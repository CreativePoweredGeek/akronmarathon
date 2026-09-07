<?php

namespace BoldMinded\Speedy\Service\Purgers;

class Dummy implements Purger
{
    public function __construct(array $settings = [])
    {
    }

    public function getName(): string
    {
        return 'Dummy';
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function settings(): array
    {
        return [];
    }

    public function purgeAll(): bool
    {
        return true;
    }

    public function purgeUrl(string $url): bool
    {
        return true;
    }

    public function purgeUrls(array $urls): bool
    {
        return true;
    }
}
