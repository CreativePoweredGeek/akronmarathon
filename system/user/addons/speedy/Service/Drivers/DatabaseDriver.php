<?php

namespace BoldMinded\Speedy\Service\Drivers;

use BoldMinded\Speedy\Model\DatabaseDriverStats;
use BoldMinded\Speedy\Model\DatabaseDriver as DatabaseDriverModel;
use BoldMinded\Speedy\Service\CacheItem;
use BoldMinded\Speedy\Service\Drivers\Support\SupportValidator;
use ExpressionEngine\Service\Model\Collection;
use ExpressionEngine\Service\Model\Query\Builder;

class DatabaseDriver extends AbstractDriver
{
    const NAME = 'database';

    private int $siteId;

    private string $prefix;

    public function __construct()
    {
        parent::__construct();

        $this->siteId = ee()->config->item('site_id');

        $this->prefix = SPEEDY_CLASS_NAME . '/' . $this->siteName . '/';
    }

    /**
     * The database driver's getItemsFromPath() selects rows by key prefix with
     * no expiry filter, so it already returns the full list search needs.
     */
    public function getAllItemsFromPath(string $path): array
    {
        return $this->getItemsFromPath($path);
    }

    public function getItemsFromPath(string $path): array
    {
        if (!$path) {
            $items = $this->getModel()->all();
        } else {
            $path = rtrim($path, '/') . '/';

            $query = "SELECT SUBSTRING(`key`, '%s') as `key`,
                id, ttl, expires_at, created_at, `value`,
                CASE ttl WHEN '0' then '0' ELSE (created_at + ttl - UNIX_TIMESTAMP()) END as ttl_remaining
                FROM exp_speedy_db_driver
                WHERE SUBSTRING(`key`, 1, '%s') = '%s'
                ORDER BY `key` ASC";

            /** @var \CI_DB_result $result */
            $result = ee('db')->query(sprintf(
                $query,
                strlen($path) + 1,
                strlen($path),
                ee('db')->escape_str($path)
            ));

            $items = new Collection($result->result_array());
        }

        if (empty($items)) {
            return [];
        }

        return $items->getDictionary('id', 'key');
    }

    public function refresh(): bool
    {
        $this->getModel()->all()->delete();

        return true;
    }

    public function countItems(): int
    {
        // Purge expired items before counting
        ee('db')->where('expires_at < ', time())->delete('exp_speedy_db_driver');

        return $this->getModel()->count();
    }

    public function deleteItem(string $key): bool
    {
        if (CacheItem::isKeyRegex($key)) {
            return $this->deleteMatchingItems($key);
        }

        $item = $this->getModel()->filter('key', /*$this->prefix . */ $key)->first();

        if ($item) {
            $item->delete();
            return true;
        }

        return false;
    }

    public function deleteMatchingItems(string $key): bool
    {
        // If we got here accidentally...
        if (!CacheItem::isKeyRegex($key)) {
            return $this->deleteItem($key);
        }

        $key = CacheItem::removeRegexIndicator($key);

        $items = $this->getModel()->filter('key', 'REGEXP', $key)->all();

        foreach ($items as $item) {
            $item->delete();
        }

        return true;
    }

    public function deletePath(string $path): bool
    {
        $path = rtrim($path, '/') . '/%';

        $this->getModel()->filter('key', 'LIKE', $path)->delete();

        return true;
    }

    protected function doFetch(string $key): string
    {
        $item = $this->getModel()->filter('key', $key)->first();

        // Check for cache file not exists or unreadable
        if (!$item) {
            $this->addMiss($key);
            throw new ItemNotFoundException();
        }

        if ($item->ttl !== 0 && time() > $item->expires_at) {
            $this->deleteItem($key);
            throw new ItemNotFoundException();
        }

        $this->addHit($key);

        return $item->value;
    }

    private function addHit(string $key): void
    {
        $item = $this->getStatsModel()->filter('key', $key)->first();

        if (!$item) {
            $item = $this->makeStatsModel();
            $item->key = $key;
        }

        $item->hits++;
        $item->save();
    }

    private function addMiss(string $key): void
    {
        $item = $this->getStatsModel()->filter('key', $key)->first();

        if (!$item) {
            $item = $this->makeStatsModel();
            $item->key = $key;
        }

        $item->misses++;
        $item->save();
    }

    protected function doSave(string $key, string $value, int $ttl): bool
    {
        if ($this->isFrontEdit($value)) {
            return false;
        }

        $createdAt = $this->getCreatedAt();
        $expiresAt = $ttl ? ($createdAt + $ttl) : 0;

        $item = $this->makeModel();
        $item->set([
            'site_id' => $this->siteId,
            'key' => $key,
            'ttl' => $ttl,
            'value' => $value,
            'created_at' => $createdAt,
            'expires_at' => $expiresAt,
        ]);

        $result = $item->save();

        return $result->getId();
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSupportValidator(): SupportValidator
    {
        return SupportValidator::create()
            ->addRule('Database driver exists', function () {
                return ee('db')->table_exists('speedy_db_driver');
            });
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function getStats(): array
    {
        $usage = 0;
        $items = $this->getModel()->all();

        foreach ($items as $item) {
            $usage += $this->getSize($item->value);
        }

        $uptime = ee('db')->query('SELECT
              VARIABLE_VALUE AS uptime_seconds,
              NOW() AS "now",
              NOW() - INTERVAL VARIABLE_VALUE SECOND AS "up_since",
              DATEDIFF(NOW(), NOW() - INTERVAL VARIABLE_VALUE SECOND) AS "uptime_days"
            FROM performance_schema.session_status
            WHERE VARIABLE_NAME = "Uptime"');

        $hits = ee('db')->select_sum('hits')->get('speedy_db_driver_stats')->row('hits');
        $misses = ee('db')->select_sum('misses')->get('speedy_db_driver_stats')->row('misses');
        $uptime = $uptime->row('uptime_days') ?: 0;

        return [
            self::STATS_HITS             => $hits,
            self::STATS_MISSES           => $misses,
            self::STATS_UPTIME           => $uptime . ' Days',
            self::STATS_MEMORY_USAGE     => $usage,
            self::STATS_MEMORY_AVAILABLE => null,
        ];
    }

    public function getItemMetadata($key): array
    {
        $item = $this->getModel()->filter('key', $key)->first();

        if (!$item) {
            throw new ItemNotFoundException();
        }

        return [
            self::META_TTL           => $item->ttl ?? 0,
            self::META_TTL_REMAINING => $item->expires_at > 0 ? ($item->expires_at - time()) : 0,
            self::META_SIZE          => $this->getSize($item->value),
            self::META_CREATED_AT    => $item->created_at,
            self::META_EXPIRES_AT    => $item->expires_at,
        ];
    }

    /**
     * @param $string
     * @return int
     */
    private function getSize(string $string = ''): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($string, '8bit');
        }

        return strlen($string);
    }

    private function getModel(): Builder
    {
        return ee('Model')->get('speedy:DatabaseDriver')->filter('site_id', $this->siteId);
    }

    private function getStatsModel(): Builder
    {
        return ee('Model')->get('speedy:DatabaseDriverStats')->filter('site_id', $this->siteId);
    }

    private function makeModel(): DatabaseDriverModel
    {
        return ee('Model')->make('speedy:DatabaseDriver');
    }

    private function makeStatsModel(): DatabaseDriverStats
    {
        return ee('Model')->make('speedy:DatabaseDriverStats');
    }
}
