<?php

namespace BoldMinded\Speedy\Queue\Jobs;

use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\Job;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\ShouldBeUnique;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\ShouldQueue;
use BoldMinded\Queue\Queue\Jobs\AbstractJob;

class RefreshUrlJob extends AbstractJob implements ShouldQueue, ShouldBeUnique
{
    public function fire(Job $job, array $payload = []): bool
    {
        $url = $payload['url'] ?? '';
        $interval = $payload['interval'] ?? 0;

        // A blank URL is a permanent payload error, retrying can't fix it.
        if (!$url) {
            $job->delete();

            return false;
        }

        // Since this is queued as separate jobs this shouldn't be much of an issue
        // but still slow things down a bit and prevent stampeding the server.
        if ($interval) {
            @sleep($interval);
        }

        if (!ee('speedy:Request')->get($url)) {
            $message = sprintf('RefreshUrlJob failed for URL: %s', $url);

            ee('speedy:Logger')->error($message);

            // Throw so the queue worker releases the job for retry with backoff
            // instead of leaving it reserved until the reservation timeout.
            throw new \RuntimeException($message);
        }

        $job->delete();

        return true;
    }
}
