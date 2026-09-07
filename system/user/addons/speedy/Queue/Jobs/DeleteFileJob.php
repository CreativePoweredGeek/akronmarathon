<?php

namespace BoldMinded\Speedy\Queue\Jobs;

use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\Job;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\ShouldBeUnique;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\ShouldQueue;
use BoldMinded\Queue\Queue\Jobs\AbstractJob;
use BoldMinded\Speedy\Service\Drivers\DriverInterface;

class DeleteFileJob extends AbstractJob implements ShouldQueue, ShouldBeUnique
{
    public function fire(Job $job, array $payload = []): bool
    {
        $type = $payload['type'] ?? '';
        $name = $payload['name'] ?? '';

        // A blank type or name is a permanent payload error, retrying can't fix it.
        if (!$type || !$name) {
            $job->delete();

            return false;
        }

        $logger = ee('speedy:Logger');

        if ($type === 'dir') {
            $logger->info(
                sprintf(
                    'Attempting to delete directory %s',
                    $name
                )
            );

            @rmdir($name);
        } else {
            $logger->info(
                sprintf(
                    'Attempting to delete file %s',
                    $name
                )
            );

            @unlink($name);
        }

        $job->delete();

        return true;
    }
}
