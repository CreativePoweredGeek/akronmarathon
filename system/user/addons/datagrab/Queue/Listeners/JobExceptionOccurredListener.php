<?php

namespace BoldMinded\DataGrab\Queue\Listeners;

use BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\JobExceptionOccurred;

class JobExceptionOccurredListener
{
    public function handle(JobExceptionOccurred $event)
    {
        ee('datagrab:Importer')->logger->log($event->exception->getMessage());
        ee('datagrab:Importer')->logger->log($event->exception->getTraceAsString());
    }
}
