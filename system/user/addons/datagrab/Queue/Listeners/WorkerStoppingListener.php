<?php

namespace BoldMinded\DataGrab\Queue\Listeners;

use BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\WorkerStopping;
use BoldMinded\DataGrab\Dependency\Illuminate\Queue\Worker;
use BoldMinded\DataGrab\Model\ImportStatus;

class WorkerStoppingListener extends AbstractListener
{
    public function handle(WorkerStopping $event)
    {
        ee('datagrab:Importer')->updateStatus(ImportStatus::WAITING);

        $message = 'WORKER STOPPED: ';

        if ($event->status === Worker::EXIT_MEMORY_LIMIT) {
            $message .= 'memory limit exceeded';
        } elseif ($event->status === Worker::EXIT_ERROR) {
            $message .= 'error';
        } else {
            $message .= 'success';
        }

        ee('datagrab:Importer')->logger->log($message);

        if ($this->isCli()) {
            echo PHP_EOL . 'Worker Stopped' . PHP_EOL;
        }
    }
}
