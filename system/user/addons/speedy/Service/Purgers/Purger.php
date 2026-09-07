<?php

namespace BoldMinded\Speedy\Service\Purgers;

interface Purger
{
    public function purgeAll(): bool;
    public function purgeUrl(string $url): bool;
    public function purgeUrls(array $urls): bool;
    public function settings(): array;
    public function getName(): string;
    public function isEnabled(): bool;
}
