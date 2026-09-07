<?php

namespace BoldMinded\Speedy\Queue\Jobs;

use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\Job;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\ShouldBeUnique;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\ShouldQueue;
use BoldMinded\Queue\Queue\Jobs\AbstractJob;

class BreakEntryCacheJob extends AbstractJob implements ShouldQueue, ShouldBeUnique
{
    public function fire(Job $job, array $payload = []): bool
    {
        try {
            $entryIds = $payload['entryIds'] ?? [];
            $siteId = $payload['siteId'] ?? 0;

            if (count($entryIds) > 0) {
                $channelEntries = ee('Model')->get('ChannelEntry')
                    ->filter('entry_id', 'IN', $payload['entryIds'])
                    ->all();

                $cacheBreaker = ee('speedy:CacheBreaker');

                if ($siteId) {
                    $cacheBreaker->setSiteId($siteId);
                }

                $cacheBreaker->_breakEntryCache($channelEntries);
           }

            // Assume the breaking happened, breakEntryCache doesn't return anything
            $job->delete();
        } catch (\Exception $exception) {
            ee('speedy:Logger')->error($exception->getMessage());

            $job->delete();
        }

        return true;
    }
}
