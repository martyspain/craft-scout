<?php

namespace rias\scout\jobs;

use craft\queue\BaseJob;
use rias\scout\engines\Engine;
use rias\scout\Scout;

class ImportIndex extends BaseJob
{
    /** @var string */
    public $indexName;

    public function execute($queue): void
    {
        /** @var Engine $engine */
        $engine = Scout::$plugin->getSettings()->getEngines()->first(function (Engine $engine) {
            return $engine->scoutIndex->indexName === $this->indexName;
        });

        if (!$engine) {
            return;
        }

        $hasElements = $engine->scoutIndex->elements;
        $elements = $hasElements ?: $engine->scoutIndex->criteria;
        $totalElements = $hasElements ? count($elements) : $engine->scoutIndex->criteria->count();
        $batchSize = Scout::$plugin->getSettings()->batch_size;

        if ($hasElements) {
            $batch = array_chunk($elements, $batchSize);
        } else {
            $batch = $elements->batch($batchSize);
        }

        $elementsUpdated = 0;

        foreach ($batch as $elements) {
            $engine->update($elements);
            $elementsUpdated += count($elements);
            $this->setProgress($queue, $elementsUpdated / $totalElements);
        }
    }

    protected function defaultDescription(): string
    {
        return sprintf(
            'Indexing element(s) in “%s”',
            $this->indexName
        );
    }
}
