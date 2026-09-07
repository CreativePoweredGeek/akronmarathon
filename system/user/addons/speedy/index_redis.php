<?php

define('SPEEDY_STATIC_START', microtime(true));

require_once '{{utilitiesCachePath}}/Csrf/SpeedyCsrfStorageInterface.php';
require_once '{{utilitiesCachePath}}/Csrf/SpeedyCsrf.php';
require_once '{{utilitiesCachePath}}/Csrf/SpeedyCookie.php';
require_once '{{utilitiesCachePath}}/Csrf/SpeedyDatabase.php';
require_once '{{utilitiesCachePath}}/StaticCacheHelper.php';

$response = null;

if (!isset($_GET['speedy_bypass'])) {
    try {
        $handler = new RedisStaticCacheHandler();
        $response = $handler->handle();
    } catch (\RedisException $exception) {
        error_log($exception->getMessage());
    }
}

if (isset($_GET['speedy_debug'])) {
    echo '<!-- [Speedy Debug] requested item is: ' . $handler->getCacheKey() . ' -->'."\n";
    echo '<!-- [Speedy Debug] uri is: ' . $handler->getUri() . ' -->'."\n";
}

unset($client);
unset($handler);

// Valid cached response?
if ($response !== null) {
    if (isset($_GET['speedy_debug'])) {
        echo '<!-- [Speedy Debug] this is a cached page -->'."\n";
    }

    echo $response;
    exit;
}

if (isset($_GET['speedy_debug'])) {
    echo '<!-- [Speedy Debug] no Redis cache response found. Booting EE. -->';
}

// No cached response, proceed with normal EE operations.
require_once '{{index_path}}';
exit;

class RedisStaticCacheHandler
{
    /**
     * @see Core/Output->_display()
     */
    const EE_EXPIRATION_DATE = 870066000;

    /**
     * @var string
     */
    private $cacheKey = null;

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var bool
     */
    private $disabled = false;

    /**
     * @var string
     */
    private $keyPrefix = null;

    /**
     * @var Redis|null
     */
    private $redis = null;

    /**
     * @var array
     */
    private $settings = null;

    /**
     * @var string
     */
    private $uri = '';

    /**
     * @var StaticCacheHelper|null
     */
    private $staticCacheHelper = null;

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var array
     */
    private $allowList = [];
    public function __construct()
    {
        $csrf = new SpeedyCsrf($this->options);
        $csrf->checkToken();
        $csrf->setTokenHeader();

        $this->redis = new Redis();
        $this->staticCacheHelper = new StaticCacheHelper($this->allowList, $_GET, $csrf, $this->options);
        $this->connect();
    }
    private function connect()
    {
        $settings = $this->settings;

        if (isset($settings['servers']) && !empty($settings['servers'])) {
            $server = current($settings['servers']);

            try {
                $host = $server['host'];
                $timeout = $server['timeout'] ?? 0;

                if (is_string($host) && strlen($host) > 0 && $host[0] === '/') {
                    $port = 0; // Unix socket
                } else {
                    $port = !empty($server['port']) ? (int) $server['port'] : 6379;
                }

                $result = $this->redis->connect($host, $port, (float) $timeout);
            } catch (RedisException $e) {
                error_log('Redis connection refused: '.$e->getMessage());
                $this->redis = null;
            }

            // Redis will return false sometimes instead of throwing an exception
            if (!$result) {
                error_log('Redis connection failed.');
                $this->redis = null;
            }

            // If a password is set, attempt to authenticate
            if (!empty($server['password']) && $result) {
                $this->redis->auth($server['password']);
            }

            if (!empty($server['database'])) {
                $this->redis->select($server['database']);
            }

            if (!empty($server['prefix'])) {
                $this->redis->setOption(Redis::OPT_PREFIX, $server['prefix']);
            }
        }
    }

    public function handle()
    {
        $uri = $this->getKey();
        $cachedItem = $this->redis->hGetAll($uri);

        if ($cachedItem === null) {
            $this->debug('the key "' . $uri . '" was not found');
        }

        if ($cachedItem !== null && isset($cachedItem['content']) && $cachedItem['content'] !== '') {
            $this->parseMetaData($cachedItem);
            // @todo should this use the new prepareHtmlContent instead? Removing special characters I believe
            // was added to strip some escaping of characters so the full HTML can be saved as a class property
            // on the index.php cache file. Shouldn't need that with Redis caching...
            return $this->staticCacheHelper->prepareContent($cachedItem['content']);
        }

        return null;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        $uri = $this->staticCacheHelper->getUri();
        $segments = $this->parseSegments($uri);
        $queryString = $this->staticCacheHelper->getQueryString();

        $key = $this->keyPrefix . $segments;

        if ($queryString) {
            $key .= '+' . $queryString;
        }

        $this->cacheKey = $key;
        $this->uri = $uri;

        return $key;
    }

    /**
     * Return the uri segments without the GET parameters
     *
     * @param $uri
     * @return string
     */
    private function parseSegments($uri): string
    {
        $uriParts = explode('?', $uri);
        return isset($uriParts[0]) ? $uriParts[0] : $uri;
    }

    /**
     * @param array $cachedItem
     */
    public function parseMetaData(array $cachedItem)
    {
        // If seconds is set to 0 then the cache is never deleted, unless done so manually
        if ($this->disabled || $this->isCacheItemExpired($cachedItem)) {
            if ($this->deleteCachedItem()) {
                $this->staticCacheHelper->redirectTo($this->uri);
            }

            exit();
        }

        header('X-Cache-Generator: Speedy');

        // Set the HTTP headers (these are JSON-encoded, because Redis doesn't support nested hashes)
        if (isset($cachedItem['headers'])) {
            $headers = json_decode($cachedItem['headers'], true, 2);

            if ($headers && is_array($headers)) {
                foreach ($headers as $header) {
                    if (strpos($header, 'Expires:') === 0) {
                        $time = strtotime(str_replace('Expires: ', '', $header));
                        // EE by default sets the Expires heading to "Mon, 26 Jul 1997 05:00:00 GMT" (870066000) as part
                        // of it's no Cache headers generation function in Output->_display(), which basically tells
                        // search engines etc to not cache the pages. We're using Redis to serve items and cache them
                        // indefinitely. Even in this case we output no-cache headers with past Expires dates. In order
                        // for our custom Expires headers to work, we need to ignore the default value EE is setting.
                        if ($this->isExpiredCache($time) && $this->deleteCachedItem()) {
                            $this->staticCacheHelper->redirectTo($this->uri);
                            exit();
                        }
                    }

                    header($header);
                }
            }
        }
    }

    /**
     * @param array $cachedItem
     * @return bool
     */
    private function isCacheItemExpired(array $cachedItem): bool
    {
        return ($cachedItem['ttl'] != 0 && time() > $cachedItem['made'] + $cachedItem['ttl']);
    }

    /**
     * @param int $time
     * @return bool
     */
    private function isExpiredCache($time): bool
    {
        if (time() > $time && $time !== self::EE_EXPIRATION_DATE) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    private function deleteCachedItem(): bool
    {
        if ($this->redis->del([$this->cacheKey])) {
            return true;
        }

        $this->debug('Could not delete expired cache item.');

        return false;
    }

    /**
     * If debug mode is enabled, output the debug message. Otherwise, redirect to home.
     *
     * @param string $message
     */
    private function debug($message = '')
    {
        if ($this->debug) {
            echo '<!-- [Speedy Debug] ' . $message . ' -->';
            exit();
        }

        $this->staticCacheHelper->redirectTo();
    }

    /**
     * @return string
     */
    public function getCacheKey(): string
    {
        return $this->cacheKey;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }
}
