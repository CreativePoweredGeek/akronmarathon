<?php

namespace BoldMinded\Speedy\Service\Drivers;

use BoldMinded\Speedy\Queue\Jobs\DeleteFileJob;
use BoldMinded\Speedy\Queue\QueueableTrait;
use BoldMinded\Speedy\Service\SpeedyRecursiveFilterIterator;
use Iterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use BoldMinded\Speedy\Service\CacheItem;
use RecursiveRegexIterator;
use RegexIterator;
use Throwable;

abstract class AbstractFilesystemDriver extends AbstractDriver
{
    use QueueableTrait;

    protected string $cachePath;
    protected string $siteCachePath;
    public function __construct(string $cachePath)
    {
        parent::__construct();

        $this->cachePath = $cachePath;
        $this->siteCachePath = $cachePath . '/' . $this->siteName;

        if (!$this->ensureDirectory($this->siteCachePath)) {
            $this->getLogger()->error(sprintf('Site cache directory "%s" could not be created.', $this->siteCachePath));
        }
    }
    public function isConfigured(): bool
    {
        return true;
    }
    public function clear(): bool
    {
        return $this->clearCachePath($this->siteCachePath);
    }

    /**
     * List only the immediate children of $path, without recursing into the
     * whole subtree.
     *
     * The default implementation (AbstractDriver::getItemsAtPath) calls
     * getItemsFromPath(), which recursively enumerates and sorts every leaf
     * beneath $path and then collapses the result to first-level names. On a
     * large cache that means stat-ing the entire subtree just to render one
     * directory level in the control panel. This override reads a single
     * directory instead, so navigation cost scales with the number of entries
     * in the current folder rather than the size of everything below it.
     *
     * Output shape matches the previous behaviour: subdirectories are returned
     * with a trailing slash (navigable in the CP), files are returned through
     * formatItemPathName() (e.g. the static driver strips "/index.php").
     */
    public function getItemsAtPath(string $path): array
    {
        $base = rtrim($this->siteCachePath . '/' . ltrim($path, '/'), '/');

        // Confine listing to the cache root (same guard as getItemsFromPath).
        try {
            $this->assertWithinCacheRoot($base);
        } catch (\InvalidArgumentException $exception) {
            return [];
        }

        if (!@is_dir($base)) {
            return [];
        }

        $dirs = [];
        $files = [];

        try {
            $iterator = new \FilesystemIterator($base, \FilesystemIterator::SKIP_DOTS);
        } catch (\Throwable $exception) {
            return [];
        }

        foreach ($iterator as $entry) {
            $name = $entry->getFilename();

            // Ignore Speedy's own support files (utilities/, etc.).
            if (in_array($name, SpeedyRecursiveFilterIterator::$FILTERS, true)) {
                continue;
            }

            if ($entry->isDir()) {
                // A directory that directly holds the item's data file (e.g. the
                // static driver stores each item as a dir containing index.php)
                // IS a cache item, not a folder to descend into. List it WITHOUT
                // a trailing slash so the CP renders it as a viewable item rather
                // than a navigable folder.
                if ($this->isItemDirectory($entry->getPathname())) {
                    $files[$name] = true;
                } else {
                    $dirs[$name . '/'] = true;
                }
            } elseif (!$this->isInternalFile($name)) {
                $files[$name] = true;
            }
        }

        $dirNames = array_keys($dirs);
        $fileNames = array_keys($files);

        sort($dirNames, SORT_STRING);
        sort($fileNames, SORT_STRING);

        return array_merge($dirNames, $fileNames);
    }

    /**
     * Recursively list every item path under $path WITHOUT filtering by expiry
     * and WITHOUT mutating anything.
     *
     * getItemsFromPath() skips (and opportunistically unlinks) items whose
     * filemtime expiry is in the past. That is right for the clear/collect
     * flows, but wrong for the CP item browser: the listing (getItemsAtPath)
     * shows every on-disk item regardless of expiry, so search must do the same
     * — otherwise an item visible in the listing returns no search results, and
     * searching would silently delete expired-but-present files. This method
     * returns the same relative item paths as getItemsFromPath(), just without
     * the expiry filter/cleanup, so search stays consistent with browsing.
     *
     * @return string[]
     */
    public function getAllItemsFromPath(string $path): array
    {
        $base = rtrim($this->siteCachePath . '/' . ltrim($path, '/'), '/');

        try {
            $this->assertWithinCacheRoot($base);
        } catch (\InvalidArgumentException $exception) {
            return [];
        }

        if (!@is_dir($base)) {
            return [];
        }

        $iterator = $this->getIterator($base, RecursiveIteratorIterator::LEAVES_ONLY);

        $items = [];
        $path_len = strlen($base . '/');

        foreach ($iterator as $name => $file) {
            $name = str_replace('\\', '/', $name);
            $items[] = $this->formatItemPathName(substr($name, $path_len));
        }

        sort($items, SORT_STRING);

        return $items;
    }

    public function getItemsFromPath(string $path): array
    {
        $path = rtrim($this->siteCachePath . '/' . ltrim($path, '/'), '/');

        // Confine directory listing to the cache root so a traversal "path"
        // (e.g. ?path=../../../../etc) cannot enumerate arbitrary directories.
        try {
            $this->assertWithinCacheRoot($path);
        } catch (\InvalidArgumentException $exception) {
            return [];
        }

        if (!@is_dir($path)) {
            return [];
        }

        $iterator = $this->getIterator($path, RecursiveIteratorIterator::LEAVES_ONLY);

        $items = [];
        $expired = [];
        $path_len = strlen($path . '/');
        $now = time();

        foreach ($iterator as $name => $file) {
            $name = str_replace('\\', '/', $name);
            // We can quickly filter out expired items based on the filemtime
            // which is set to the expiry time or 10 years in the future for
            // non-expiring items.
            if ($now < $file->getMTime()) {
                // Strip the $path prefix from each filename
                $items[] = $this->formatItemPathName(substr($name, $path_len));
            } else {
                // Use this opportunity to clear expired items
                @unlink($name);
                $expired[] = $name;
            }
        }

        // Prune after iterating so directories aren't removed out from under the iterator.
        foreach ($expired as $name) {
            $this->pruneEmptyDirectories($name);
        }

        sort($items, SORT_STRING);

        return $items;
    }

    protected function formatItemPathName(string $name): string
    {
        return $name;
    }

    /**
     * Whether a bare filename is an internal artifact of the driver rather than
     * a user-visible cache item (used by single-level listing). The base driver
     * stores an item as a file named after the item, so nothing is internal.
     */
    protected function isInternalFile(string $name): bool
    {
        return false;
    }

    /**
     * Whether a directory at the given absolute path is itself a cache item
     * (rather than an intermediate folder of more items). The base driver stores
     * each item as a single file, so directories are always folders; drivers
     * that store an item AS a directory (e.g. static => dir/index.php) override
     * this so such directories list as viewable items, not navigable folders.
     */
    protected function isItemDirectory(string $absolutePath): bool
    {
        return false;
    }

    public function deleteItem(string $key): bool
    {
        if (CacheItem::isKeyRegex($key)) {
            return $this->deleteMatchingItems($key);
        }

        $file = $this->getFilename($key);

        try {
            if (!$file) { // https://boldminded.com/support/ticket/2926
                $this->getLogger()->error(sprintf(
                    'deleteItem() $key parameter value is blank.',
                    $file
                ));

                return false;
            }

            if (method_exists($this, 'getHtmlFilename')) {
                $htmlFile = $this->getHtmlFilename($key);

                if (
                    ee()->config->item('speedy_static_use_html_file') === 'yes'
                    && file_exists($htmlFile)
                ) {
                    @unlink($htmlFile);
                }
            }

            if (!file_exists($file)) {
                $this->getLogger()->error(sprintf(
                    'Cannot delete file "%s", it does not exist.',
                    $file
                ));
            }

            $deleted = !file_exists($file) || @unlink($file);

            if ($deleted) {
                $this->pruneEmptyDirectories($file);
            }

            return $deleted;
        } catch (\Exception $exception) {
            $this->getLogger()->error($exception->getMessage());
        }

        return false;
    }

    public function deleteMatchedItem(string $file): bool
    {
        try {
            if (!$file) {
                $this->getLogger()->error(sprintf(
                    'deleteItem() $key parameter value is blank.',
                    $file
                ));

                return false;
            }

            if (!file_exists($file)) {
                $this->getLogger()->error(sprintf(
                    'Cannot delete file "%s", it does not exist.',
                    $file
                ));
            }

            $deleted = !file_exists($file) || @unlink($file);

            if ($deleted) {
                $this->pruneEmptyDirectories($file);
            }

            return $deleted;
        } catch (\Exception $exception) {
            $this->getLogger()->error($exception->getMessage());
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

        try {
            $directory = new RecursiveDirectoryIterator($this->siteCachePath);
            $iterator = new RecursiveIteratorIterator($directory);
            $regex = new RegexIterator(
                $iterator, '/'. str_replace('/', '\/', $key) .'/i',
                RecursiveRegexIterator::ALL_MATCHES
            );

            foreach ($regex as $file => $matches) {
                $this->deleteMatchedItem($file);
            }
        } catch (Throwable $exception) {
            $this->getLogger()->error($exception->getMessage());

            return false;
        }

        return false;
    }

    public function deletePath(string $path): bool
    {
        $file = $this->getFilename($path);

        return $this->clearCachePath($file);
    }

    protected function getFilename(string $key, bool $validateKey = true): string
    {
        if ($validateKey) {
            CacheItem::validateKey($key);
        }

        // Strip prefix as it is included in cache path
        $prefix = 'speedy/' . $this->siteName . '/';
        if (strpos($key, $prefix) === 0) {
            $key = substr($key, strlen($prefix));
        }

        $path = $this->siteCachePath . '/' . $key;

        // Defense in depth: confirm the resolved path is still inside the cache
        // directory. validateKey() already rejects traversal payloads, but this
        // catches anything that slips through (symlinks, unexpected key sources)
        // before the path is read from or unlinked.
        $this->assertWithinCacheRoot($path);

        return $path;
    }

    /**
     * Confirm that a path resolves to a location inside the site cache
     * directory. Throws if it would escape the cache root.
     */
    protected function assertWithinCacheRoot(string $path): void
    {
        $root = $this->canonicalizePath($this->siteCachePath);
        $target = $this->canonicalizePath($path);

        if ($root === '' || strpos($target . '/', rtrim($root, '/') . '/') !== 0) {
            $this->getLogger()->error(sprintf(
                'Refusing to access path "%s" outside of cache root "%s".',
                $path,
                $this->siteCachePath
            ));

            throw new \InvalidArgumentException('[Speedy] The cache key resolves to a path outside the cache directory.');
        }
    }

    /**
     * Canonicalize a path, resolving "." and ".." segments lexically so the
     * check works for files that do not yet exist (writes). When the path (or
     * its nearest existing ancestor) exists, realpath() is preferred so symlinks
     * are also resolved.
     */
    protected function canonicalizePath(string $path): string
    {
        $real = @realpath($path);
        if ($real !== false) {
            return str_replace('\\', '/', $real);
        }

        // The path does not exist yet (e.g. a file about to be written), so
        // realpath() returns false. Walk up to the nearest existing ancestor,
        // realpath() that so symlinks are resolved consistently with the cache
        // root, then re-append the remaining segments resolving "." and "..".
        $path = str_replace('\\', '/', $path);
        $isAbsolute = isset($path[0]) && $path[0] === '/';
        $segments = explode('/', $path);
        $tail = [];

        while (!empty($segments)) {
            $candidate = ($isAbsolute ? '/' : '') . implode('/', $segments);
            $real = @realpath($candidate);

            if ($real !== false) {
                $base = explode('/', str_replace('\\', '/', $real));
                break;
            }

            array_unshift($tail, array_pop($segments));
            $base = [];
        }

        $resolved = $base;

        foreach ($tail as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($resolved);
                continue;
            }
            $resolved[] = $segment;
        }

        $joined = implode('/', array_filter($resolved, static function ($s) {
            return $s !== '';
        }));

        return ($isAbsolute ? '/' : '') . $joined;
    }

    public function refresh(): bool
    {
        $now = $this->getCreatedAt();
        $expired = [];

        foreach ($this->getIterator() as $name => $file) {
            if ($now >= $file->getMTime()) {
                @unlink($name);
                $expired[] = $name;
            }
        }

        // Prune after iterating so directories aren't removed out from under the iterator.
        foreach ($expired as $name) {
            $this->pruneEmptyDirectories($name);
        }

        return true;
    }

    public function writeFile(string $file, string $data, int $expiresAt = 3600): bool
    {
        // Attempt to create the path to the cache file.
        $path = dirname($file);

        if (!$this->ensureDirectory($path)) {
            $this->getLogger()->error(sprintf('Could not create cache path "%s".', $path));

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
        $tmpFile = $this->siteCachePath . '/' . uniqid('', true);

        $this->getLogger()->debug(sprintf('Writing data to temporary cache file name "%s".', $tmpFile));

        if (file_put_contents($tmpFile, $data) === false) {
            $this->getLogger()->error('Could not write data to temporary cache file.');

            return false;
        }

        $this->getLogger()->debug(sprintf('Renaming cache file to "%s".', $file));

        // Then use rename() (which is an atomic operation) to create the
        // cache data, or overwrite any existing file.
        if (@rename($tmpFile, $file)) {
            @chmod($file, 0644);
            @touch($file, $expiresAt);

            return true;
        }

        $this->getLogger()->error('Could not rename temporary cache file.');

        @unlink($tmpFile);

        return false;
    }

    /**
     * Race-safe directory creation.
     *
     * Returns true if the directory exists (or is created) and false only when
     * it genuinely could not be created.
     *
     * Why not just `@mkdir($path, ..., true)`? Under concurrent front-end
     * requests, two processes can try to create the same path at once: one wins
     * and the other's mkdir() fails with "File exists". The `@` suppresses that
     * at the PHP level, but EE's error handler reports warnings regardless of
     * the `@` operator, so the harmless race still surfaces as a logged
     * "E_WARNING: mkdir(): File exists". Checking is_dir() first means the
     * common "already exists" case never calls mkdir() at all, and a final
     * is_dir() check absorbs the rare genuine race without treating it as an
     * error.
     */
    protected function ensureDirectory(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }

        if (@mkdir($path, DIR_WRITE_MODE, true)) {
            return true;
        }

        // Lost a creation race (another process made it) — that's success.
        return is_dir($path);
    }

    /**
     * Delete now-empty directories left behind after removing a cache file,
     * walking up until a non-empty directory or the site cache path. rmdir()
     * refuses to remove non-empty directories, so the walk stops naturally
     * and is safe under concurrent writes.
     */
    protected function pruneEmptyDirectories(string $file): void
    {
        $boundary = rtrim(str_replace('\\', '/', $this->siteCachePath), '/');
        $dir = dirname(str_replace('\\', '/', $file));

        while ($dir !== $boundary && strpos($dir, $boundary . '/') === 0 && @rmdir($dir)) {
            $dir = dirname($dir);
        }
    }

    protected function clearCachePath(string $path): bool
    {
        if (!@is_dir($path)) {
            return true;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $name => $file) {
            if ($this->shouldUseQueue()) {
                $this->pushToQueue(
                    DeleteFileJob::class,
                    [
                        'type' => $file->isDir() ? 'dir' : 'file',
                        'name' => $name,
                    ]
                );

                continue;
            }

            if ($file->isDir()) {
                @rmdir($name);
            } else {
                @unlink($name);
            }
        }

        if (file_exists($path)) {
            if ($this->shouldUseQueue()) {
                $this->pushToQueue(
                    DeleteFileJob::class,
                    [
                        'type' => 'dir',
                        'name' => $path,
                    ]
                );
            } else {
                @rmdir($path);
            }
        }

        return true;
    }

    protected function getIterator(string $path = '', int $flags = 0): Iterator
    {
        if ($path === '') {
            $path = $this->siteCachePath;
        }

        $iterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
        $iterator = new SpeedyRecursiveFilterIterator($iterator);
        $iterator = new \RecursiveIteratorIterator($iterator, $flags);

        return $iterator;
    }

    public function getSiteCachePath(): string
    {
        return $this->siteCachePath;
    }

    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    public function getDocumentRootPath(): string
    {
        return !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : FCPATH;
    }
}
