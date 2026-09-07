<?php

namespace BoldMinded\DataGrab\Queue\Listeners;

use BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\JobFailed;
use BoldMinded\DataGrab\Model\ImportStatus;

class JobFailedListener
{
    public function handle(JobFailed $event)
    {
        if ($event->queue !== 'delete') {
            ee('datagrab:Importer')->updateStatus(ImportStatus::FAILED);
        }

        ee('datagrab:Importer')->logger->log($event->exception->getMessage());
    }
}
