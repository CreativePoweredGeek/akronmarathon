<?php

namespace BoldMinded\DataGrab\Queue\Listeners;

use BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\Looping;
use BoldMinded\DataGrab\Model\ImportStatus;

class LoopingListener
{
    public function handle(Looping $event)
    {
        if ($event->queue !== 'delete') {
            ee('datagrab:Importer')->updateStatus(ImportStatus::RUNNING);
        }
    }
}
