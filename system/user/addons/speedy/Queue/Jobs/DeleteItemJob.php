<?php

namespace BoldMinded\Speedy\Queue\Jobs;

use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\Job;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\ShouldBeUnique;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\ShouldQueue;
use BoldMinded\Queue\Queue\Jobs\AbstractJob;
use BoldMinded\Speedy\Service\Drivers\DriverInterface;
use BoldMinded\Speedy\Service\Purgers\PurgerFactory;
use BoldMinded\Speedy\Service\Purgers\UrlCollector;

class DeleteItemJob extends AbstractJob implements ShouldQueue, ShouldBeUnique
{
    public function fire(Job $job, array $payload = []): bool
    {
        $key = $payload['key'] ?? '';
        $siteId = $payload['siteId'] ?? 0;

        // A blank key is a permanent payload error, retrying can't fix it.
        if (!$key) {
            $job->delete();

            return false;
        }

        $drivers = ee('speedy:DriverFactory');
        $diagnostics = ee('speedy:Diagnostics');
        $logger = ee('speedy:Logger');

        /** @var DriverInterface $driver */
        foreach ($drivers->getDrivers() as $driver) {
            if ($driver->isSupported() && $driver->isConfigured()) {
                $logger->info(
                    sprintf(
                        'Attempting to delete cache item %s',
                        $key
                    )
                );

                $result = $driver->deleteItem($key);

                $logger->info(
                    sprintf(
                        '%s %s',
                        $key, $result === true ? 'deleted' : 'not deleted'
                    )
                );

                $diagnostics->clearDiagnostics($key);
            }
        }

        $urls = (new UrlCollector([$key], $siteId ?: null))->collect();

        if (count($urls) > 0 && !(new PurgerFactory())->create()->purgeUrls($urls)) {
            $message = sprintf('DeleteItemJob failed to purge URLs for key: %s', $key);

            $logger->error($message);

            // Throw before deleting the Url records so the queue worker retries
            // and the retry can collect and purge the same URLs again.
            throw new \RuntimeException($message);
        }

        // The cache item is gone, so remove the URL records that pointed at it,
        // otherwise the same URLs are re-purged by every later break of this key.
        $urlQuery = ee('Model')->get('speedy:Url')->filter('key', $key);

        if ($siteId) {
            $urlQuery->filter('site_id', $siteId);
        }

        $urlQuery->delete();

        $job->delete();

        return true;
    }
}
