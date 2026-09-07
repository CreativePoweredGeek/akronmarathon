<?php

use BoldMinded\Speedy\Library\Basee\App;
use BoldMinded\Speedy\Service\CacheItem;
use BoldMinded\Speedy\Service\Drivers\DriverInterface;
use BoldMinded\Speedy\Service\Drivers\DummyDriver;
use BoldMinded\Speedy\Service\Drivers\RedisDriver;
use BoldMinded\Speedy\Service\Drivers\StaticDriver;
use BoldMinded\Speedy\Service\StaticCacheHelper;
use BoldMinded\Speedy\Service\ShutdownProcess;
use BoldMinded\Speedy\Service\SpeedyUtil;

class Speedy
{
    /** @var bool */
    private static $is_caching = false;

    /** @var string[][] */
    private static $cached_items = [];

    /** @var array */
    private static $static_cache;

    /** @var bool */
    private static $is_bot;

    /** @var \BoldMinded\Speedy\Service\ShutdownProcess */
    private static $shutdown;

    /** @var \BoldMinded\Speedy\Service\Logger\Logger */
    private $logger;

    /** @var \BoldMinded\Speedy\Service\DriverFactory */
    private $drivers;

    /** @var \BoldMinded\Speedy\Service\EscapeRepository */
    private $escapes;

    /** @var \BoldMinded\Speedy\Library\Basee\Setting */
    private $setting;

    /** @var int */
    private $default_ttl;

    /** @var string */
    private $default_driver;

    /** @var int */
    private $dynamic_ttl;

    /** @var string */
    private $dynamic_tags;

    private int $siteId = 1;

    /**
     * Speedy constructor.
     */
    public function __construct()
    {
        $this->logger = ee('speedy:Logger');
        $this->drivers = ee('speedy:DriverFactory');
        $this->escapes = ee('speedy:EscapeRepository');
        $this->setting = ee('speedy:Setting');

        $this->default_ttl = ee()->config->item('speedy_ttl');
        if ($this->default_ttl === false || trim($this->default_ttl) === '') {
            $this->default_ttl = 3600;
        }
        $this->default_driver = ee()->config->item('speedy_driver') ?: 'dummy';
        $this->siteId = ee()->config->item('site_id');
    }

    /**
     * Reset module status.
     *
     * This method is called from the test suite to clear any data cached
     * between calls to this module's tags.
     *
     * @internal
     * @param bool $isCaching
     */
    public static function _reset($isCaching = false)
    {
        if (self::$shutdown) {
            self::$shutdown->unregister();
        }

        self::$is_caching = $isCaching;
        self::$cached_items = [];
        self::$static_cache = null;
        self::$is_bot = null;
        self::$shutdown = null;
    }

    /**
     * This method is called from the test suite to clear the the registered
     * shutdown callback.
     *
     * @return \BoldMinded\Speedy\Service\ShutdownProcess
     */
    public static function _getShutdown()
    {
        return self::$shutdown;
    }

    /**
     * {exp:speedy:fragment}
     *
     * @return string
     */
    public function fragment()
    {
        $tagdata = $this->getTagdata();
        $key = $this->fetchKeyParam();

        $fragmentDriverName = ee()->config->item('speedy_driver_fragment') ?: '';
        $isStaticEnabled = ee()->config->item('speedy_static_enabled') === 'yes';
        $disallowEmptyCache = ee()->TMPL->fetch_param('allow_empty_cache') !== 'yes';

        if (
            $key === false ||
            !$this->shouldCacheFragment() ||
            // Static and Fragment caching can not go together.
            // If static cache is enabled, but a fragment tag is on a page, and no exp:speedy:static tag is
            // on the same page, then it will try to cache fragments into separate files as if they were
            // independent static pages, and upon output, really weird things happen.
            ($isStaticEnabled && $fragmentDriverName === '') ||
            // Do we allow caching of empty content? If not then return empty content without caching it.
            ($tagdata === '' && $disallowEmptyCache)
        ) {
            return $tagdata;
        }

        // If static is enabled there may be cases where a site should serve static content to non-logged
        // in users, and logged-in users get some fragments cached. Static would trump everything, but this
        // scenario might create some duplicate cache items. E.g. a page is cached statically but the fragments
        // are still also cached.
        if ($isStaticEnabled && $fragmentDriverName !== '') {
            $driver = $this->drivers->getDriver($fragmentDriverName, $this->logger);
        } else {
            $driver = $this->getCacheDriver();
        }

        $cacheItem = $driver->getItem($key);

        if ($cacheItem->isHit()) {
            $this->logger->info(sprintf('The specified key "%s" was found.', $key));
        } else {
            ee('speedy:Diagnostics')
                ->setDriver($driver->getName())
                ->start();

            $this->logger->info(sprintf('The specified key "%s" was not found.', $key));

            $cacheItem = new CacheItem();
            $cacheItem
                ->setKey($key)
                ->setValue($this->processTagdata($tagdata))
                ->setTTL($this->fetchTTLParam());

            // Call the `speedy_pre_save` hook
            if (ee()->extensions->active_hook('speedy_pre_save')) {
                $cacheItem = ee()->extensions->call('speedy_pre_save', $cacheItem, 'fragment');

                if (ee()->extensions->end_script === true) {
                    return $tagdata;
                }
            }

            $this->logger->info(sprintf('Storing processed tagdata for key "%s".', $key));
            $driver->save($cacheItem);

            // Store a reference to all items cached this session so if there
            // is an error later, they can be cleared at shutdown.
            self::$cached_items[$driver->getName()][] = $key;
            $this->registerShutdown();

            // Save the tags for this item
            if (!$driver instanceof DummyDriver) {
                $this->saveTags($key, $this->fetchTagsParam());
                $this->saveUrl($key);
            }
        }

        return $this->processReturn($cacheItem->getValue());
    }

    /**
     * {exp:speedy:static}
     */
    public function static()
    {
        if ($this->shouldCacheStaticFile()) {
            $driver = ee()->config->item('speedy_driver');

            if (!in_array($driver, ['static', 'redis'])) {
                $driver = 'static';
            }

            self::$static_cache = [
                'url'  => $this->fetchCacheUrl(),
                'ttl'  => $this->fetchTTLParam(),
                'tags' => $this->fetchTagsParam(),
                'driver' => $driver,
            ];

            ee('speedy:Diagnostics')
                ->setDriver($driver)
                ->start();

            $this->registerShutdown();
        }
    }

    /**
     * {exp:speedy:escape}
     *
     * @return string
     */
    public function escape()
    {
        $tag = null;
        $tagdata = $this->getTagdata();
        $tag_parts = ee()->TMPL->tagparts;

        if (count($tag_parts) > 2) {
            $tag = $tag_parts[2];
        }

        if (trim($tagdata) === '') {
            return $tagdata;
        }

        if (!self::$is_caching) {
            return $this->parseVars($tagdata);
        }

        return $this->escapes->create($tagdata, $tag);
    }

    /**
     * {exp:speedy:clear}
     *
     * @return void
     */
    public function clear()
    {
        if ($this->verifyActionSecret()) {
            $tags = explode('|', $this->fetchTagsParam());
            $items = explode('|', $this->fetchItemsParam());

            /** @var \BoldMinded\Speedy\Service\CacheBreaker $breaker */
            $breaker = ee('speedy:CacheBreaker');

            if (!empty($tags)) {
                $breaker->clearTags($tags);
            }

            if (!empty($items)) {
                $breaker->clearItems($items);
            }
        }
    }

    /** @todo this isn't working, and not sure why. Move this to core_boot and add a config option to enable it? */
    public function http_headers()
    {
        $createdAt = ee()->TMPL->fetch_param('created_at') ?: time();
        $expiresAt = ee()->TMPL->fetch_param('expires_at') ?: 0;

        $driver = $this->getCacheDriver();
        $purgerSettings = json_decode($this->setting->get('settings_purger'), true);

        $headers = $driver->buildCacheHeaders($createdAt, $expiresAt, $purgerSettings);

        foreach ($headers as $name => $value) {
            ee('Response')->setHeader($name, $value);
        }

        return false;
    }

    /**
     * {exp:speedy:not_available}
     *
     * This is a placeholder template tag used when migrating from CE Cache to Speedy.
     * CE has some extra tags that Speedy does not, but if {exp:ce_cache:whatever} is
     * left in the template it will throw an error when parsing.
     *
     * @return string
     */
    public function not_available()
    {
        return '';
    }

    /**
     * ACTION
     */
    public function _break_cache()
    {
        // Prevent access to this from the template parser.
        if (isset(ee()->TMPL)) {
            return;
        }

        if ($this->verifyActionSecret()) {
            /** @var \BoldMinded\Speedy\Service\CacheBreaker $breaker */
            $breaker = ee('speedy:CacheBreaker');
            $breaker->_breakCache();
        }
    }

    /**
     * ACTION
     */
    public function _break_entry_cache()
    {
        // Prevent access to this from the template parser.
        if (isset(ee()->TMPL)) {
            return;
        }

        if ($this->verifyActionSecret()) {
            // Parse get params for entry ids
            $ids = ee()->input->get('ids', true);
            if ($ids === false) {
                return;
            }

            $ids = array_filter(explode('|', $ids), 'is_numeric');
            if (count($ids) === 0) {
                return;
            }

            $entries = ee('Model')->get('ChannelEntry')
                ->filter('entry_id', 'IN', $ids)
                ->all();

            /** @var \BoldMinded\Speedy\Service\CacheBreaker $breaker */
            $breaker = ee('speedy:CacheBreaker');
            $breaker->_breakEntryCache($entries);
        }
    }

    /**
     * ACTION
     */
    public function _loopback_test()
    {
        @header('Content-Type: application/json; charset=UTF-8');

        echo json_encode([
            'response' => true,
        ]);

        exit;
    }

    /**
     * @return string
     */
    public function _is_frontedit_enabled()
    {
        @header('Content-Type: application/json; charset=UTF-8');

        echo json_encode([
            'response' => defined('IS_PRO') && IS_PRO && !ee('pro:FrontEdit')->fronteditIsDisabled()
        ]);

        exit;
    }

    /**
     * @return string|bool
     */
    private function fetchKeyParam()
    {
        if (ee()->TMPL->fetch_param('global', 'no') === 'yes') {
            $key = ee()->TMPL->fetch_param('key', false);
        } else {
            $key = ee()->TMPL->fetch_param('key', 'item');
        }

        if (empty($key)) {
            $this->logger->error('No cache key was specified.');
            return false;
        }

        $key = trim($key);

        try {
            CacheItem::validateKey($key);
        } catch (\InvalidArgumentException $e) {
            $message = lang('speedy_invalid_cache_key');
            $this->logger->error(sprintf($message, htmlspecialchars($key, ENT_QUOTES)));
            return false;
        }

        return trim($this->fetchKeyPrefix() . $key, '/');
    }

    /**
     * @return string
     */
    private function fetchKeyPrefix(): string
    {
        if (ee()->TMPL->fetch_param('global', 'no') === 'yes') {
            return 'global/';
        }

        return 'local/' . $this->fetchCacheUrl() . '/';
    }

    /**
     * @return string|bool
     */
    private function fetchDriverParam()
    {
        $driver = ee()->TMPL->fetch_param('driver', false);

        if (empty($driver)) {
            $message = 'No cache driver was specified. Using the default "%s" driver.';
            $this->logger->info(sprintf($message, $this->default_driver));
            return $this->default_driver;
        }

        return $driver;
    }

    /**
     * @return int|bool
     */
    private function fetchTTLParam()
    {
        if ($this->dynamic_ttl) {
            return (int) $this->dynamic_ttl;
        }

        $ttl = ee()->TMPL->fetch_param('ttl', false);

        if ($ttl === false || $ttl === '') {
            $message = 'No cache TTL was specified. Using the default %s seconds.';
            $this->logger->info(sprintf($message, $this->default_ttl));
            return $this->default_ttl;
        }

        if (!ctype_digit((string) $ttl)) {
            $message = 'The specified TTL "%s" is not valid. A TTL must be a positive integer indicating the number of seconds to cache the block.';
            $this->logger->error(sprintf($message, htmlspecialchars($ttl, ENT_QUOTES)));
            return $this->default_ttl;
        }

        return (int) $ttl;
    }

    private function parseSetTTLPair(string $tagdata = '')
    {
        if (strpos($tagdata, 'speedy:set_ttl') !== false) {
            $tags = App::parseTagPair($tagdata, 'speedy:set_ttl');

            foreach ($tags as $tag) {
                $tagdata = str_replace($tag['chunk'], '', $tagdata);

                // If multiple set_ttl pairs are present, it'll only use the last one.
                if ($tag['content']) {
                    try {
                        $isTimestamp = $this->isTimestamp($tag['content']);

                        // E.g. 3600 seconds
                        if (is_numeric($tag['content']) && !$isTimestamp) {
                            $this->dynamic_ttl = (int) $tag['content'];
                        // E.g. 1 week
                        } elseif (!is_numeric($tag['content'])) {
                            $date = new \DateTime();
                            $date->modify($tag['content']);
                            $this->dynamic_ttl = $date->getTimestamp() - time();
                        // E.g. a specific timestamp, so calc seconds until then to set the TTL
                        } elseif ($isTimestamp) {
                            $origin = new \DateTime();
                            $target = (new \DateTime())->setTimestamp($tag['content']);
                            $this->dynamic_ttl = $target->getTimestamp() - $origin->getTimestamp();
                        }
                    } catch (Exception $e) {
                        $message = 'Could not convert %s to a valid timestamp.';
                        $this->logger->error(sprintf($message, $tag['content']));
                    }
                }
            }
        }

        return $tagdata;
    }

    /**
     * @param string $string
     * @return bool
     */
    private function isTimestamp(string $string): bool {
        try {
            new DateTime('@' . $string);
        } catch(Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    private function fetchTagsParam(): string
    {
        $tags = ee()->TMPL->fetch_param('tags', '');
        $tags .= $this->fetchDynamicTags();

        return $this->prepareTags($tags);
    }

    private function prepareTags(string $tags): string
    {
        $tags = preg_replace('~\|+~', '|', $tags);
        $tags = trim(trim($tags, '|'));

        return $tags;
    }

    private function fetchDynamicTags(): string
    {
        $tags = '';

        if ($this->dynamic_tags) {
            $tags .= '|' . $this->dynamic_tags;
        }

        return $tags;
    }

    private function parseAddTagPair(string $tagdata = '')
    {
        if (strpos($tagdata, 'speedy:add_tag') !== false) {
            $tags = App::parseTagPair($tagdata, 'speedy:add_tag');

            foreach ($tags as $tag) {
                $tagdata = str_replace($tag['chunk'], '', $tagdata);

                if ($tag['content']) {
                    $this->dynamic_tags .= '|' . trim($tag['content']);
                }
            }
        }

        return $tagdata;
    }

    /**
     * @return string
     */
    private function fetchItemsParam(): string
    {
        $tags = ee()->TMPL->fetch_param('items', '');
        $tags = preg_replace('~\|+~', '|', $tags);
        $tags = trim(trim($tags, '|'));

        return $tags;
    }

    /**
     * @return string
     */
    private function fetchCacheUrl(): string
    {
        $global_vars = ee()->config->_global_vars;
        $override_param = ee()->TMPL->fetch_param('url_override');
        $url_prefix = ee()->TMPL->fetch_param('url_prefix');

        // Redis needs a little extra help with the home page... it needs a key.
        // If there is no URI, assume it's the home page.
        $isRedisDriver = $this->getCacheDriver() instanceof RedisDriver;
        $indexKey = $isRedisDriver ? 'index' : '';
        $uriString = ee()->uri->uri_string() ?: $indexKey;

        switch (true) {
            // url_override=""
            case $override_param !== false:
                return $this->cleanCacheUrl($override_param);

            // url_prefix=""
            case $url_prefix !== false:
                return $this->cleanCacheUrl($url_prefix.'/'.$uriString);

            // Zoo triggers add-on
            case isset($global_vars['triggers:original_paginated_uri']):
                return $this->cleanCacheUrl($global_vars['triggers:original_paginated_uri']);

            // Freebie add-on
            case isset($global_vars['freebie_original_uri']) && $global_vars['freebie_original_uri'] != '':
                return $this->cleanCacheUrl($global_vars['freebie_original_uri']);

            // Transcribe add-on
            case class_exists('Transcribe'):
                $transcribe = new Transcribe();

                // Returns fully qualified URL (result: https://example.com/es/acerca-de-nosotros/)
                $uri = $transcribe->uri('', $uriString);

                // Get the path from the URL (result: /es/acerca-de-nosotros/)
                $uri = parse_url($uri, PHP_URL_PATH);

                // Remove leading and trailing slashes (result: es/acerca-de-nosotros)
                $uri = trim($uri, '/');

                return $this->cleanCacheUrl($uri);

            default:
                return $this->cleanCacheUrl($uriString);
        }
    }

    /**
     * @param string $url
     * @return string
     */
    private function cleanCacheUrl(string $url): string
    {
        return ee()->security->sanitize_filename(
            $this->utf8Decode(
                reduce_double_slashes(
                    trim($url, '/')
                )
            ), true);
    }

    /**
     * PHP 8.2 support for deprecated utf8_decode()
     *
     * @param string $value
     * @return string
     */
    private function utf8Decode(string $value): string
    {
        return mb_convert_encoding($value, 'UTF-8', mb_detect_encoding($value));
    }

    /**
     * @return DriverInterface
     */
    private function getCacheDriver(): DriverInterface
    {
        $drivers = $this->fetchDriverParam();

        foreach (explode('|', $drivers) as $name) {
            try {
                $driver = $this->drivers->getDriver($name, $this->logger);
                if (!$driver->isSupported()) {
                    $message = 'The specified driver "%s" is not supported.';
                    $this->logger->error(sprintf($message, htmlspecialchars($name, ENT_QUOTES)));
                    continue;
                }
                return $driver;
            } catch (InvalidArgumentException $e) {
                $message = 'The specified driver "%s" is not valid.';
                $this->logger->error(sprintf($message, htmlspecialchars($name, ENT_QUOTES)));
                continue;
            }
        }

        $this->logger->info('Falling back to "dummy" driver.');

        return $this->drivers->getDriver('dummy', $this->logger);
    }

    /**
     * @param string $tagdata
     * @return string
     */
    private function processTagdata(string $tagdata = ''): string
    {
        self::$is_caching = true;

        // Call the `speedy_pre_parse` hook
        if (ee()->extensions->active_hook('speedy_pre_parse')) {
            $tagdata = ee()->extensions->call('speedy_pre_parse', $tagdata);
        }

        $tagdata = $this->parseAsTemplate($tagdata);
        $tagdata = $this->parseSetTTLPair($tagdata);
        $tagdata = $this->parseAddTagPair($tagdata);

        // Call the `speedy_post_parse` hook
        if (ee()->extensions->active_hook('speedy_post_parse')) {
            $tagdata = ee()->extensions->call('speedy_post_parse', $tagdata);
        }

        self::$is_caching = false;

        return $this->unescape($tagdata);
    }

    /**
     * @param string $tagdata
     * @return string
     */
    private function processStaticTagdata(string $tagdata = ''): string
    {
        // Call the `speedy_pre_parse` hook
        if (ee()->extensions->active_hook('speedy_pre_parse')) {
            $tagdata = ee()->extensions->call('speedy_pre_parse', $tagdata);
        }

        if (ee()->output->out_type === 'feed') {
            $tagdata = preg_replace('~<ee\:last_update>(.*?)<\/ee\:last_update>~', '', $tagdata);
            $tagdata = preg_replace('~\{\?xml(.+?)\?\}~', '<?xml\\1?>', $tagdata);
        }

        $tagdata = $this->parseSetTTLPair($tagdata);
        $tagdata = $this->parseAddTagPair($tagdata);

        // Call the `speedy_post_parse` hook
        if (ee()->extensions->active_hook('speedy_post_parse')) {
            $tagdata = ee()->extensions->call('speedy_post_parse', $tagdata);
        }

        return $tagdata;
    }

    /**
     * @param string $tagdata
     * @return string
     */
    private function parseAsTemplate(string $tagdata = ''): string
    {
        $this->logger->info('Processing tagdata to be cached:');

        // Capture a reference to the current template parser
        $ee_tmpl = ee()->TMPL;

        // Create a new template parser and copy over relevant details
        $speedy_tmpl = new EE_Template();
        $speedy_tmpl->start_microtime = $ee_tmpl->start_microtime;
        $speedy_tmpl->depth = $ee_tmpl->depth + 1;
        $speedy_tmpl->plugins = $ee_tmpl->plugins;
        $speedy_tmpl->modules = $ee_tmpl->modules;

        // Push the new template parser into the Facade as it
        // references itself while parsing templates.
        ee()->remove('TMPL');
        ee()->set('TMPL', $speedy_tmpl);

        // Execute the parser the template fragment to be cached
        $speedy_tmpl->parse_tags();
        $speedy_tmpl->process_tags();
        $speedy_tmpl->parse($tagdata);

        $tagdata = $speedy_tmpl->final_template;

        // Restore the original template parser
        ee()->remove('TMPL');
        ee()->set('TMPL', $ee_tmpl);

        // Copy logged information back onto the original template parser
        foreach ($speedy_tmpl->log as $item) {
            $ee_tmpl->log[] = $item;
        }

        // Clean up template parser references
        unset($ee_tmpl, $speedy_tmpl);

        // Call the `template_post_parse` hook for this data
        if (ee()->extensions->active_hook('template_post_parse')) {
            $tagdata = ee()->extensions->call('template_post_parse', $tagdata, false, $this->siteId);
        }

        return $tagdata;
    }

    /**
     * @param string $tagdata
     * @return string
     */
    private function unescape(string $tagdata = ''): string
    {
        // Recursively replace any content escaped by {exp:speedy:escape}
        while (false !== $pos = strpos($tagdata, '{!-- speedy:escape:')) {
            $escape = substr($tagdata, $pos, 55);
            $content = $this->escapes->retrieve($escape);
            $tagdata = str_replace($escape, $content, $tagdata);
        }

        return $tagdata;
    }

    /**
     * @param string $tagdata
     * @return string
     */
    private function processReturn(string $tagdata = ''): string
    {
        $tagdata = $this->parseVars($tagdata);
        $tagdata = $this->parseActionIds($tagdata);

        return $tagdata;
    }

    /**
     * @param string $tagdata
     * @return string
     */
    private function parseVars(string $tagdata = ''): string
    {
        $tagdata = ee()->TMPL->remove_ee_comments($tagdata);
        $tagdata = $this->parseSegments($tagdata);
        $tagdata = $this->parseGlobalVars($tagdata);
        $tagdata = $this->parseDates($tagdata);
        $tagdata = $this->parseLayoutVariables($tagdata);

        return $tagdata;
    }

    /**
     * @param string $tagdata
     * @return string
     */
    private function parseSegments(string $tagdata = ''): string
    {
        if (strpos($tagdata, '{segment_') !== false) {
            for ($i = 1; $i < 10; $i++) {
                $tagdata = str_replace('{segment_' . $i . '}', ee()->uri->segment($i), $tagdata);
            }
        }

        return $tagdata;
    }

    private function parseGlobalVars(string $tagdata = ''): string
    {
        return ee()->TMPL->parse_variables_row($tagdata, ee()->config->_global_vars);
    }

    private function parseLayoutVariables(string $tagdata = ''): string
    {
        if (strpos($tagdata, '{layout') === false) {
            return $tagdata;
        }

        // https://github.com/ExpressionEngine/ExpressionEngine/pull/4638
        // @todo If this method gets made public in a later EE release, this can be removed.
        // This is should be public in EE 7.5.13 - remove eventually or add a version check instead
        if (method_exists(ee()->TMPL, 'parseLayoutVariables')) {
            $reflection = new ReflectionMethod(ee()->TMPL, 'parseLayoutVariables');

            if (!$reflection->isPublic()) {
                $instance = new EE_Template;
                $reflectionClass = new ReflectionClass(EE_Template::class);
                $method = $reflectionClass->getMethod('parseLayoutVariables');
                $method->setAccessible(true);

                return $method->invoke($instance, $tagdata, ee()->TMPL->layout_vars);
            } else {
                return ee()->TMPL->parseLayoutVariables($tagdata, ee()->TMPL->layout_vars);
            }
        }

        return $tagdata;
    }

    /**
     * @param string $tagdata
     * @return string
     */
    private function parseDates(string $tagdata = ''): string
    {
        $dates = [];

        if (strpos($tagdata, '{template_edit_date') !== false) {
            $dates['template_edit_date'] = ee()->TMPL->template_edit_date;
        }

        if (strpos($tagdata, '{current_time') !== false) {
            $dates['current_time'] = ee()->localize->now;
        }

        return ee()->TMPL->parse_date_variables($tagdata, $dates);
    }

    /**
     * Assist EE a bit b/c action URLs may not get cached, so we call the
     * insert_action_ids method which updates these types of variables, and
     * unfortunately we have to do an extra query.
     *
     * <input type="hidden" name="ACT" value="{AID:Email:send_email}" />
     *
     * @param string $tagdata
     * @return string
     */
    private function parseActionIds(string $tagdata = ''): string
    {
        // Don't perform an extra query if we don't have to.
        if (strpos($tagdata, '{AID:') === false) {
            return $tagdata;
        }

        $actions = ee('Model')->get('Action')->all()->getDictionary('class', 'method');
        $collection = [];

        foreach ($actions as $className => $methodName) {
            $collection[$className][$methodName] = $methodName;
        }

        ee()->functions->action_ids = $collection;
        return ee()->functions->insert_action_ids($tagdata);
    }

    /**
     * @return string
     */
    private function getTagdata(): string
    {
        // We need to retrieve the tagdata like this as ee()->TMPL->tagdata
        // will have already been passed through EE_Template::parse_tags()
        // which will strip any no_results content from inner tags.
        $index = 0;
        foreach (ee()->TMPL->tag_data as $i => $data) {
            if (ee()->TMPL->tagchunk == $data['chunk']) {
                $index = $i;
            }
        }

        return trim(ee()->TMPL->tag_data[$index]['block']);
    }

    /**
     * Register the shutdown handler
     */
    private function registerShutdown()
    {
        if (self::$shutdown === null) {
            self::$shutdown = ShutdownProcess::create(function () {
                $this->handleShutdown();
            });
        }
    }

    /**
     * Callback to run on shutdown
     */
    private function handleShutdown()
    {
        $is_404 = ee()->output->out_type === '404';
        $exclude_404s = ee()->config->item('speedy_exclude_404s') !== 'no';

        if ($is_404 && $exclude_404s) {
            $this->logger->info('Page returned 404 and exclude 404s enabled. Deleting fragments cached this session.');
            $this->deleteCachedItem();
            return;
        }

        if (self::$static_cache === null) {
            return;
        }

        /** @var \BoldMinded\Speedy\Service\Drivers\DriverInterface $driver */
        $driver = $this->drivers->getDriver(self::$static_cache['driver']);
        $tagdata = trim(ee()->TMPL->final_template);

        // Do we allow caching of empty content? If not then return empty content without caching it.
        if ($tagdata === '' && (ee()->TMPL->fetch_param('allow_empty_cache') !== 'yes')) {
            return;
        }

        if (!$driver->isSupported()) {
            return;
        }

        $url = self::$static_cache['url'];
        $ttl = $this->dynamic_ttl ?: self::$static_cache['ttl'];
        $tags = self::$static_cache['tags'];
        $key = 'static/' . $url;

        $allowList = ee()->config->item('speedy_query_cache_allowlist') ?: [];

        if (!empty($_GET) && !empty($allowList)) {
            $staticCacheHelper = new StaticCacheHelper($allowList, $_GET);

            if (self::$static_cache['driver'] === StaticDriver::NAME) {
                $queryString = $staticCacheHelper->getQueryStringEncoded();
            } else {
                $queryString = $staticCacheHelper->getQueryString();
            }

            if ($queryString) {
                $key .= '+'. $queryString;
            }
        }

        $cacheItem = new CacheItem();
        $cacheItem
            ->setValue($this->processStaticTagdata($tagdata))
            ->setKey($key)
            ->setTTL($ttl);

        // Call the `speedy_pre_save` hook
        if (ee()->extensions->active_hook('speedy_pre_save')) {
            $cacheItem = ee()->extensions->call('speedy_pre_save', $cacheItem, 'static');

            if (ee()->extensions->end_script === true) {
                return;
            }
        }

        $this->logger->info(sprintf('Storing processed tagdata for key "%s".', $key));
        $driver->save($cacheItem);

        // Fetch dynamic tags and append them to whatever might have been added directly to the static tag,
        // which is gathered in the static() method. Since the template has not been parsed at that point
        // we have to call this at the end of the process after it's been parsed.
        // Save the tags for this item
        if (!$driver instanceof DummyDriver) {
            $dynamicTags = $this->fetchDynamicTags();
            $tags = $this->prepareTags($tags . $dynamicTags);

            $this->saveTags($key, $tags);
            $this->saveUrl($key);
        }
    }

    /**
     * Delete all items cached in this session.
     */
    private function deleteCachedItem()
    {
        foreach (self::$cached_items as $name => $keys) {
            $driver = $this->drivers->getDriver($name);
            if ($driver->isSupported()) {
                foreach ($keys as $key) {
                    $driver->deleteItem($key);
                }
            }
        }

        self::$cached_items = [];
    }

    /**
     * @param string $key
     * @param string $tag_string
     */
    private function saveTags(string $key, string $tag_string = '')
    {
        // Prefix it for MSM support
        $key = SPEEDY_CLASS_NAME .'/'. ee()->config->item('site_short_name') . '/' . $key;
        $tags = $this->parseTagString($tag_string, $key);

        // Delete all tags currently associated with this key.
        ee('Model')->get('speedy:Tag')
            ->filter('key', $key)
            ->filter('site_id', $this->siteId)
            ->delete();

        // Build and save all the new tags for this key.
        /** @var \BoldMinded\Speedy\Model\Tag $tag */
        foreach ($tags as $tag_name) {
            $tag = ee('Model')->make('speedy:Tag');
            $tag->key = $key;
            $tag->tag = $tag_name;
            $tag->site_id = $this->siteId;
            $tag->save();
        }
    }

    /*
     * If a reverse proxy purger is set, record all the URLs and the matching keys.
     * They'll be used later when attempting to purge the proxy cache.
     */
    private function saveUrl(string $key): void
    {
        $settings = json_decode($this->setting->get('settings_purger'), true);
        $providerName = $settings['provider'] ?? '';

        // If a reverse proxy purger has not been defined, don't record any URLs
        if (!$providerName) {
            return;
        }

        $currentUri = ee()->functions->fetch_current_uri();
        $queryString = $_SERVER['QUERY_STRING'] ?? '';

        if ($queryString) {
            $currentUri .= '?' . $queryString;
        }

        // Play it safe
        if (!$currentUri) {
            return;
        }

        // The URI is partly request-derived (path + query string) and is later
        // handed to the reverse-proxy purge API, so normalise and validate it
        // against the site's own scheme/host before persisting. Anything that
        // resolves to a foreign host (or is otherwise malformed) is dropped so
        // it cannot pollute or misdirect a purge batch.
        $currentUri = $this->normalizePurgeUrl($currentUri);

        if ($currentUri === null) {
            $this->logger->info(sprintf(
                'Refusing to record off-site or malformed purge URL for key "%s".',
                $key
            ));

            return;
        }

        $exists = ee('Model')->get('speedy:Url')
            ->filter('key', $key)
            ->filter('url', $currentUri)
            ->filter('site_id', $this->siteId)
            ->all();

        if (count($exists) === 0) {
            $url = ee('Model')->make('speedy:Url');
            $url->key = $key;
            $url->url = $currentUri;
            $url->site_id = $this->siteId;
            $url->save();
        }
    }

    /**
     * Validate a candidate purge URL against the site's own scheme and host.
     *
     * Returns the normalized URL when it belongs to this site, or null when it
     * resolves to a foreign host or cannot be parsed. The returned URL keeps its
     * path and query string but is rebuilt from trusted components so it is safe
     * to persist and later send to a reverse-proxy purge API.
     */
    private function normalizePurgeUrl(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== '' && $scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        // Determine the trusted host (and scheme) from the site's configured
        // base URL rather than from request data.
        $base = parse_url((string) ee()->functions->fetch_site_index(0, 0));
        $trustedHost = isset($base['host']) ? strtolower($base['host']) : '';

        if ($trustedHost === '' || strtolower($parts['host']) !== $trustedHost) {
            return null;
        }

        // Rebuild from validated components. Userinfo (user:pass@) is dropped.
        $rebuilt = ($scheme !== '' ? $scheme : ($base['scheme'] ?? 'https')) . '://' . $trustedHost;

        if (isset($base['port'])) {
            $rebuilt .= ':' . $base['port'];
        } elseif (isset($parts['port'])) {
            $rebuilt .= ':' . (int) $parts['port'];
        }

        $rebuilt .= $parts['path'] ?? '';

        if (isset($parts['query']) && $parts['query'] !== '') {
            $rebuilt .= '?' . $parts['query'];
        }

        return $rebuilt;
    }

    /**
     * @param string $tag_string
     * @param string $key
     * @return array
     */
    private function parseTagString(string $tag_string = '', string $key = ''): array
    {
        $tags = [];
        $raw_tags = explode('|', $tag_string);

        foreach ($raw_tags as $tag) {
            $tag = trim($tag);

            if (empty($tag)) {
                $message = 'An empty tag was found and will not be applied to the saved item "%s".';
                $this->logger->info(sprintf($message, $key));
                continue;
            }

            if (strlen($tag) > 100) {
                $message = 'The tag "%s" could not be saved for the "%s" item, because it is over 100 characters long.';
                $this->logger->info(sprintf($message, $tag, $key));
                continue;
            }

            $tags[] = $tag;
        }

        return array_unique($tags);
    }

    /**
     * Authorize request to break caches with secret param.
     *
     * @return bool
     */
    private function verifyActionSecret(): bool
    {
        $real_secret = ee()->config->item('speedy_secret');

        if ($real_secret === false || trim((string) $real_secret) === '') {
            return false;
        }

        $user_secret = $this->fetchActionSecret();

        if ($user_secret === '') {
            return false;
        }

        return SpeedyUtil::hashEquals((string) $real_secret, $user_secret);
    }

    private function fetchActionSecret(): string
    {
        $header = $_SERVER['HTTP_X_SPEEDY_SECRET'] ?? '';
        if (is_string($header) && $header !== '') {
            return $header;
        }

        $post = ee()->input->post('secret', true);
        if (is_string($post) && $post !== '') {
            return $post;
        }

        return '';
    }

    private function shouldCache(string $type = 'fragment'): bool
    {
        // Is Speedy disabled
        if (ee()->config->item('speedy_enabled') === 'no') {
            return false;
        }

        // Is POST request
        $ignorePost = ee()->config->item('speedy_ignore_post_requests') !== 'no';
        if ($ignorePost && !empty($_POST)) {
            return false;
        }

        // Block bot traffic
        if (ee()->config->item('speedy_block_bots') === 'yes' && $this->isBot()) {
            return false;
        }

        // Logged in only, but user is not logged in
        $loggedIn = ee()->session->userdata['member_id'] !== 0;
        if (ee()->config->item('speedy_logged_in_only') === 'yes' && !$loggedIn) {
            return false;
        }

        // Logged out only, but user is logged in
        if (ee()->config->item('speedy_logged_out_only') === 'yes' && $loggedIn) {
            return false;
        }

        // If someone wants to conditionally disable based on a tag param, e.g.
        // {exp:speedy:fragment disable="{if publisher:is_draft}yes{/if}"}
        if (ee()->TMPL->fetch_param('disable') === 'yes') {
            return false;
        }

        if (ee()->extensions->active_hook('speedy_should_cache')) {
            return ee()->extensions->call('speedy_should_cache', $this, $type);
        }

        return true;
    }

    /**
     * @return bool
     */
    private function shouldCacheFragment(): bool
    {
        return $this->shouldCache();
    }

    /**
     * @return bool
     */
    private function shouldCacheStaticFile(): bool
    {
        // This page is already queued for static caching
        if (self::$static_cache !== null) {
            return false;
        }

        // Static cache enabled
        if (ee()->config->item('speedy_static_enabled') !== 'yes') {
            return false;
        }

        // Publisher addon
        if (isset($_GET['publisher_status']) && $_GET['publisher_status'] === 'draft') {
            return false;
        }

        // Ignore ?ACT= requests?
        if (
            ee()->config->item('speedy_ignore_action_requests') === 'yes' &&
            ee('Request')->get('ACT')
        ) {
            return false;
        }

        // Base rules apply to static file as well
        return $this->shouldCache('static');
    }

    /**
     * @return bool
     */
    private function isBot(): bool
    {
        if (self::$is_bot === null) {
            $user_agent = ee()->input->user_agent();

            self::$is_bot = (bool) (!empty($user_agent) && preg_match('~bot|spider|crawl|curl~i', $user_agent));
        }

        return self::$is_bot;
    }
}
