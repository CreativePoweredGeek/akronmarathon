<?php

namespace BoldMinded\DataGrab\Queue\Subscribers;

use BoldMinded\DataGrab\Dependency\Illuminate\Events\Dispatcher;

class QueueSubscriber
{
    /**
     * @param Dispatcher $events
     * @return mixed
     */
    public function subscribe(Dispatcher $events)
    {
        $events->listen(
            'BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\WorkerStopping',
            'BoldMinded\DataGrab\Queue\Listeners\WorkerStoppingListener@handle'
        );

        $events->listen(
            'BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\JobProcessed',
            'BoldMinded\DataGrab\Queue\Listeners\JobProcessedListener@handle'
        );

        $events->listen(
            'BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\JobProcessing',
            'BoldMinded\DataGrab\Queue\Listeners\JobProcessingListener@handle'
        );

        $events->listen(
            'BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\JobFailed',
            'BoldMinded\DataGrab\Queue\Listeners\JobFailedListener@handle'
        );

        $events->listen(
            'BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\JobRetryRequested',
            'BoldMinded\DataGrab\Queue\Listeners\JobRetryRequestedListener@handle'
        );

        $events->listen(
            'BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\JobExceptionOccurred',
            'BoldMinded\DataGrab\Queue\Listeners\JobExceptionOccurredListener@handle'
        );

        $events->listen(
            'BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\JobQueued',
            'BoldMinded\DataGrab\Queue\Listeners\JobQueuedListener@handle'
        );

        $events->listen(
            'BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\QueueBusy',
            'BoldMinded\DataGrab\Queue\Listeners\QueueBusyListener@handle'
        );

        $events->listen(
            'BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\Looping',
            'BoldMinded\DataGrab\Queue\Listeners\LoopingListener@handle'
        );

        // @todo not sure I need to return...
        return $events;
    }
}
