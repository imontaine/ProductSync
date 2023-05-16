<?php
use React\ChildProcess\Process;
use React\EventLoop\Loop;

class Batch {

    public bool $isProcessing = false;
    public bool $isProcessed = false;
    public int $id = 0;

    public function __construct(
        public array $documents = [],
    ) {
    }

    public function addDocument($document) {
        $this->documents[] = $document;
    }

    public function countDocuments() {
        return count($this->documents);
    }

    public function process($finishCallback=null) {
        $this->isProcessing = true;
        $start = microtime(true);
        $process = new Process('php '.__DIR__.'/../process.php');
        $process->start();
        $process->stdin->write(json_encode($this->documents));
        $process->stdin->end();
        $timeoutTimer = Loop::addTimer(300 /* 5 minutes */, function () use ($process) {
            $process->terminate();
        });
        $process->on('exit', function ($code) use ($finishCallback, $start, $timeoutTimer) {
            Loop::cancelTimer($timeoutTimer);
            $finish = microtime(true);
            $diff = ($finish - $start);
            echo "batch ".$this->id." finished in ".(substr($diff,0,4))."s at ".date('Y-m-d H:i:s')."\n";
            $this->isProcessing = false;
            $this->isProcessed = true;
            $finishCallback && $finishCallback($code);
        });

        $process->stdout->on('data', function ($chunk) {
            echo "batch ".$this->id." returned: ".$chunk."\n";
        });
    }

}