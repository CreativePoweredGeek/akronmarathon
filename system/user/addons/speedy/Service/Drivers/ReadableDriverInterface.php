<?php

namespace BoldMinded\Speedy\Service\Drivers;

interface ReadableDriverInterface extends DriverInterface
{
    const STATS_HITS             = 'hits';
    const STATS_MISSES           = 'misses';
    const STATS_UPTIME           = 'uptime';
    const STATS_MEMORY_USAGE     = 'memory_usage';
    const STATS_MEMORY_AVAILABLE = 'memory_available';

    const META_TTL           = 'ttl';
    const META_TTL_REMAINING = 'ttl_remaining';
    const META_SIZE          = 'size';
    const META_CREATED_AT    = 'created_at';
    const META_EXPIRES_AT    = 'expires_at';

    public function getItemsAtPath(string $path): array;

    public function getItemsFromPath(string $path): array;

    /**
     * Recursively list every item under $path WITHOUT filtering by expiry and
     * WITHOUT mutating the cache. Used by the CP item browser's search so it
     * stays consistent with the (expiry-agnostic) listing and never deletes
     * items as a side effect. Drivers whose getItemsFromPath() already does no
     * expiry filtering may simply delegate to it.
     */
    public function getAllItemsFromPath(string $path): array;

    /**
     * @throws \BoldMinded\Speedy\Service\Drivers\ItemNotFoundException
     */
    public function getItemMetadata(string $key): array;

    public function getStats(): array;
}
