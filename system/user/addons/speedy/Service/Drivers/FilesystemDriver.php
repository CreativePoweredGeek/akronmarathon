<?php

namespace BoldMinded\Speedy\Service\Drivers;

use BoldMinded\Speedy\Service\Drivers\Support\SupportValidator;

class FilesystemDriver extends AbstractFilesystemDriver
{
    const NAME = 'file';

    /**
     * FilesystemDriver constructor.
     */
    public function __construct()
    {
        $cachePath = PATH_CACHE . SPEEDY_CLASS_NAME;

        if (ee()->config->item('speedy_file_cache_path') !== false) {
            $cachePath = rtrim(ee()->config->item('speedy_file_cache_path'), '/');
        }

        parent::__construct($cachePath);
    }

    // This is 100% redundant b/c it's defined in the abstract, but debugging customer
    // site clearly showed it was not configured when trying to save an entry and clear a cache item.
    // Could not replicate locally though. https://boldminded.com/support/ticket/2340
    public function isConfigured(): bool
    {
        return true;
    }

    public function countItems(): int
    {
        return iterator_count($this->getIterator());
    }

    protected function doFetch(string $key): string
    {
        $now = time();
        $file = $this->getFilename($key);

        // Check for cache file not exists or unreadable
        if (!file_exists($file) || !$fh = @fopen($file, 'rb')) {
            throw new ItemNotFoundException();
        }

        // NOTE: Need to read created at line, even if the value isn't used.
        $createdAt = (int) fgets($fh);
        $expiresAt = (int) fgets($fh);

        // Check for cache file expired
        if ($expiresAt !== 0 && $now >= $expiresAt) {
            fclose($fh);
            throw new ItemNotFoundException();
        }

        $value = stream_get_contents($fh);

        fclose($fh);

        return $value;
    }

    protected function doSave(string $key, string $value, int $ttl): bool
    {
        if ($this->isFrontEdit($value)) {
            return false;
        }

        $createdAt = $this->getCreatedAt();
        $expiresAt = $ttl ? ($createdAt + $ttl) : 0;

        $file = $this->getFilename($key);
        $data = $createdAt . "\n" . $expiresAt . "\n" . $value;

        // Attempt to create the path to the cache file.
        $path = dirname($file);

        if (!$this->ensureDirectory($path)) {
            $this->getLogger()->error(sprintf(
                'The directory "%s" is not writable.', $path
            ));

            return false;
        }

        // We set the filemtime to the expiry date so that expired cache items
        // can be easily detected without having to actually read the file.
        // Since non-expiring cache items are given a TTL of 0, we set a far
        // off filemtime of 10 years.
        if ($expiresAt === 0) {
            $expiresAt = time() + 315576000; // 10 years
        }

        // To prevent race conditions where multiple threads corrupt the cache
        // by writing to the same file at the same time we first write the data
        // out to a temp file.
        $tmpFile = $this->cachePath . '/' . uniqid('', true);

        if (file_put_contents($tmpFile, $data) === false) {
            return false;
        }

        // Then use rename() (which is an atomic operation) to create the
        // cache data, or overwrite any existing file.
        if (@rename($tmpFile, $file)) {
            @chmod($file, 0644);
            @touch($file, $expiresAt);

            return true;
        }

        @unlink($tmpFile);

        return false;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSupportValidator(): SupportValidator
    {
        return SupportValidator::create()
            ->addRule('Cache path is writable', function () {
                if (!is_dir($this->siteCachePath)) {
                    $this->ensureDirectory($this->cachePath);
                }

                return is_dir($this->cachePath) && is_writable($this->cachePath);
            });
    }

    public function getStats(): array
    {
        $usage = 0;
        foreach ($this->getIterator() as $name => $file) {
            $usage += $file->getSize();
        }

        $available = disk_free_space($this->cachePath);

        return [
            self::STATS_HITS             => null,
            self::STATS_MISSES           => null,
            self::STATS_UPTIME           => null,
            self::STATS_MEMORY_USAGE     => $usage,
            self::STATS_MEMORY_AVAILABLE => $available,
        ];
    }

    public function getItemMetadata(string $key): array
    {
        $file = $this->getFilename($key);

        if (!file_exists($file) || !$fh = @fopen($file, 'rb')) {
            throw new ItemNotFoundException();
        }

        $fileSize = filesize($file);
        $createdAt = (int) fgets($fh);
        $expiresAt = (int) fgets($fh);

        fclose($fh);

        return [
            self::META_TTL           => $expiresAt > 0 ? ($expiresAt - $createdAt) : 0,
            self::META_TTL_REMAINING => $expiresAt > 0 ? ($expiresAt - time()) : 0,
            self::META_SIZE          => $fileSize,
            self::META_CREATED_AT    => $createdAt,
            self::META_EXPIRES_AT    => $expiresAt,
        ];
    }
}
