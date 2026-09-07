<?php

namespace BoldMinded\Speedy\Service\Drivers;

use BoldMinded\Speedy\Service\CacheItem;
use BoldMinded\Speedy\Service\Diagnostics;
use BoldMinded\Speedy\Service\Logger\LoggerAwareTrait;

abstract class AbstractDriver implements ClearableDriverInterface, ReadableDriverInterface
{
    use LoggerAwareTrait;

    /** @var \Closure */
    private $createItem;

    /** @var string */
    protected $siteName;

    /** @var int|null */
    private $createdAt;

    /**
     * DummyDriver constructor.
     */
    public function __construct()
    {
        $this->siteName = ee()->config->item('site_short_name');
        $this->createItem = \Closure::bind(function ($key, $value, $hit) {
            $item = new CacheItem();
            $item
                ->setKey($key)
                ->setValue($value)
                ->setHit($hit)
            ;

            return $item;
        }, $this, CacheItem::class);
    }

    public function setSiteName(string $siteName): DriverInterface
    {
        $this->siteName = $siteName;

        return $this;
    }

    public function isSupported(): bool
    {
        return $this->getSupportValidator()->isSupported();
    }

    public function isConfigured(): bool
    {
        if ($this instanceof ConfigurableDriverInterface) {
            return $this->getConfiguredValidator()->isSupported();
        }

        return false;
    }

    public function isFrontEdit(string $value = ''): bool
    {
        return strpos($value, '{frontedit_link');
    }

    public function getItem(string $key): CacheItem
    {
        $f = $this->createItem;

        try {
            $value = $this->doFetch($key);
            $hit = true;
        } catch (ItemNotFoundException $e) {
            $value = null;
            $hit = false;
        }

        return $f($key, $value, $hit);
    }

    public function getItemsAtPath(string $path): array
    {
        $items = $this->getItemsFromPath($path);

        // Strip everything after the first slash leaving only the directory or file name.
        foreach ($items as $index => $item) {
            $slash_pos = strpos($item, '/');

            if ($slash_pos !== false) {
                $items[$index] = substr($item, 0, $slash_pos + 1);
            }
        }

        return array_unique($items);
    }

    public function save(CacheItem $item): bool
    {
        $value = $item->getValue();
        $value = $this->prepareCsrfToken($value);

        ee('speedy:Diagnostics')->stop($item->getKey());

        return $this->doSave($item->getKey(), $value, $item->getTTL());
    }

    /**
     * Strip CSRF token values from content before it is cached.
     * Use the same variable that EE uses internally for its placeholder.
     *
     * When using full static page caching, the StaticCacheHelper will do the replacement
     * of the current user's actual CSRF token on output. If using fragment caching then
     * EE will natively handle the replacement b/c {csrf_token} is a native variable.
     */
    private function prepareCsrfToken(string $content): string
    {
        // Update logout links
        $csrfTokenString = 'csrf_token={csrf_token}';

        $content = preg_replace(
            '/csrf_token=([a-zA-Z0-9]{40})/um',
            $csrfTokenString,
            $content
        );

        // Update hidden form fields
        $csrfTokenField = '<input type="hidden" name="csrf_token" value="{csrf_token}" />';

        return preg_replace(
            '/<input type="hidden" name="csrf_token" value="([a-zA-Z0-9]{40})"\s?\/?>/um',
            $csrfTokenField,
            $content
        );
    }

    public function buildCacheHeaders(
        int $createdAt,
        int $expiresAt,
        array|null $purgerSettings = []
    ): array
    {
        if ($expiresAt === 0) {
            $expiresAt = time() + 315576000; // 10 years
        }

        $now = new \DateTimeImmutable();
        $then = (new \DateTimeImmutable())->setTimestamp($expiresAt);

        $diff = $then->getTimestamp() - $now->getTimestamp();
        $maxAge = ($diff > 0) ? $diff : 60;

        // Not using Cloudflare/Reverse Proxy, default cache control for EE
        $cacheControlHeader = 'no-store, no-cache, must-revalidate';

        if ($purgerSettings) {
            $cacheControlValue = $purgerSettings['cache_control'] ?? '';

            $cacheControlHeader = match($cacheControlValue) {
                // Browser don't cache (dynamic-ish/logged-in pages), but Cloudflare cache
                'partial' => sprintf(
                    'public, max-age=0, s-maxage=%d',
                    $maxAge
                ),

                // Browser and Cloudflare cache
                'full' => sprintf(
                    'public, max-age=%d, s-maxage=%d, must-revalidate',
                    $maxAge,
                    $maxAge
                ),
                default => $cacheControlHeader,
            };
        }

        $headers = [];

        if (isset(ee()->output->headers) && is_array(ee()->output->headers)) {
            foreach (ee()->output->headers as $header) {
                if (isset($header[0]) && preg_match('~^([^:]+):\s*(.+)$~', $header[0], $matches)) {
                    $headers[$matches[1]] = $matches[2];
                }
            }
        }

        $headers['X-Cache-Generator'] = 'Speedy';
        $headers['Expires'] =  gmdate('D, d M Y H:i:s', $expiresAt + $maxAge) . ' GMT';
        $headers['Last-Modified'] =  gmdate('D, d M Y H:i:s', $createdAt) . ' GMT';
        $headers['Cache-Control'] = $cacheControlHeader;
        $headers['Pragma'] = 'no-cache';

        $customHeaders = ee()->config->item('speedy_custom_headers') ?: [];

        foreach ($customHeaders as $header) {
            if (preg_match('~^([^:]+):\s*(.+)$~', $header, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }

        if (ee()->extensions->active_hook('speedy_modify_cache_headers')) {
            return ee()->extensions->call('speedy_modify_cache_headers', $headers);
        }

        return $headers;
    }

    public function clear(): bool
    {
        return $this->deletePath('');
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt !== null ? $this->createdAt : time();
    }

    public function setCreatedAt(int $time): AbstractDriver
    {
        $this->createdAt = $time;

        return $this;
    }

    /**
     * @throws \BoldMinded\Speedy\Service\Drivers\ItemNotFoundException
     */
    abstract protected function doFetch(string $key): string;

    abstract protected function doSave(string $key, string $value, int $ttl): bool;
}
