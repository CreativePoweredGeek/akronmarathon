<?php

namespace BoldMinded\DataGrab\Queue\Listeners;

use BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\QueueBusy;

class QueueBusyListener
{
    public function handle(QueueBusy $event)
    {
        if ($event->queue !== 'delete') {
            ee('datagrab:Importer')->logger->log('Queue Busy');
        }
    }
}
