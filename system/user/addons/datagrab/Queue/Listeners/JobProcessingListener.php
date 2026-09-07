<?php

namespace BoldMinded\DataGrab\Queue\Listeners;

use BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\JobProcessing;

class JobProcessingListener
{
    public function handle(JobProcessing $event) {}
}
