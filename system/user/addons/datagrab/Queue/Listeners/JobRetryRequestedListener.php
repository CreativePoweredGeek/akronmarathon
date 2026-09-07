<?php

namespace BoldMinded\DataGrab\Queue\Listeners;

use BoldMinded\DataGrab\Dependency\Illuminate\Queue\Events\JobRetryRequested;

class JobRetryRequestedListener
{
    public function handle(JobRetryRequested $event) {}
}
