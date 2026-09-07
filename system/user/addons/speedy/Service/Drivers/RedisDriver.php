<?php

namespace BoldMinded\Speedy\Service\Drivers;

use BoldMinded\Speedy\Service\Config;
use BoldMinded\Speedy\Service\EnvSettings;
use Iterator;
use Redis;
use RedisException;
use BoldMinded\Speedy\Service\CacheItem;
use BoldMinded\Speedy\Service\Drivers\Configuration\RedisSettingsForm;
use BoldMinded\Speedy\Service\Drivers\Support\SupportValidator;

class RedisDriver extends AbstractDriver implements ConfigurableDriverInterface
{
    const NAME = 'redis';

    /** @var Redis */
    private $client;

    /** @var string */
    private $prefix;

    /** @var array */
    private $settings = [];

    /** @var bool */
    private $hasFileConfigOverride = false;

    public function __construct()
    {
        parent::__construct();

        $this->prefix = SPEEDY_CLASS_NAME . '/' . self::NAME . '/' . $this->siteName;
    }

    protected function checkIgnoreUrls(string $key): bool
    {
        if (!isset($this->settings['ignore_urls']) || empty($this->settings['ignore_urls'])) {
            return false;
        }

        $key = preg_replace('#^redis/#', '', $key);

        foreach ($this->settings['ignore_urls'] as $setting) {
            if (preg_match('#' . $setting['url'] . '#', $key)) {
                return true;
            }
        }

        return false;
    }

    public function configure(array $settings): void
    {
        $this->settings = $settings;
        $this->client = new Redis();

        if (isset($settings['servers']) && !empty($settings['servers'])) {
            $server = current($settings['servers']);
            $result = null;

            $server = EnvSettings::override($server);

            try {
                $host = $server['host'];
                $timeout = $server['timeout'] ?? 0;

                if (is_string($host) && strlen($host) > 0 && $host[0] === '/') {
                    $port = 0; // Unix socket
                } else {
                    $port = !empty($server['port']) ? (int) $server['port'] : 6379;
                }

                $result = $this->client->connect($host, $port, (float) $timeout);
            } catch (RedisException $e) {
                $this->getLogger()->error('Redis connection refused: '.$e->getMessage());
                $this->client = null;
            }

            // Redis will return false sometimes instead of throwing an exception
            if (!$result) {
                $this->getLogger()->error('Redis connection failed.');
                $this->client = null;
            }

            // If a password is set, attempt to authenticate
            if (!empty($server['password']) && $result) {
                $this->client->auth($server['password']);
            }

            if (!empty($server['database'])) {
                $this->client->select($server['database']);
            }

            if (!empty($server['prefix'])) {
                $this->prefix = $server['prefix'] . $this->prefix;
            }
        }
    }

    public function clear(): bool
    {
        $count = $this->databaseCount();

        // @todo Add hidden config variable to use $this->client->flushDb(); instead.
        // Document that if the same Redis DB is used for something other
        // than Speedy this might have undesired effects.
        if ($count < 50000) {
            $keys = $this->getItemsFromPath('/');

            foreach ($keys as $key) {
                $this->client->del($this->getKeyPath($key));
            }

            return true;
        }

        $this->client->flushDb();

        return true;
    }

    private function databaseCount(): int
    {
        // This won't provide a perfect count if something else on the
        // site is also using the same Redis DB, but in most cases this should be enough.
        if (!$this->client) {
            return 0;
        }

        $info = $this->client->info();
        preg_match('/^keys=(\d+),/', $info['db0'] ?? '', $matches);

        return $matches[1] ?? 0;
    }

    public function countItems(): int
    {
        $count = $this->databaseCount();

        // On large sites this could cause very slow load times in the CP.
        // It's been tested up to 500,000 redis keys and the CP takes ~ 1 minute to load.
        // If count is a reasonable number, try to get a more accurate count.
        if ($count < 50000) {
            $count = 0;
            $iterator = null;
            while ($keys = $this->client->scan($iterator, $this->prefix . '/*', 1000)) {
                $count += count($keys);
            }

            return $count;
        }

        // Otherwise we'll just go with an estimate that isn't based on what our
        // Speedy path prefix is and might also include other random things if something
        // other than Speedy is using the same Redis DB.
        return $count;
    }

    public function deleteItem($key): bool
    {
        if (CacheItem::isKeyRegex($key)) {
            return $this->deleteMatchingItems($key);
        }

        $key = $this->getKeyPath($key);

        if (!$this->client || !$this->client->hGetAll($key)) {
            return true;
        }

        $result = $this->client->del($key);

        return $result > 0;
    }

    public function deleteMatchingItems(string $key): bool
    {
        // If we got here accidentally...
        if (!CacheItem::isKeyRegex($key)) {
            return $this->deleteItem($key);
        }

        $parts = explode('^', $key);
        $keyBase = $parts[0];
        $pattern = $parts[1] ?? null;

        if (!$pattern) {
            return false;
        }

        $keys = $this->getItemsFromPath('/' . $parts[0] . '/');
        $failedClears = [];

        foreach ($keys as $partialKey) {
            $key = $this->getKeyPath($keyBase . $partialKey);

            if (!$this->client->del($key)) {
                $failedClears[] = $key;
            }
        }

        return count($failedClears) === 0;
    }

    public function deletePath(string $path): bool
    {
        $keys = $this->getItemsFromPath($path);

        foreach($keys as $key) {
            $this->client->del($this->getKeyPath($path .'/'. $key));
        }

        return true;
    }

    /**
     * @throws ItemNotFoundException
     */
    protected function doFetch(string $key): string
    {
        $key = $this->getKeyPath($key);

        /** @var array $data */
        $data = $this->client->hGetAll($key);

        if (isset($data['content'])) {
            return $data['content'];
        }

        throw new ItemNotFoundException();
    }

    /**
     * @param string $key
     * @param string $value
     * @param int    $ttl
     * @return bool
     */
    protected function doSave(string $key, string $value, int $ttl): bool
    {
        if (($this->isStatic() && $this->checkIgnoreUrls($key)) || $this->isFrontEdit($value)) {
            return true;
        }

        $key = $this->getKeyPath($key);
        $data = [
            'ttl' => $ttl,
            'made' => $this->getCreatedAt(),
            'content' => $value,
        ];

        if ($this->isStatic()) {
            $headers = $this->buildCacheHeaders(
                $this->getCreatedAt(),
                $this->getCreatedAt() + $ttl,
                $this->settings['purger'] ?? null
            );

            $data['headers'] = json_encode(array_map(
                fn($k, $v) => "$k: $v",
                array_keys($headers),
                $headers
            ));
        }

        $success = $this->client->hMSet($key, $data);

        if ($success && $ttl > 0) {
            $this->client->expire($key, $ttl);
        }

        return $success;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getConfigurationForm(): RedisSettingsForm
    {
        return new RedisSettingsForm();
    }

    public function getConfiguredValidator(): SupportValidator
    {
        $validator = SupportValidator::create()
            ->addRule('At least one server must be specified', function () {
                return isset($this->settings['servers']) && count($this->settings['servers']) > 0;
            });

        $siteCachePath = Config::getSiteCachePath();

        if (isset($this->settings['static']) && $this->settings['static'] === 'y' && !file_exists($siteCachePath . '/configured.txt')) {
            $validator->addRule('Redis as Static is enabled, but no handler has been created, or the /static directory and utility files do not exist. <a href="https://docs.boldminded.com/speedy/docs/control-panel#static-cache-configuration">Please see the documentation</a>.', function () {
                return false;
            });
        }

        return $validator;
    }

    public function getSupportValidator(): SupportValidator
    {
        return SupportValidator::create()
            ->addRule('The Redis extension is loaded and configured', function () {
                return extension_loaded('redis');
            });
    }

    public function getItemsFromPath(string $path): array
    {
        $path = $this->prefix . '/' . ltrim($path, '/');
        $path = rtrim($path, '/') . '/';

        $pattern = sprintf('~^%s~', preg_quote($path, '~'));
        $iterator = $this->getIterator($pattern);

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
     * Redis expires keys natively, so getItemsFromPath() never returns expired
     * items and performs no expiry filtering of its own — it is already the
     * read-only, full list search needs.
     */
    public function getAllItemsFromPath(string $path): array
    {
        return $this->getItemsFromPath($path);
    }

    private function getIterator(string $pattern = ''): Iterator
    {
        if ($pattern === '') {
            $pattern = sprintf('~^%s~', preg_quote($this->prefix, '~'));
        }

        $iterator = null;
        $store = [];

        if (!$this->client) {
            return new \ArrayIterator([]);
        }

        try {
            // Collect all matching keys first
            while ($keys = $this->client->scan($iterator, $this->prefix . '*', 500)) {
                foreach ($keys as $key) {
                    if (preg_match($pattern, $key)) {
                        $matchedKeys[] = $key;
                    }
                }
            }

            // Fetch all hashes in a single pipeline
            if (!empty($matchedKeys)) {
                $this->client->pipeline();

                foreach ($matchedKeys as $key) {
                    $this->client->hGetAll($key);
                }

                $results = $this->client->exec();

                foreach ($matchedKeys as $index => $key) {
                    $store[$key] = $results[$index];
                }
            }
        } catch (RedisException $exception) {
            // Fail silently...
        }

        return new \ArrayIterator($store);
    }

    public function getItemMetadata(string $key): array
    {
        $key = $this->getKeyPath($key);

        /** @var array $data */
        $data = $this->client->hGetAll($key);

        if (!isset($data['content'])) {
            throw new ItemNotFoundException();
        }

        $fileSize = $this->getSize($data['content']);
        $createdAt = (int) $data['made'];
        $expiresAt = (int) $data['made'] + (int) $data['ttl'];

        return [
            self::META_TTL           => (int) $data['ttl'],
            self::META_TTL_REMAINING => $expiresAt > 0 ? ($expiresAt - time()) : 0,
            self::META_SIZE          => $fileSize,
            self::META_CREATED_AT    => $createdAt,
            self::META_EXPIRES_AT    => $expiresAt,
        ];
    }

    private function getSize(string $string = ''): int
    {
        return ini_get('mbstring.func_overload') ? mb_strlen($string , '8bit') : strlen($string);
    }

    public function getStats(): array
    {
        $usage = $this->client->info();

        return [
            self::STATS_HITS             => $usage['keyspace_hits'] ?? '',
            self::STATS_MISSES           => $usage['keyspace_misses'] ?? '',
            self::STATS_UPTIME           => $usage['uptime_in_days'] . ' days',
            self::STATS_MEMORY_USAGE     => $usage['used_memory_dataset'] ?? '',
            self::STATS_MEMORY_AVAILABLE => $usage['total_system_memory'] ?? '',
        ];
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function getKeyPath(string $key): string
    {
        CacheItem::validateKey($key);

        return $this->prefix . '/' . $key;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    private function isStatic(): bool
    {
        if (isset($this->settings['static']) && $this->settings['static'] === 'yes') {
            return true;
        }

        return false;
    }

    public function refresh(): bool
    {
        $now = time();
        $keys = $this->getItemsFromPath('/');

        foreach($keys as $key) {
            /** @var array $data */
            $data = $this->client->hGetAll($this->getKeyPath($key));

            if (isset($data['made']) && isset($data['ttl']) && $now >= $data['made'] + $data['ttl']) {
                $this->client->del($this->getKeyPath($key));
            }
        }

        return true;
    }

    public function hasFileConfigOverride(): bool
    {
        return $this->hasFileConfigOverride;
    }

    public function setHasFileConfigOverride(bool $hasFileConfigOverride): RedisDriver
    {
        $this->hasFileConfigOverride = $hasFileConfigOverride;

        return $this;
    }
}
