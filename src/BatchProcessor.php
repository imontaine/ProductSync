<?php
use React\ChildProcess\Process;
use React\EventLoop\Loop;

class BatchProcessor {

    public Batch $currentBatch;
    public array $processingBatches = [];
    public array $queuedBatches = [];
    public int $id = 0;
    public array $callbacks = [];

    public function __construct(
        public readonly int $batchSize,
        public readonly int $concurrentBatchLimit
    ) {
        $this->currentBatch = new Batch();
        $this->id++;
        $this->currentBatch->id = $this->id;
    }

    public function processDocument($document) {

        $this->currentBatch->addDocument($document);

        if ($this->currentBatch->countDocuments() == $this->batchSize) {
            $this->addBatchToQueue($this->currentBatch);
            $this->currentBatch = new Batch();
            $this->id++;
            $this->currentBatch->id = $this->id;
        }
    }

    public function addBatchToQueue($batch) {
        $this->queuedBatches[] = $batch;
        $this->processedQueuedBatch();
    }

    protected function processedQueuedBatch() {
        $processingBatches = array_filter($this->queuedBatches, fn($batch) => $batch->isProcessing);

        if (count($processingBatches) == $this->concurrentBatchLimit) {
            //echo "concurrent limit reached, last batch added: ".(end($this->queuedBatches))->id."\n";
            return;
        }

        $pendingBatches = array_values(array_filter($this->queuedBatches, fn($batch) => !$batch->isProcessing && !$batch->isProcessed));
        $pendingBatch = empty($pendingBatches) ? false : $pendingBatches[0];
        if ($pendingBatch) {
            //echo "Processing first pending batch: ".$pendingBatch->id."\n";
            //time_elapsed();
            echo "batch ".$pendingBatch->id." with ".count($pendingBatch->documents)." docs started at ". date('Y-m-d H:i:s')."\n";
            $pendingBatch->process(function() use($pendingBatch) {
                $this->processedQueuedBatch();
                // Check if all queue batches were processed
                if (array_every($this->queuedBatches, fn($b) => $b->isProcessed)){
                    $this->finish();
                }
            });
        }
    }

    protected function finish() {
        $callbacks = $this->callbacks['finish'] ?? [];
        foreach ($callbacks as $callback) {
            $callback();
        }
    }

    public function onFinish(callable $callback) {
        $this->callbacks['finish'][] = $callback;
    }
}