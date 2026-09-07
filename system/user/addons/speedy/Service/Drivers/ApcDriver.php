<?php

namespace BoldMinded\Speedy\Service\Drivers;

use BoldMinded\Speedy\Service\Drivers\Support\SupportValidator;

class ApcDriver extends AbstractDriver
{
    const NAME = 'apc';

    private string $prefix;

    /**
     * ApcDriver constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->prefix = SPEEDY_CLASS_NAME . '/' . self::NAME . '/' . $this->siteName;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSupportValidator(): SupportValidator
    {
        return SupportValidator::create()
            ->addRule('The APC extension is loaded', function () {
                return function_usable('apc_fetch') &&
                       class_exists('APCIterator');
            })
            ->addRule('The APC extension is enabled', function () {
                return (bool) ini_get('apc.enabled');
            });
    }
    public function countItems(): int
    {
        $path = $this->prefix . '/';
        $pattern = sprintf('~^%s~', preg_quote($path, '~'));
        $iterator = new \APCIterator('user', $pattern, APC_ITER_KEY);

        return iterator_count($iterator);
    }

    public function deleteItem(string $key): bool
    {
        return apc_delete($key) || !apc_exists($key);
    }
    public function deletePath(string $path): bool
    {
        $path = rtrim($this->prefix . '/' . $path, '/');
        $pattern = sprintf('~^%s/~', preg_quote($path, '~'));
        $iterator = new \APCIterator('user', $pattern, APC_ITER_KEY);

        return apc_delete($iterator);
    }
    public function refresh(): bool
    {
        return true;
    }

    /**
     * APC expires keys natively, so getItemsFromPath() does no expiry filtering
     * and is already the read-only list search needs.
     */
    public function getAllItemsFromPath(string $path): array
    {
        return $this->getItemsFromPath($path);
    }

    public function getItemsFromPath(string $path): array
    {
        $path = $this->prefix . '/' . ltrim($path, '/');
        $path = rtrim($path, '/') . '/';

        $pattern = sprintf('~^%s~', preg_quote($path, '~'));
        $iterator = new \APCIterator('user', $pattern, APC_ITER_KEY);

        $items = [];
        $path_len = strlen($path);

        // Strip the $path prefix from each filename
        foreach ($iterator as $key => $data) {
            $items[] = substr($key, $path_len);
        }

        sort($items, SORT_STRING);

        return $items;
    }

    /**
     * @throws \BoldMinded\Speedy\Service\Drivers\ItemNotFoundException
     */
    public function getItemMetadata(string $key): array
    {
        $key = $this->prefix . '/' . $key;
        $pattern = sprintf('~^%s$~', preg_quote($key, '~'));
        $iterator = new \APCIterator('user', $pattern);

        $cache_info = iterator_to_array($iterator);

        if (!array_key_exists($key, $cache_info)) {
            throw new ItemNotFoundException();
        }

        $ttl = $cache_info[$key]['ttl'];
        $createdAt = $cache_info[$key]['creation_time'];
        $expiresAt = $createdAt + $ttl;

        return [
            self::META_TTL           => $ttl,
            self::META_TTL_REMAINING => $expiresAt - time(),
            self::META_SIZE          => $cache_info[$key]['mem_size'],
            self::META_CREATED_AT    => $createdAt,
            self::META_EXPIRES_AT    => $expiresAt,
        ];
    }

    public function getStats(): array
    {
        $info = apc_cache_info('user', true);
        $sma = apc_sma_info();

        if (PHP_VERSION_ID >= 50500) {
            $info['num_hits'] = isset($info['num_hits']) ? $info['num_hits'] : $info['nhits'];
            $info['num_misses'] = isset($info['num_misses']) ? $info['num_misses'] : $info['nmisses'];
            $info['start_time'] = isset($info['start_time']) ? $info['start_time'] : $info['stime'];
        }

        return [
            self::STATS_HITS             => $info['num_hits'],
            self::STATS_MISSES           => $info['num_misses'],
            self::STATS_UPTIME           => $info['start_time'],
            self::STATS_MEMORY_USAGE     => $info['mem_size'],
            self::STATS_MEMORY_AVAILABLE => $sma['avail_mem'],
        ];
    }

    /**
     * @throws \BoldMinded\Speedy\Service\Drivers\ItemNotFoundException
     */
    protected function doFetch(string $key): string
    {
        $key = $this->prefix . '/' . $key;

        $value = apc_fetch($key, $success);

        if ($success) {
            return $value;
        }

        throw new ItemNotFoundException();
    }

    protected function doSave(string $key, string $value, int $ttl): bool
    {
        if ($this->isFrontEdit($value)) {
            return false;
        }

        $key = $this->prefix . '/' . $key;

        return apc_store($key, $value, $ttl);
    }
}
