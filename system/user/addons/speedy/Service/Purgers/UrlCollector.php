<?php

namespace BoldMinded\Speedy\Service\Purgers;

final class UrlCollector
{
    private array $items;
    private int|null $siteId;

    public function __construct(array $items = [], int|null $siteId = null)
    {
        $this->items = $items;
        $this->siteId = $siteId;
    }

    public function collect(): array
    {
        if (count($this->items) === 0) {
            return [];
        }

        $query = ee('Model')->get('speedy:Url')
            ->filter('key', 'IN', self::normalizeKeys($this->items));

        if ($this->siteId) {
            $query->filter('site_id', $this->siteId);
        }

        return $query->all()->pluck('url');
    }

    /**
     * Url keys are stored without the speedy/{site}/ prefix that Tag keys carry,
     * but callers pass items in both formats. Strip the prefix so prefixed items
     * don't silently fail to match against exp_speedy_urls.
     */
    public static function normalizeKeys(array $items): array
    {
        return array_values(array_unique(array_map(
            static fn (string $item) => preg_replace('#^' . SPEEDY_CLASS_NAME . '/[^/]+/#', '', $item),
            $items
        )));
    }
}
