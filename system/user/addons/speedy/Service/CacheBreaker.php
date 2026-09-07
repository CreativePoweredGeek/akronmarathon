<?php

namespace BoldMinded\Speedy\Service;

use BoldMinded\DataGrab\Dependency\Ramsey\Collection\AbstractArray;
use BoldMinded\Speedy\Library\Basee\Logger;
use BoldMinded\Speedy\Model\CacheBreaking;
use BoldMinded\Speedy\Queue\Jobs\BreakCacheJob;
use BoldMinded\Speedy\Queue\Jobs\BreakCategoryCacheJob;
use BoldMinded\Speedy\Queue\Jobs\BreakEntryCacheJob;
use BoldMinded\Speedy\Queue\Jobs\DeleteItemJob;
use BoldMinded\Speedy\Queue\Jobs\PurgeUrlJob;
use BoldMinded\Speedy\Queue\Jobs\RefreshUrlJob;
use BoldMinded\Speedy\Queue\QueueableTrait;
use BoldMinded\Speedy\Service\Drivers\AbstractDriver;
use BoldMinded\Speedy\Service\Drivers\DriverInterface;
use BoldMinded\Speedy\Service\Purgers\PurgerFactory;
use BoldMinded\Speedy\Service\Purgers\UrlCollector;
use ExpressionEngine\Model\Category\Category;
use ExpressionEngine\Model\Channel\ChannelEntry;
use ExpressionEngine\Service\Model\Collection;
use SebastianBergmann\CodeCoverage\Driver\Driver;

class CacheBreaker
{
    use QueueableTrait;

    /** @var Diagnostics  */
    private $diagnostics;

    /** @var \BoldMinded\Speedy\Service\DriverFactory */
    private $drivers;

    /** @var bool */
    private $enable_refresh;

    /** @var Logger */
    private $logger;

    /** @var string */
    private $prefix;

    /** @var int */
    private $refresh_interval;

    /** @var \BoldMinded\Speedy\Service\Request\Facade */
    private $request;

    private int $siteId = 1;

    /**
     * CacheBreaker constructor.
     */
    public function __construct()
    {
        $this->diagnostics = ee('speedy:Diagnostics');
        $this->request = ee('speedy:Request');
        $this->drivers = ee('speedy:DriverFactory');
        $this->logger = ee('speedy:Logger');

        $this->prefix = SPEEDY_CLASS_NAME . '/' . ee()->config->item('site_short_name') . '/';
        $this->refresh_interval = (int) ee()->config->item('speedy_refresh_interval');
        $this->enable_refresh = ee()->config->item('speedy_enable_refresh') !== 'no';
        $this->siteId = ee()->config->item('site_id');
    }

    public function setSiteId(int $siteId): CacheBreaker
    {
        $this->siteId = $siteId;

        return $this;
    }

    /**
     * Dispatches a cache break for all items.
     */
    public function breakCache()
    {
        // Attempt to break the cache asynchronously/
        if (ee()->config->item('speedy_break_async') === 'yes') {
            $params = [];

            $secret = ee()->config->item('speedy_secret');
            if ($secret !== false && trim($secret) !== '') {
                $params['secret'] = substr(hash('md5', $secret), 10);
            }

            // If successful, we're done; otherwise fall through to sync call.
            if ($this->request->getAction('Speedy', '_break_cache', $params)) {
                return;
            }
        }

        if ($this->shouldUseQueue()) {
            $this->pushToQueue(BreakCacheJob::class, $this->siteId);
            return;
        }

        $this->_breakCache();
    }

    /**
     * Dispatches a cache break for a single entry.
     */
    public function breakEntryCache(ChannelEntry $entry)
    {
        // Attempt to break the cache asynchronously
        if (ee()->config->item('speedy_break_async') === 'yes') {
            $params = ['ids' => $entry->getId()];

            $secret = ee()->config->item('speedy_secret');
            if ($secret !== false && trim($secret) !== '') {
                $params['secret'] = substr(hash('md5', $secret), 10);
            }

            // If successful, we're done; otherwise fall through to sync call.
            if ($this->request->getAction('Speedy', '_break_entry_cache', $params)) {
                return;
            }
        }

        if ($entry instanceof ChannelEntry) {
            $entry = new Collection([$entry]);
        }

        if ($this->shouldUseQueue()) {
            $this->pushToQueue(BreakEntryCacheJob::class, [
                'entryIds' => $entry->pluck('entry_id'),
                'siteId' => $this->siteId,
            ]);

            return;
        }

        $this->_breakEntryCache($entry);
    }

    /**
     * Dispatches a cache break for a single category.
     */
    public function breakCategoryCache(Category $category)
    {
        // Attempt to break the cache asynchronously
        if (ee()->config->item('speedy_break_async') === 'yes') {
            $params = ['ids' => $category->getId()];

            $secret = ee()->config->item('speedy_secret');
            if ($secret !== false && trim($secret) !== '') {
                $params['secret'] = substr(hash('md5', $secret), 10);
            }

            // If successful, we're done; otherwise fall through to sync call.
            if ($this->request->getAction('Speedy', '_break_category_cache', $params)) {
                return;
            }
        }

        if ($category instanceof Category) {
            $category = new Collection([$category]);
        }

        if ($this->shouldUseQueue()) {
            $this->pushToQueue(BreakCategoryCacheJob::class, [
                'categoryId' => $category->pluck('cat_id'),
                'siteId' => $this->siteId,
            ]);

            return;
        }

        $this->_breakCategoryCache($category);
    }

    /**
     * Actually break the cache.
     *
     * This may be called from the action handler asynchronously, or from the
     * public breakEntryCache() method.
     */
    public function _breakCache(
        array $siteIds = []
    ): bool {
        // This is a potentially long-running operation, disable the time limit so PHP won't time out.
        set_time_limit(0);

        $clear_tags = [];
        $clear_items = [];
        $refresh_items = [];

        $can_refresh = $this->canRefreshItems() && $this->enable_refresh;

        if (empty($siteIds)) {
            $siteIds = [
                $this->siteId,
            ];
        }

        foreach ($siteIds as $siteId) {
            // Collect all tags and tagged items.
            foreach ($this->collectAllTags($siteId) as $tag) {
                $clear_tags[] = $tag->tag;
                $clear_items[] = $tag->key;
                if ($can_refresh) {
                    $refresh_items[] = $tag->key;
                }
            }

            // Collect all items from all drivers.
            foreach ($this->collectItemsFromPath('', $siteId) as $item) {
                $clear_items[] = $item;
                if ($can_refresh) {
                    $refresh_items[] = $item;
                }
            }

            $clear_tags = array_unique($clear_tags);
            $clear_items = array_unique($clear_items);
            $refresh_items = array_unique($refresh_items);

            try {
                if (count($clear_tags)) {
                    $this->clearTags($clear_tags, $siteId);
                }

                if (count($clear_items)) {
                    $this->clearItems($clear_items, $siteId);
                }

                if (count($refresh_items)) {
                    $this->refreshItems($refresh_items);
                }

                if (ee()->extensions->active_hook('speedy_break_cache')) {
                    ee()->extensions->call('speedy_break_cache', [
                        'clearTags' => $clear_tags,
                        'clearItems' => $clear_items,
                        'refreshItems' => $refresh_items,
                    ]);
                }
            } catch (\Exception $exception) {
                error_log($exception->getMessage());

                return false;
            }
        }

        return true;
    }

    public function getAllSettings(): Collection
    {
        return ee('Model')->get('speedy:CacheBreaking')
            ->filter('entity_type', 'channel')
            ->all();
    }

    /**
     * Actually break the cache.
     *
     * This may be called from the action handler asynchronously, or from the
     * public breakEntryCache() method.
     */
    public function _breakEntryCache($entries)
    {
        $entries = $this->normalizeEntriesCollection($entries);

        if (count($entries) === 0) {
            return;
        }

        // This is a potentially long-running operation, disable the time limit
        // so PHP won't time out.
        set_time_limit(0);

        $allSettings = $this->getAllSettings();

        if ($allSettings->count() === 0) {
            return;
        }

        $clear_tags = [];
        $clear_items = [];
        $refresh_items = [];

        $can_refresh = $this->canRefreshItems() && $this->enable_refresh;

        foreach ($allSettings as $settings) {
            // Select only the $entries in the channel affected by this $settings.
            $entriesCollection = $this->collectRelevantEntries($entries, $settings);
            if (count($entriesCollection) === 0) {
                continue;
            }

            // Parse the tag/item templates configured for this $settings.
            $tags = $this->parseEntrySettingVariables($entriesCollection, $settings->tags);
            $items = $this->parseEntrySettingVariables($entriesCollection, $settings->items);

            // Collect the tags to be cleared
            foreach ($tags as $tag) {
                $clear_tags[] = $tag;
            }

            // Collect the items associated with the tags to be cleared
            foreach ($this->collectTaggedItems($tags) as $child) {
                $clear_items[] = $child;
                if ($can_refresh && $settings->refresh) {
                    $refresh_items[] = $child;
                }
            }

            // It is important to extrapolate paths instead of using the
            // CacheDriver::deletePath() method as we need to know which items
            // are being cleared in case we need to refresh them.
            // @todo: Maybe an earlier check can be introduced to skip this and use deletePath() later
            foreach ($items as $index => $item) {
                if ($this->isPathItem($item)) {
                    foreach ($this->collectItemsFromPath($item) as $child) {
                        $clear_items[] = $child;
                        if ($can_refresh && $settings->refresh) {
                            $refresh_items[] = $child;
                        }
                    }
                } else {
                    $clear_items[] = $this->prefixItem($item);
                    if ($can_refresh && $settings->refresh) {
                        $refresh_items[] = $this->prefixItem($item);
                    }
                }
            }
        }

        $clear_tags = array_unique($clear_tags);
        $clear_items = array_unique($clear_items);
        $refresh_items = array_unique($refresh_items);

        try {
            if (count($clear_tags)) {
                $this->clearTags($clear_tags);
            }

            if (count($clear_items)) {
                $this->clearItems($clear_items);
            }

            if (count($refresh_items)) {
                $this->refreshItems($refresh_items);
            }

            if (ee()->extensions->active_hook('speedy_break_entry_cache')) {
                ee()->extensions->call('speedy_break_entry_cache', [
                    'clearTags' => $clear_tags,
                    'clearItems' => $clear_items,
                    'refreshItems' => $refresh_items,
                ]);
            }
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }
    }

    /**
     * Actually break the cache.
     *
     * This may be called from the action handler asynchronously, or from the
     * public breakEntryCache() method.
     *
     * @param mixed $categories
     */
    public function _breakCategoryCache($categories)
    {
        $categories = $this->normalizeCategoriesCollection($categories);

        if (count($categories) === 0) {
            return;
        }

        // This is a potentially long-running operation, disable the time limit
        // so PHP won't time out.
        set_time_limit(0);

        /** @var \BoldMinded\Speedy\Model\CacheBreaking[] $allSettings */
        $allSettings = ee('Model')->get('speedy:CacheBreaking')
            ->filter('entity_type', 'category_group')
            ->all();

        if (count($allSettings) === 0) {
            return;
        }

        $clear_tags = [];
        $clear_items = [];
        $refresh_items = [];

        $can_refresh = $this->canRefreshItems() && $this->enable_refresh;

        foreach ($allSettings as $settings) {
            // Select only the $entries in the channel affected by this $settings.
            $categoriesCollection = $this->collectRelevantCategories($settings, $categories);
            if (count($categoriesCollection) === 0) {
                continue;
            }

            // Parse the tag/item templates configured for this $settings.
            $tags = $this->parseCategorySettingVariables($settings->tags, $categoriesCollection);
            $items = $this->parseCategorySettingVariables($settings->items, $categoriesCollection);

            // Collect the tags to be cleared
            foreach ($tags as $tag) {
                $clear_tags[] = $tag;
            }

            // Collect the items associated with the tags to be cleared
            foreach ($this->collectTaggedItems($tags) as $child) {
                $clear_items[] = $child;
                if ($can_refresh && $settings->refresh) {
                    $refresh_items[] = $child;
                }
            }

            // It is important to extrapolate paths instead of using the
            // CacheDriver::deletePath() method as we need to know which items
            // are being cleared in case we need to refresh them.
            // @todo: Maybe an earlier check can be introduced to skip this and use deletePath() later
            foreach ($items as $index => $item) {
                if ($this->isPathItem($item)) {
                    foreach ($this->collectItemsFromPath($item) as $child) {
                        $clear_items[] = $child;
                        if ($can_refresh && $settings->refresh) {
                            $refresh_items[] = $child;
                        }
                    }
                } else {
                    $clear_items[] = $this->prefixItem($item);
                    if ($can_refresh && $settings->refresh) {
                        $refresh_items[] = $this->prefixItem($item);
                    }
                }
            }
        }

        $clear_tags = array_unique($clear_tags);
        $clear_items = array_unique($clear_items);
        $refresh_items = array_unique($refresh_items);

        try {
            if (count($clear_tags)) {
                $this->clearTags($clear_tags);
            }

            if (count($clear_items)) {
                $this->clearItems($clear_items);
            }

            if (count($refresh_items)) {
                $this->refreshItems($refresh_items);
            }

            if (ee()->extensions->active_hook('speedy_break_category_cache')) {
                ee()->extensions->call('speedy_break_category_cache', [
                    'clearTags' => $clear_tags,
                    'clearItems' => $clear_items,
                    'refreshItems' => $refresh_items,
                ]);
            }
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }
    }

    /**
     * Refresh the items found at the following urls.
     *
     * This is registered as a shutdown function and should not be called directly.
     */
    public function _refreshUrls(
        array $urls
    ): void
    {
        if (count($urls) === 0 || !$this->canRefreshItems()) {
            return;
        }

        foreach ($urls as $url) {
            if ($this->shouldUseQueue()) {
                $this->pushToQueue(RefreshUrlJob::class, [
                    'url' => $url,
                    'interval' => $this->refresh_interval ?? 0,
                ]);
                continue;
            }

            $this->request->get($url);

            // Delay item refresh so we don't stampede the server.
            if ($this->refresh_interval) {
                @sleep($this->refresh_interval);
            }
        }
    }

    public function clearTags(
        array $tags,
        int|null $siteId = null,
    ): void
    {
        if (count($tags) === 0) {
            return;
        }

        if ($siteId === null) {
            $siteId = $this->siteId;
        }

        $regexTags = array_filter($tags, static fn ($tag) => strpos($tag, '^') !== false);
        $basicTags = array_filter($tags, static fn ($tag) => strpos($tag, '^') === false);

        if (count($basicTags) > 0) {
            $tagResult = ee('Model')->get('speedy:Tag')
                ->filter('tag', 'IN', $basicTags)
                ->filter('site_id', $siteId)
                ->all();

            $this->clearTagsFromCollection($tagResult, $siteId);

            ee('Model')->get('speedy:Tag')
                ->filter('tag', 'IN', $basicTags)
                ->filter('site_id', $siteId)
                ->delete();
        }

        if (count($regexTags) > 0) {
            foreach ($regexTags as $regexTag) {
                $tagResult = ee('Model')->get('speedy:Tag')
                    ->filter('tag', 'REGEXP', $regexTag)
                    ->filter('site_id', $siteId)
                    ->all();

                $this->clearTagsFromCollection($tagResult, $siteId);

                foreach ($tagResult as $tag) {
                    $tag->delete();
                }
            }
        }
    }

    private function clearTagsFromCollection(
        Collection $tagCollection,
        int|null $siteId = null
    ): void
    {
        if ($siteId === null) {
            $siteId = $this->siteId;
        }

        if ($this->shouldUseQueue()) {
            foreach ($tagCollection as $tagRow) {
                // Remove the prefix. Each cache driver will re-add it, so we don't want to double up.
                $key = $this->stripPrefix($tagRow->key);

                $this->pushToQueue(DeleteItemJob::class, [
                    'key' => $key,
                    'siteId' => $siteId,
                ]);
            }

            return;
        }

        $keys = [];

        /** @var DriverInterface $driver */
        foreach ($this->drivers->getDrivers() as $driver) {
            if ($driver->isSupported() && $driver->isConfigured()) {
                foreach ($tagCollection as $tagRow) {
                    // Remove the prefix. Each cache driver will re-add it, so we don't want to double up.
                    $key = $this->stripPrefix($tagRow->key);
                    $keys[$key] = true;

                    $this->logger->info(
                        sprintf(
                            'Attempting to delete cache item %s',
                            $key
                        )
                    );

                    $result = $driver->deleteItem($key);

                    $this->logger->info(
                        sprintf(
                            '%s %s',
                            $key, $result === true ? 'deleted' : 'not deleted'
                        )
                    );

                    $this->diagnostics->clearDiagnostics($key);
                }
            }
        }

        $purger = (new PurgerFactory())->create();
        $urlKeys = array_keys($keys);
        $urls = (new UrlCollector($urlKeys, $siteId))->collect();

        if (count($urls) > 0) {
            $purger->purgeUrls($urls);
        }

        // The cache items are gone, so remove the URL records that pointed at them,
        // otherwise the same URLs are re-purged by every later break of these keys.
        if (count($urlKeys) > 0) {
            ee('Model')->get('speedy:Url')
                ->filter('key', 'IN', $urlKeys)
                ->filter('site_id', $siteId)
                ->delete();
        }
    }

    private function getSiteName(
        int $siteId
    ): string
    {
        $requestedSite = ee('Model')
            ->get('Site')
            ->filter('site_id', $siteId)
            ->first();

        return $requestedSite->site_name ?? 'default_site';
    }

    public function clearItems(
        array $items,
        int|null $siteId = null
    ): void
    {
        if ($siteId === null) {
            $siteId = $this->siteId;
        }

        $siteName = $this->getSiteName($siteId);

        /** @var DriverInterface $driver */
        foreach ($this->drivers->getDrivers() as $driver) {
            assert($driver instanceof DriverInterface);

            if ($driver->isSupported() && $driver->isConfigured()) {
                $driver->setSiteName($siteName);

                foreach ($items as $item) {
                    // Remove the prefix. Each cache driver will re-add it, so we don't want to double up.
                    $item = $this->stripPrefix($item);

                    $this->logger->info(
                        sprintf(
                            'Attempting to clear item %s with %s',
                            $item,
                            $driver::class
                        )
                    );

                    $result = $driver->deleteItem($item);

                    $this->logger->info(
                        sprintf(
                            '%s %s',
                            $item,
                            $result === true ? 'cleared' : 'not cleared'
                        )
                    );

                    $this->diagnostics->clearDiagnostics($item);
                }
            }
        }

        // Try purging from reverse proxy if defined. Defaults to Dummy purger and does nothing.
        // $items can contain both prefixed (tag and template derived) and unprefixed
        // (path derived) keys, but Url rows are stored unprefixed and Tag rows prefixed.
        // Normalize for each table so neither the purge lookup nor the row cleanup
        // silently misses items in the other format.
        $purger = (new PurgerFactory())->create();
        $urlKeys = UrlCollector::normalizeKeys($items);
        $urls = (new UrlCollector($urlKeys, $siteId))->collect();

        if ($this->shouldUseQueue()) {
            foreach ($urls as $url) {
                $this->pushToQueue(PurgeUrlJob::class, [
                    'url' => $url,
                ]);
            }
        } else {
            $purger->purgeUrls($urls);
        }

        $tagKeys = array_unique(array_map(
            fn (string $item) => strpos($item, SPEEDY_CLASS_NAME . '/') === 0
                ? $item
                : SPEEDY_CLASS_NAME . '/' . $siteName . '/' . $item,
            $items
        ));

        ee('Model')->get('speedy:Url')
            ->filter('key', 'IN', $urlKeys)
            ->filter('site_id', $siteId)
            ->delete();

        ee('Model')->get('speedy:Tag')
            ->filter('key', 'IN', $tagKeys)
            ->filter('site_id', $siteId)
            ->delete();
    }

    /**
     * @param array $items
     */
    public function refreshItems(
        array $items
    ): void
    {
        $site_url = rtrim(ee()->config->item('site_url'), '/');

        $clear_urls = [];

        foreach ($items as $item) {
            // Strip local/static prefix from the item.
            // The url of global or other items can not be determined so just skip them.
            if ($this->isLocalItem($item)) {
                $item = substr($item, strlen($this->prefixItem('local/')));
            } elseif ($this->isStaticItem($item)) {
                $item = substr($item, strlen($this->prefixItem('static/')));
            } else {
                continue;
            }

            $this->diagnostics->clearDiagnostics($item);

            $clear_urls[] = $site_url . '/' . $item;
        }

        $this->logger->info(
            sprintf(
                'Refreshing the following URLs %s',
                json_encode($clear_urls, JSON_PRETTY_PRINT)
            )
        );

        $this->_refreshUrls(array_unique($clear_urls));
    }

    /**
     * @param mixed $entries
     * @return Collection
     */
    private function normalizeEntriesCollection($entries): Collection
    {
        $entriesCollection = new Collection();

        if ($entries instanceof ChannelEntry) {
            $entriesCollection->add($entries);

            return $entriesCollection;
        }

        if ($entries instanceof Collection) {
            foreach ($entries as $entry) {
                if ($entry instanceof ChannelEntry) {
                    $entriesCollection->add($entry);

                }
            }

            return $entriesCollection;
        }

        return $entriesCollection;
    }

    /**
     * @param mixed $categories
     * @return Category[]
     */
    private function normalizeCategoriesCollection($categories)
    {
        if ($categories instanceof Category) {
            return [$categories];
        }

        if ($categories instanceof Collection) {
            $categoriesCollection = [];

            foreach ($categories as $category) {
                if ($category instanceof Category) {
                    $categoriesCollection[] = $category;
                }
            }

            return $categoriesCollection;
        }

        return [];
    }

    /**
     * @param ChannelEntry[] $entries
     * @param CacheBreaking $settings
     * @return Collection
     */
    public function collectRelevantEntries(
        Collection $entries,
        CacheBreaking $settings
    ) {
        if ($settings->entity_id === 0) {
            return $entries;
        }

        $entriesCollection = [];

        foreach ($entries as $entry) {
            // If cache clearing is set to 1 or more specific statuses, and the current entry
            // is not of that status, then skip adding the entry to the collection to clear its cache.
            if (
                is_array($settings->statuses) &&
                !empty($settings->statuses) &&
                !in_array($entry->status_id, $settings->statuses)
            ) {
                continue;
            }

            $assignedCategories = $entry->Categories->pluck('cat_id') ?? [];
            $settingsCategories = $settings->categories ?? [];

            if (
                is_array($settings->categories) &&
                !empty($settings->categories) &&
                empty(array_intersect($assignedCategories, $settingsCategories))
            ) {
                continue;
            }

            if ($settings->entity_id === 0 || $settings->entity_id === (int) $entry->channel_id) {
                $entriesCollection[] = $entry;
            }
        }

        return new Collection($entriesCollection);
    }

    /**
     * @param CacheBreaking $settings
     * @param Category[] $categories
     * @return Category[]
     */
    private function collectRelevantCategories($settings, $categories)
    {
        if ($settings->entity_id === 0) {
            return $categories;
        }

        $categoriesCollection = [];

        foreach ($categories as $category) {
            if ($settings->entity_id === 0 || $settings->entity_id === (int) $category->CategoryGroup->group_id) {
                $categoriesCollection[] = $category;
            }
        }

        return $categoriesCollection;
    }

    /**
     * @return \BoldMinded\Speedy\Model\Tag[]
     */
    private function collectAllTags(
        int|null $siteId = null
    ){
        if ($siteId) {
            return ee('Model')
                ->get('speedy:Tag')
                ->filter('site_id', $siteId)
                ->all();
        }

        return ee('Model')
            ->get('speedy:Tag')
            ->all();
    }

    public function collectTaggedItems(
        array $tags,
        int|null $siteId = null
    ): array
    {
        if (empty($tags)) {
            return [];
        }

        if (!$siteId) {
            $siteId = $this->siteId;
        }

        $tagged_items = ee('Model')->get('speedy:Tag')
            ->filter('key', 'LIKE', $this->prefix . '%')
            ->filter('tag', 'IN', $tags)
            ->filter('site_id', $siteId)
            ->all();

        $keys = [];
        foreach ($tagged_items as $item) {
            $keys[] = $item->key;
        }

        return array_unique($keys);
    }

    public function collectItemsFromPath(
        string $basePath = '',
        int|null $siteId = null
    ): array
    {
        if (!$siteId) {
            $siteId = $this->siteId;
        }

        $items = [];
        $siteName = $this->getSiteName($siteId);

        /** @var DriverInterface $driver */
        foreach ($this->drivers->getDrivers() as $driver) {
            assert($driver instanceof DriverInterface);

            if ($driver->isSupported() && $driver->isConfigured()) {

                $path_items = $driver
                    ->setSiteName($siteName)
                    ->getItemsFromPath($basePath);

                foreach ($path_items as $item) {
                    $items[] = $basePath . $item;
                }
            }
        }

        return $items;
    }

    /**
     * Recursively decode HTML entities so values that were previously encoded
     * (or repeatedly encoded) by the CP grid still parse correctly. Encoding can
     * compound across saves (&#039; -> &amp;#039; -> ...), so decode until stable.
     */
    private function decodeEntities(string $string): string
    {
        do {
            $decoded = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
            if ($decoded === $string) {
                break;
            }
            $string = $decoded;
        } while (true);

        return $string;
    }

    private function parseEntrySettingVariables(
        Collection $entries,
        array $strings = [],
    ): array
    {
        $parsed_strings = [];
        $date_variables = 'entry_date|edit_date';

        foreach ($strings as $original) {
            if (trim($original) === '') {
                continue;
            }

            foreach ($entries as $entry) {
                $string = $original;

                // Parse date variables. The format parameter may be wrapped in either single
                // or double quotes, and may contain HTML entities if the value was previously
                // encoded by the CP grid (e.g. format=&#039;%Y&#039;), so decode it first.
                $string = $this->decodeEntities($string);

                if (preg_match_all('~\{(' . $date_variables . ')\s+format=([\'"])(.*?)\2\}~i', $string, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $timestamp = $entry->{$match[1]};
                        if ($timestamp) {
                            $timestamp = ee()->localize->format_date($match[3], $timestamp);
                        }

                        $string = str_replace($match[0], $timestamp, $string);
                    }
                }

                $replace = $this->buildEntrySettingVariables($entry);
                $parsed_strings[] = str_replace(array_keys($replace), array_values($replace), $string);
            }
        }

        $unique = array_unique($parsed_strings);
        $newStrings = [];

        foreach ($unique as $string) {
            // Should be a safe assumption that if a pipe is present its intended to be an array of values.
            // EE uses the pipe character for OR conditions in many places so this seems reasonable.
            if (strpos($string, '|') !== false) {
                $newStrings = array_merge(
                    $newStrings,
                    array_map('trim', explode('|', $string))
                );
            } else {
                $newStrings[] = trim($string);
            }
        }

        return $newStrings;
    }

    private function buildEntrySettingVariables(ChannelEntry $entry): array
    {
        // @todo: This would be a great place for an extension hook

        $entryDate = $entry->entry_date;
        if ($entryDate instanceof \DateTime) {
            $entryDate = $entryDate->getTimestamp();
        }

        $editDate = $entry->edit_date;
        if ($editDate instanceof \DateTime) {
            $editDate = $editDate->getTimestamp();
        }

        $variables = [
            '{author_id}'       => $entry->author_id ?? '',
            '{author_username}' => $entry->Author?->username ?: '',
            '{channel_id}'      => $entry->channel_id ?? '',
            '{channel_name}'    => $entry->Channel->channel_name ?: '',
            '{channel_title}'   => $entry->Channel->channel_title ?: '',
            '{edit_date}'       => $editDate,
            '{entry_id}'        => $entry->entry_id ?? '',
            '{entry_date}'      => $entryDate,
            '{page_uri}'        => $entry->getPageURI() ? trim($entry->getPageURI(), '/') : '',
            '{title}'           => $entry->title ?? '',
            '{url_title}'       => $entry->url_title ?? '',
            '{username}'        => $entry->Author?->username ?: '',
            '{cat_url_title}'   => '',
            '{cat_url_title_1}' => '',
            '{cat_url_title_2}' => '',
            '{cat_url_title_3}' => '',
            '{cat_url_title_4}' => '',
            '{cat_url_title_5}' => '',
            '{cat_url_title_6}' => '',
            '{cat_url_title 7}' => '',
            '{cat_url_title_8}' => '',
            '{cat_url_title_9}' => '',
            '{cat_url_title_any}' => '',
        ];

        if ($entry->Categories !== null) {
            $categoryUrlTitles = $entry->Categories->pluck('cat_url_title');

            if (count($categoryUrlTitles) === 1) {
                // If only 1 category is allowed to be assigned to an entry by the category
                // group rules, or if it just happens to have 1 of many available.
                $variables['{cat_url_title}'] = $categoryUrlTitles[0];
            }

            // If this is used, multiple cache breaking rules will need to be defined.
            // E.g. if 3 categories are assigned, then 3 rules, {cat_url_title_1},
            // {cat_url_title_2}, and {cat_url_title_3} will be needed.
            foreach ($categoryUrlTitles as $index => $categoryUrlTitle) {
                $variables['{cat_url_title_' . $index + 1 .'}'] = $categoryUrlTitle;
            }

            // These will be treated as an array by parseEntrySettingVariables()
            $variables['{cat_url_title_any}'] = implode('|', $categoryUrlTitles);
        }

        return $variables;
    }

    private function parseCategorySettingVariables(
        array $strings = [],
        array $categories = [],
    ): array
    {
        $parsed_strings = [];

        foreach ($strings as $original) {
            if (trim($original) === '') {
                continue;
            }

            foreach ($categories as $category) {
                $string = $original;

                $replace = $this->buildCategorySettingVariables($category);
                $parsed_strings[] = str_replace(array_keys($replace), array_values($replace), $string);
            }
        }

        return array_unique($parsed_strings);
    }

    private function buildCategorySettingVariables(Category $category): array
    {
        // @todo: This would be a great place for an extension hook
        $categoryGroupName = $category->CategoryGroup->group_name ?: '';
        $categoryGroupUrlTitle = (string) ee('Format')->make('Text', $categoryGroupName)->urlSlug();

        return [
            '{category_group_id}'           => $category->CategoryGroup->group_id ?: '',
            '{category_group_name}'         => $categoryGroupName,
            '{category_group_url_title}'    => $categoryGroupUrlTitle,
            '{category_id}'                 => $category->cat_id ?? '',
            '{category_name}'               => $category->cat_name ?: '',
            '{category_url_title}'          => $category->cat_url_title ?? '',
        ];
    }

    private function prefixItem(string $item): string
    {
        return $this->prefix . $item;
    }

    /**
     * Remove a leading speedy/{site}/ prefix. Anchored so a prefix-like string
     * elsewhere in the key is left alone, and site-agnostic because tag keys
     * are saved with the short name of the site that rendered the page.
     */
    private function stripPrefix(string $item): string
    {
        return preg_replace('#^' . SPEEDY_CLASS_NAME . '/[^/]+/#', '', $item);
    }

    private function isPathItem(string $item): bool
    {
        return substr($item, -1) === '/';
    }

    private function isGlobalItem(string $item): bool
    {
        return strpos($item, $this->prefixItem('global/')) === 0;
    }

    private function isLocalItem(string $item): bool
    {
        return strpos($item, $this->prefixItem('local/')) === 0;
    }

    private function isStaticItem(string $item): bool
    {
        return strpos($item, $this->prefixItem('static/')) === 0;
    }

    private function canRefreshItems(): bool
    {
        return $this->request->isSupported();
    }
}
