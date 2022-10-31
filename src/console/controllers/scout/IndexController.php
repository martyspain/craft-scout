<?php

namespace rias\scout\console\controllers\scout;

use Craft;
use craft\helpers\Console;
use rias\scout\console\controllers\BaseController;
use rias\scout\engines\Engine;
use rias\scout\jobs\ImportIndex;
use rias\scout\Scout;
use yii\console\ExitCode;

class IndexController extends BaseController
{
    public $defaultAction = 'refresh';

    /** @var bool */
    public $force = false;

    /** @var bool */
    public $queue = false;

    public function options($actionID): array
    {
        return ['force', 'queue'];
    }

    public function actionFlush($index = '')
    {
        if (
            $this->force === false
            && $this->confirm(Craft::t('scout', 'Are you sure you want to flush Scout?')) === false
        ) {
            return ExitCode::OK;
        }

        $engines = Scout::$plugin->getSettings()->getEngines();
        $engines->filter(function (Engine $engine) use ($index) {
            return $index === '' || $engine->scoutIndex->indexName === $index;
        })->each(function (Engine $engine) {
            $engine->flush();
            $this->stdout("Flushed index {$engine->scoutIndex->indexName}\n", Console::FG_GREEN);
        });

        return ExitCode::OK;
    }

    public function actionImport($index = '')
    {
        $engines = Scout::$plugin->getSettings()->getEngines();

        $engines->filter(function (Engine $engine) use ($index) {
            return $index === '' || $engine->scoutIndex->indexName === $index;
        })->each(function (Engine $engine) {
            if ($this->queue) {
                Craft::$app->getQueue()
                    ->ttr(Scout::$plugin->getSettings()->ttr)
                    ->priority(Scout::$plugin->getSettings()->priority)
                    ->push(new ImportIndex([
                        'indexName' => $engine->scoutIndex->indexName,
                    ]));
                $this->stdout("Added ImportIndex job for '{$engine->scoutIndex->indexName}' to the queue".PHP_EOL, Console::FG_GREEN);
            } else {
                $hasElements = $engine->scoutIndex->elements;
                $elements = $hasElements ?: $engine->scoutIndex->criteria;
                $totalElements = $hasElements ? count($elements) : $engine->scoutIndex->criteria->count();
                $batchSize = Scout::$plugin->getSettings()->batch_size;
                $elementsUpdated = 0;

                if ($hasElements) {
                    $batch = array_chunk($elements, $batchSize);
                } else {
                    $batch = $elements->batch($batchSize);
                }

                foreach ($batch as $elements) {
                    $engine->update($elements);
                    $elementsUpdated += count($elements);
                    $this->stdout("Updated {$elementsUpdated}/{$totalElements} element(s) in {$engine->scoutIndex->indexName}\n", Console::FG_GREEN);
                }
            }
        });

        return ExitCode::OK;
    }

    public function actionRefresh($index = '')
    {
        $this->actionFlush($index);
        $this->actionImport($index);

        return ExitCode::OK;
    }
}
