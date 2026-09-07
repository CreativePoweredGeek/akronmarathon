<?php

namespace BoldMinded\Speedy\Service\Drivers;

use BoldMinded\Speedy\Service\Drivers\Configuration\MemcacheSettingsForm;
use BoldMinded\Speedy\Service\Drivers\Support\SupportValidator;
use Iterator;

class MemcacheDriver extends AbstractDriver
{
    const NAME = 'memcache';

    /** @var array */
    private static $defaultClientOptions = [
        'persistent' => false,
        'servers'    => [],
    ];

    /** @var string */
    private $prefix;

    /** @var array */
    private $servers;

    /** @var \Memcache */
    private $client;

    public function __construct()
    {
        parent::__construct();

        $this->prefix = SPEEDY_CLASS_NAME . '/' . self::NAME . '/' . $this->siteName;
    }

    public function clear(): bool
    {
        $this->client->flush();

        return true;
    }

    public function configure(array $settings): void
    {
        $settings += static::$defaultClientOptions;

        $this->servers = (array) $settings['servers'];
        $persistent = $settings['persistent'] === 'y';

        $this->client = new \Memcache();

        foreach ($this->servers as $server) {
            $weight = is_numeric($server['weight']) ? (int) $server['weight'] : 1;
            $this->client->addServer($server['host'], $server['port'], $persistent, $weight);
        }
    }

    public function countItems(): int
    {
        $iterator = $this->getIterator();

        return iterator_count($iterator);
    }

    public function deleteItem(string $key): bool
    {
        $key = $this->prefix . '/' . $key;

        $result = $this->client->delete($key);
        $items = $this->client->get([$key]);

        return $result && !isset($items[$key]);
    }

    public function deletePath(string $path): bool
    {
        $path = $this->prefix . '/' . ltrim($path, '/');
        $path = rtrim($path, '/') . '/';

        $pattern = sprintf('~^%s~', preg_quote($path, '~'));
        $iterator = $this->getIterator($pattern);

        $prefix_len = strlen($this->prefix . '/');

        $success = true;
        foreach ($iterator as $key => $value) {
            $key = substr($key, $prefix_len);
            $success = $this->deleteItem($key) && $success;
        }

        return $success;
    }

    protected function doFetch(string $key): string
    {
        if (!$this->client) {
            return '';
        }

        $key = $this->prefix . '/' . $key;

        $items = $this->client->get([$key]);

        if (isset($items[$key])) {
            $contents = json_decode($items[$key]);
        } else {
            throw new ItemNotFoundException();
        }

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

        return $this->client->set($key, json_encode($contents), 0, (int) $ttl);
    }

    public function getConfigurationForm(): MemcacheSettingsForm
    {
        return new MemcacheSettingsForm();
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
                    $stats = $this->client->getExtendedStats();
                    foreach ($stats as $server => $data) {
                        if (!empty($data['time'])) {
                            return true;
                        }
                    }
                }
                return false;
            });
    }

    public function getSupportValidator(): SupportValidator
    {
        return SupportValidator::create()
            ->addRule('The memcache extension is loaded', function () {
                return extension_loaded('memcache');
            })->addRule('The memcache class exists', function () {
                return class_exists('Memcache');
            });
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Memcache expires keys natively, so getItemsFromPath() does no expiry
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

        $items = [];
        $path_len = strlen($path);

        // Strip the $path prefix from each filename
        foreach ($iterator as $key => $data) {
            $items[] = substr($key, $path_len);
        }

        sort($items, SORT_STRING);

        return $items;
    }

    public function getItemMetadata(string $key): array
    {
        $key = $this->prefix . '/' . $key;

        $items = $this->client->get([$key]);

        if (isset($items[$key])) {
            $contents = $items[$key];
        } else {
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

    public function getStats(): array
    {
        $stats = [];
        $rawStats = $this->client->getExtendedStats();

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

    private function getIterator(string $pattern = ''): Iterator
    {
        if ($pattern === '') {
            $pattern = sprintf('~^%s~', preg_quote($this->prefix, '~'));
        }

        $keys = $this->getAllKeys();
        $items = $this->client->get($keys);

        $store = [];
        if ($items) {
            foreach ($items as $key => $value) {
                if (preg_match($pattern, $key)) {
                    $store[$key] = $value;
                }
            }
        }

        return new \ArrayIterator($store);
    }

    /**
     * @see https://gist.github.com/vanjos/7013411
     */
    private function getAllKeys(): array
    {
        $keys = [];

        foreach ($this->servers as $server) {
            $response = $this->sendRawCommand($server, 'stats items');
            if ($response === false) {
                continue;
            }

            $lines = explode("\r\n", $response);

            $listed = [];
            foreach ($lines as $line) {
                if (preg_match('~STAT items:([\d]+):number ([\d]+)~', $line, $matches)) {
                    if (!in_array($matches[1], $listed)) {
                        $listed[] = $matches[1];
                        $response = $this->sendRawCommand($server, "stats cachedump {$matches[1]} {$matches[2]}");
                        if ($response === false) {
                            continue;
                        }
                        if (preg_match_all('~ITEM (.*?) ~', $response, $matches)) {
                            foreach ($matches[1] as $key) {
                                $keys[] = $key;
                            }
                        }
                    }
                }
            }
        }

        return $keys;
    }

    public function refresh(): bool
    {
        return true;
    }

    private function sendRawCommand(array $server, string $command): bool|string
    {
        if (!$sh = @fsockopen($server['host'], $server['port'])) {
            return false;
        }

        fwrite($sh, $command . "\r\n");

        $buffer = '';
        while (!feof($sh)) {
            $buffer .= fgets($sh, 256);
            if (strpos($buffer, "END\r\n") !== false ||
                strpos($buffer, "DELETED\r\n") !== false ||
                strpos($buffer, "NOT_FOUND\r\n") !== false ||
                strpos($buffer, "OK\r\n") !== false
            ) {
                break;
            }
        }

        fclose($sh);

        return $buffer;
    }
}
