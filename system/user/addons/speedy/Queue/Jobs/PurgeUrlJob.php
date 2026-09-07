<?php

namespace BoldMinded\Speedy\Queue\Jobs;

use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\Job;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\ShouldBeUnique;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\ShouldQueue;
use BoldMinded\Queue\Queue\Jobs\AbstractJob;
use BoldMinded\Speedy\Service\Purgers\PurgerFactory;

class PurgeUrlJob extends AbstractJob implements ShouldQueue, ShouldBeUnique
{
    public function fire(Job $job, array $payload = []): bool
    {
        $url = $payload['url'] ?? '';

        // A blank URL is a permanent payload error, retrying can't fix it.
        if (!$url) {
            $job->delete();

            return false;
        }

        $purger = (new PurgerFactory())->create();

        if (!$purger->purgeUrl($url)) {
            $message = sprintf(
                'PurgeUrlJob failed for URL: %s using purger: %s',
                $url,
                $purger->getName()
            );

            ee('speedy:Logger')->error($message);

            // Throw so the queue worker releases the job for retry with backoff,
            // and the real error appears in the failed jobs log instead of only
            // a MaxAttemptsExceededException after repeated reservation timeouts.
            throw new \RuntimeException($message);
        }

        $job->delete();

        return true;
    }
}
