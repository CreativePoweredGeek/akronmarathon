<?php

namespace BoldMinded\Speedy\Service\Drivers;

use BoldMinded\Speedy\Service\Drivers\Support\SupportValidator;

class ApcuDriver extends AbstractDriver
{
    const NAME = 'apcu';

    private string $prefix;
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
            ->addRule('The APCu extension is loaded', function () {
                return function_usable('apcu_fetch') &&
                       class_exists('APCuIterator');
            })
            ->addRule('The APCu extension is enabled', function () {
                return (bool) ini_get('apc.enabled');
            });
    }

    /**
     * @return int
     */
    public function countItems()
    {
        $path = $this->prefix . '/';
        $pattern = sprintf('~^%s~', preg_quote($path, '~'));
        $iterator = new \APCUIterator($pattern, APC_ITER_KEY);

        return iterator_count($iterator);
    }

    public function clear()
    {
        // TODO: Implement clear() method.
        return true;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function deleteItem($key)
    {
        return apcu_delete($key) || !apcu_exists($key);
    }

    /**
     * @param string $path
     * @return bool
     */
    public function deletePath($path)
    {
        $path = rtrim($this->prefix . '/' . $path, '/');
        $pattern = sprintf('~^%s/~', preg_quote($path, '~'));
        $iterator = new \APCUIterator($pattern, APC_ITER_KEY);

        return apcu_delete($iterator);
    }

    /**
     * @return bool
     */
    public function refresh()
    {
        return true;
    }

    /**
     * APCu expires keys natively, so getItemsFromPath() does no expiry filtering
     * and is already the read-only list search needs.
     *
     * @param string $path
     * @return string[]
     */
    public function getAllItemsFromPath(string $path): array
    {
        return $this->getItemsFromPath($path);
    }

    /**
     * @param string $path
     * @return string[]
     */
    public function getItemsFromPath($path)
    {
        $path = $this->prefix . '/' . ltrim($path, '/');
        $path = rtrim($path, '/') . '/';

        $pattern = sprintf('~^%s~', preg_quote($path, '~'));
        $iterator = new \APCUIterator($pattern, APC_ITER_KEY);

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
     * @param string $key
     * @return array
     * @throws \BoldMinded\Speedy\Service\Drivers\ItemNotFoundException
     */
    public function getItemMetadata($key)
    {
        $key = $this->prefix . '/' . $key;
        $pattern = sprintf('~^%s$~', preg_quote($key, '~'));
        $iterator = new \APCUIterator($pattern);

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

    /**
     * @return array
     */
    public function getStats()
    {
        $info = apcu_cache_info(true);
        $sma = apcu_sma_info();

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
     * @param string $key
     * @return string
     * @throws \BoldMinded\Speedy\Service\Drivers\ItemNotFoundException
     */
    protected function doFetch($key)
    {
        $key = $this->prefix . '/' . $key;

        $value = apcu_fetch($key, $success);

        if ($success) {
            return $value;
        }

        throw new ItemNotFoundException();
    }

    /**
     * @param string $key
     * @param string $value
     * @param int    $ttl
     * @return string
     */
    protected function doSave(string $key, string $value, int $ttl): bool
    {
        if ($this->isFrontEdit($value)) {
            return false;
        }

        $key = $this->prefix . '/' . $key;

        return apcu_store($key, $value, $ttl);
    }
}
