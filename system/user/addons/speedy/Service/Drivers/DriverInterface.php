<?php

namespace BoldMinded\Speedy\Service\Drivers;

use BoldMinded\Speedy\Service\CacheItem;
use BoldMinded\Speedy\Service\Drivers\Support\SupportValidator;

interface DriverInterface
{
    public function getName(): string;

    public function countItems(): int;

    public function isSupported(): bool;

    public function isConfigured(): bool;

    public function getSupportValidator(): SupportValidator;

    public function setSiteName(string $siteName): DriverInterface;

    public function getItem(string $key): CacheItem;

    public function deleteItem(string $key): bool;

    public function deletePath(string $path): bool;

    public function save(CacheItem $item): bool;

    public function clear(): bool;

    public function refresh(): bool;

    public function setCreatedAt(int $time): DriverInterface;

    public function getCreatedAt(): int;
}
