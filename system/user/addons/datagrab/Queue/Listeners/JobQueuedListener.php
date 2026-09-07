<?php

namespace BoldMinded\DataGrab\Queue\Listeners;

use BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\JobQueued;

class JobQueuedListener
{
    public function handle(JobQueued $event) {}
}
