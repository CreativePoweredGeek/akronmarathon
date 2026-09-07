<?php

namespace BoldMinded\DataGrab\Queue\Listeners;

use BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\JobProcessed;

class JobProcessedListener
{
    public function handle(JobProcessed $jobProcessed)
    {
        // $item = $jobProcessed->job->payload();
    }
}
