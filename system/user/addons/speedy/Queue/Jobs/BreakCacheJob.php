<?php

namespace BoldMinded\Speedy\Queue\Jobs;

use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\Job;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\ShouldBeUnique;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\ShouldQueue;
use BoldMinded\Queue\Queue\Jobs\AbstractJob;

class BreakCacheJob extends AbstractJob implements ShouldQueue, ShouldBeUnique
{
    public function fire(Job $job, int $siteId = 1): bool
    {
        try {
            ee('speedy:CacheBreaker')->setSiteId($siteId)->_breakCache();

            // Assume the breaking happened, breakEntryCache doesn't return anything
            $job->delete();
        } catch (\Exception $exception) {
            ee('speedy:Logger')->error($exception->getMessage());

            $job->delete();

            return false;
        }

        return true;
    }
}
