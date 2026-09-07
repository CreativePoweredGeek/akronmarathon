<?php

namespace BoldMinded\Speedy\Service\Drivers;

use BoldMinded\Speedy\Service\Drivers\Configuration\MemcachedSettingsForm;
use BoldMinded\Speedy\Service\Drivers\Support\SupportValidator;
use BoldMinded\Speedy\Service\EnvSettings;
use Iterator;

class MemcachedDriver extends AbstractDriver implements ConfigurableDriverInterface
{
    const NAME = 'memcached';

    /** @var array */
    private static $defaultClientOptions = [
        'persistent' => false,
        'servers'    => [],
    ];

    /** @var \Memcached */
    private $client;

    /** @var string */
    private $prefix;

    /** @var array */
    private $servers;

    /** @var bool */
    private $hasFileConfigOverride = false;

    public function __construct()
    {
        parent::__construct();

        $this->prefix = SPEEDY_CLASS_NAME . '/' . self::NAME . '/' . $this->siteName;
    }

    public function configure(array $settings): void
    {
        $settings += static::$defaultClientOptions;

        $this->servers = array_map(fn ($server) => EnvSettings::override($server), $settings['servers']);
        $persistent_id = ($settings['persistent'] === 'y') ? 'speedy_' . $this->siteName : null;

        $this->client = new \Memcached($persistent_id);
        $this->client->setOption(\Memcached::OPT_NO_BLOCK, true);
        $this->client->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);

        // Set client's servers, taking care of persistent connections
        if (!$this->client->isPristine()) {
            $oldServers = [];
            foreach ($this->client->getServerList() as $server) {
                $oldServers[] = [$server['host'], (int) $server['port']];
            }

            $newServers = [];
            foreach ($this->servers as $server) {
                $newServers[] = [$server['host'], (int) $server['port']];
            }

            if ($newServers !== $oldServers) {
                // Before resetting, ensure $this->servers is valid
                $this->client->addServers($this->servers);
                $this->client->resetServerList();
            }
        }

        $this->client->addServers($this->servers);
    }

    public function countItems(): int
    {
        $iterator = $this->getIterator();

        if ($iterator === false) {
            return '';
        }

        return iterator_count($iterator);
    }

    public function deleteItem(string $key): bool
    {
        $key = $this->prefix . '/' . $key;

        return $this->client->delete($key) ||
            $this->client->getResultCode() === \Memcached::RES_NOTFOUND;
    }

    public function deletePath(string $path): bool
    {
        $path = $this->prefix . '/' . ltrim($path, '/');
        $path = rtrim($path, '/') . '/';

        $pattern = sprintf('~^%s~', preg_quote($path, '~'));
        $iterator = $this->getIterator($pattern);

        if ($iterator === false) {
            return false;
        }

        $delete = [];
        foreach ($iterator as $key => $value) {
            $delete[] = $key;
        }

        return $this->client->deleteMulti($delete) ||
            $this->client->getResultCode() === \Memcached::RES_NOTFOUND;
    }

    public function clear(): bool
    {
        $this->client->flush();

        return true;
    }

    protected function doFetch(string $key): string
    {
        if (!$this->client) {
            return '';
        }

        $key = $this->prefix . '/' . $key;

        $contents = $this->client->get($key);

        if ($this->client->getResultCode() === \Memcached::RES_NOTFOUND) {
            throw new ItemNotFoundException();
        }

        $contents = json_decode($contents);

        return $contents->value;
    }

    protected function doSave(string $key, string $value, int $ttl): bool
    {
        if ($this->isFrontEdit($value)) {
            return false;
        }

        $key = $this->prefix . '/' . $key;
        $createdAt = time();

        // If the expiration value is larger than 30 days, the server will
        // consider it to be a Unix timestamp rather than an offset from current time.
        // @see http://php.net/manual/en/memcached.expiration.php
        if ($ttl > 2592000) {
            $ttl = $createdAt + $ttl;
        }

        $contents = [
            'ttl'        => $ttl,
            'value'      => $value,
            'created_at' => $createdAt,
            'expires_at' => $createdAt + $ttl,
        ];

        return $this->client->set($key, json_encode($contents), (int) $ttl);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getConfigurationForm(): MemcachedSettingsForm
    {
        return new MemcachedSettingsForm();
    }

    public function getConfiguredValidator(): SupportValidator
    {
        $hasServers = count($this->servers) >= 1;

        return SupportValidator::create()
            ->addRule('At least one server must be specified', function () use ($hasServers) {
                return $hasServers;
            })
            ->addRule('At least one server must be responding', function () use ($hasServers) {
                if ($hasServers) {
                    $stats = $this->client->getStats();

                    if (is_array($stats)) {
                        foreach ($stats as $data) {
                            if (!empty($data['time'])) {
                                return true;
                            }
                        }
                    }
                }
                return false;
            });
    }

    public function getSupportValidator(): SupportValidator
    {
        return SupportValidator::create()
            ->addRule('The memcached extension is loaded', function () {
                return extension_loaded('memcached');
            })
            ->addRule('The memcached extension is version 2.2.0 or greater', function () {
                return version_compare(phpversion('memcached'), '2.2.0', '>=');
            });
    }

    /**
     * Memcached expires keys natively, so getItemsFromPath() does no expiry
     * filtering and is already the read-only list search needs.
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
        $iterator = $this->getIterator($pattern);

        if ($iterator === false) {
            return [];
        }

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

        $contents = $this->client->get($key);

        if ($this->client->getResultCode() === \Memcached::RES_NOTFOUND) {
            throw new ItemNotFoundException();
        }

        $before = memory_get_usage();
        $contents = json_decode($contents);
        $size = memory_get_usage() - $before;

        return [
            self::META_TTL           => $contents->ttl,
            self::META_TTL_REMAINING => $contents->expires_at - time(),
            self::META_SIZE          => $size,
            self::META_CREATED_AT    => $contents->created_at,
            self::META_EXPIRES_AT    => $contents->expires_at,
        ];
    }

    private function getIterator(string $pattern = ''): Iterator
    {
        if ($pattern === '') {
            $pattern = sprintf('~^%s~', preg_quote($this->prefix, '~'));
        }

        $keys = $this->client->getAllKeys();

        // Known issue with php-memcached < 3.0.1
        // @see https://github.com/php-memcached-dev/php-memcached/issues/203
        if ($keys === false) {
            return new \ArrayIterator([]);
        }

        $this->client->getDelayed($keys, false);
        $items = $this->client->fetchAll();

        $store = [];
        if ($items) {
            foreach ($items as $item) {
                if (preg_match($pattern, $item['key'])) {
                    $store[$item['key']] = $item['value'];
                }
            }
        }

        return new \ArrayIterator($store);
    }

    public function getStats(): array
    {
        $stats = [];
        $rawStats = $this->client->getStats();

        $servers = [];
        foreach ($this->servers as $server) {
            $servers[$server['host'] . ':' . $server['port']] = $server['weight'];
        }
        arsort($servers);

        foreach ($servers as $server => $_) {
            $data = $rawStats[$server];
            $stats[] = [
                'label' => $server,
                'stats' => [
                    self::STATS_HITS             => isset($data['get_hits']) ? $data['get_hits'] : null,
                    self::STATS_MISSES           => isset($data['get_misses']) ? $data['get_misses'] : null,
                    self::STATS_UPTIME           => isset($data['uptime']) ? $data['uptime'] : null,
                    self::STATS_MEMORY_USAGE     => isset($data['bytes']) ? $data['bytes'] : null,
                    self::STATS_MEMORY_AVAILABLE => isset($data['limit_maxbytes']) ? $data['limit_maxbytes'] : null,
                ],
            ];
        }

        return $stats;
    }

    public function refresh(): bool
    {
        return true;
    }

    public function hasFileConfigOverride(): bool
    {
        return $this->hasFileConfigOverride;
    }

    public function setHasFileConfigOverride(bool $hasFileConfigOverride): MemcachedDriver
    {
        $this->hasFileConfigOverride = $hasFileConfigOverride;

        return $this;
    }
}
