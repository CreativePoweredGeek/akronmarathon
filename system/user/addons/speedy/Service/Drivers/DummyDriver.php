<?php

namespace BoldMinded\Speedy\Service\Drivers;

use BoldMinded\Speedy\Service\CacheItem;
use BoldMinded\Speedy\Service\Drivers\Support\SupportValidator;
use BoldMinded\Speedy\Service\Logger\Logger;

class DummyDriver implements DriverInterface
{
    const NAME = 'dummy';

    /** @var \Closure */
    private $createItem;

    /** @var string */
    protected $siteName;

    /**
     * DummyDriver constructor.
     */
    public function __construct()
    {
        $this->createItem = \Closure::bind(function ($key) {
            $item = new CacheItem();
            $item
                ->setKey($key)
                ->setHit(false)
            ;

            return $item;
        }, $this, CacheItem::class);
    }

    public function setSiteName(string $siteName): DriverInterface
    {
        $this->siteName = $siteName;

        return $this;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function countItems(): int
    {
        return 0;
    }

    public function isSupported(): bool
    {
        return $this->getSupportValidator()->isSupported();
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function getSupportValidator(): SupportValidator
    {
        return SupportValidator::create();
    }

    public function getItem(string $key): CacheItem
    {
        $f = $this->createItem;

        return $f($key);
    }

    /**
     * @return array
     */
    public function getItemsFromPath(): array
    {
        return [];
    }

    public function getAllItemsFromPath(string $path): array
    {
        return [];
    }

    public function deleteItem(string $key): bool
    {
        return true;
    }

    public function deletePath(string $path): bool
    {
        return true;
    }

    public function save(CacheItem $item): bool
    {
        return false;
    }

    public function clear(): bool
    {
        return true;
    }

    public function refresh(): bool
    {
        return true;
    }

    public function setLogger(Logger $logger): bool
    {
        return true;
    }

    public function getCreatedAt(): int
    {
        return 0;
    }

    public function setCreatedAt(int $createdAt): DummyDriver
    {
        return $this;
    }
}
