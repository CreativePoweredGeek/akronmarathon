<?php

namespace BoldMinded\Speedy\Commands;

use BoldMinded\Speedy\Commands\Support\RequestSimulator;
use BoldMinded\Speedy\Commands\Support\SessionSimulator;
use BoldMinded\Speedy\Commands\Support\UriSimulator;
use BoldMinded\Speedy\Service\CacheItem;
use BoldMinded\Speedy\Service\Drivers\DriverInterface;
use Error;
use Exception;

/**
 * Bulk-generate cache items by driving mod.speedy.php's static() and fragment()
 * methods through a simulated EE request, exercising the full caching lifecycle
 * (key generation, driver save, tag/URL storage, and the static shutdown write).
 *
 * Usage:
 *   php system/ee/eecli.php speedy:generate
 *   php system/ee/eecli.php speedy:generate --total=500 --mode=fragment --driver=filesystem
 */
class CommandGenerateCacheItems extends AbstractCommand
{
    /** @var string */
    public $name = 'Generate Cache Items';

    /** @var string */
    public $description = 'Generate a large number of cache items by simulating EE requests through the static and fragment caching methods.';

    /** @var string */
    public $summary = '';

    /** @var string */
    public $usage = 'php system/ee/eecli.php speedy:generate --total=10000 --mode=static';

    /** @var array */
    public $commandOptions = [
        'total:' => 'How many cache items to generate. Defaults to 10000.',
        'driver:' => 'Override the cache driver. Defaults to the configured "speedy_driver". Note: in static mode the module forces the driver to "static" (or "redis"), so this only fully applies to fragment mode.',
        'mode:' => 'Which method to exercise: "static" (default), "fragment", or "both".',
        'ttl:' => 'TTL in seconds for generated items. Defaults to 3600.',
    ];

    /** @var int */
    const DEFAULT_TOTAL = 10000;

    /** @var int */
    const DEFAULT_TTL = 3600;

    /** @var int How often to print a progress line. */
    const PROGRESS_INTERVAL = 500;

    /** @var array<string, mixed> Original config values to restore when finished. */
    private $originalConfig = [];

    /** @var mixed Original ee()->TMPL, restored when finished. */
    private $originalTmpl;

    /** @var mixed Original ee()->uri, restored when finished. */
    private $originalUri;

    /** @var int|null Original member_id, restored when finished. */
    private $originalMemberId;

    /**
     * @var string Language segment prefix applied to generated keys so they match
     * what real Publisher-prefixed pages produce (e.g. "en"). Empty when Publisher
     * is not installed. Without this, generated keys would not match real page keys
     * and clearing/purging would miss them.
     */
    private $languagePrefix = '';

    /** @var bool Whether we injected a session stub that must be removed afterward. */
    private $injectedSession = false;

    public function handle(): string
    {
        try {
            $this->checkPath();
            $this->loadModule();

            $total = max(1, (int) ($this->option('--total') ?: self::DEFAULT_TOTAL));
            $ttl = max(0, (int) ($this->option('--ttl') ?: self::DEFAULT_TTL));
            $mode = strtolower(trim((string) ($this->option('--mode') ?: 'static')));
            $driverOverride = trim((string) ($this->option('--driver') ?: ''));

            if (!in_array($mode, ['static', 'fragment', 'both'], true)) {
                $this->output->outln('<<red>>Invalid --mode. Use "static", "fragment", or "both".<<reset>>');
                return '';
            }

            if ($driverOverride !== '') {
                $validDrivers = $this->validDriverNames();

                if (!in_array($driverOverride, $validDrivers, true)) {
                    $this->output->outln(sprintf(
                        '<<red>>Invalid --driver "%s". Valid drivers: %s.<<reset>>',
                        $driverOverride,
                        implode(', ', $validDrivers)
                    ));
                    return '';
                }
            }

            if ($mode === 'fragment' || $mode === 'both') {
                $this->loadTemplateLibrary();
            }

            $configuredDriver = $driverOverride ?: (ee()->config->item('speedy_driver') ?: 'dummy');

            $this->prepareEnvironment($mode, $driverOverride);

            // Pre-flight: make sure every driver we're about to write to is
            // supported (which, for the filesystem/static drivers, includes a
            // "cache path is writable" check) AND can actually persist an item.
            // The static shutdown path swallows the driver's save() return value,
            // so without this a permissions failure would churn through every item
            // emitting raw mkdir warnings and silently writing nothing.
            if (!$this->preflightDrivers($mode, $configuredDriver, $driverOverride)) {
                return '';
            }

            $this->output->outln(sprintf(
                '<<green>>Generating %s cache items (mode: %s, configured driver: %s, ttl: %ds)...<<reset>>',
                number_format($total),
                $mode,
                $configuredDriver,
                $ttl
            ));

            $this->output->outln($this->languagePrefix !== ''
                ? sprintf('<<green>>Publisher detected: prefixing keys with language segment "%s".<<reset>>', $this->languagePrefix)
                : '<<green>>No Publisher language prefix applied.<<reset>>');

            $startTime = microtime(true);
            $written = 0;

            // The filesystem/static drivers use @-suppressed mkdir/rename and log
            // their own errors; without this, a permissions problem prints one raw
            // PHP warning per item. Mute warnings during the loop and rely on the
            // probe + count verification to surface real failures.
            $previousErrorReporting = error_reporting();
            error_reporting($previousErrorReporting & ~E_WARNING & ~E_DEPRECATED);

            try {
                for ($i = 1; $i <= $total; $i++) {
                    if ($mode === 'fragment' || $mode === 'both') {
                        $written += $this->generateFragmentItem($i, $ttl) ? 1 : 0;
                    }

                    if ($mode === 'static' || $mode === 'both') {
                        $written += $this->generateStaticItem($i, $ttl) ? 1 : 0;
                    }

                    if ($i % self::PROGRESS_INTERVAL === 0) {
                        $this->output->outln(sprintf('<<green>>  ...%s/%s<<reset>>', number_format($i), number_format($total)));
                    }
                }
            } finally {
                error_reporting($previousErrorReporting);
            }

            $elapsed = round(microtime(true) - $startTime, 2);

            $this->reportSummary($mode, $configuredDriver, $driverOverride, $written, $elapsed);
        } catch (Error $error) {
            $this->output->outln('<<red>>' . $error->getMessage() . '<<reset>>');
        } catch (Exception $exception) {
            $this->output->outln('<<red>>' . $exception->getMessage() . '<<reset>>');
        } finally {
            $this->restoreEnvironment();
        }

        return '';
    }

    /**
     * @return string[] The registered driver names (e.g. file, database, redis, static).
     */
    private function validDriverNames(): array
    {
        $names = [];

        foreach (ee('speedy:DriverFactory')->getDrivers() as $driver) {
            if ($driver instanceof DriverInterface) {
                $names[] = $driver->getName();
            }
        }

        return $names;
    }

    /**
     * The Speedy module class lives in mod.speedy.php and is not PSR-4 autoloaded
     * (EE loads it on demand during template parsing). From the CLI we must pull
     * it in ourselves before we can instantiate it.
     */
    private function loadModule(): void
    {
        if (class_exists('Speedy')) {
            return;
        }

        $path = PATH_THIRD . 'speedy/mod.speedy.php';

        if (!is_file($path)) {
            throw new Exception('Unable to locate mod.speedy.php to load the Speedy module.');
        }

        require_once $path;

        if (!class_exists('Speedy')) {
            throw new Exception('Loaded mod.speedy.php but the Speedy class was not defined.');
        }
    }

    /**
     * Fragment caching re-parses the cached content through a fresh EE_Template
     * (see Speedy::parseAsTemplate). That class is not loaded in a CLI context,
     * so pull in EE's template library before driving fragment(). We load it
     * under a throwaway key so it does not collide with the TMPL we swap per item.
     */
    private function loadTemplateLibrary(): void
    {
        if (class_exists('EE_Template')) {
            return;
        }

        ee()->load->library('template', null, '__speedy_template_loader');

        // We only needed the class defined; drop the loader instance so it does
        // not linger on the super object.
        ee()->remove('__speedy_template_loader');

        if (!class_exists('EE_Template')) {
            throw new Exception('Unable to load EE_Template, required for fragment mode.');
        }
    }

    /**
     * Drive Speedy::fragment() for a single item. fragment() saves inline, so no
     * shutdown firing is needed; we just reset module state afterward.
     */
    private function generateFragmentItem(int $index, int $ttl): bool
    {
        \Speedy::_reset();

        $this->installRequestContext(
            $this->fragmentParams($index, $ttl),
            $this->itemUri($index),
            $this->itemContent($index)
        );

        $speedy = new \Speedy();
        $speedy->fragment();

        return true;
    }

    /**
     * Drive Speedy::static() for a single item. static() only stashes state and
     * registers a shutdown callback; the actual write happens in handleShutdown().
     * We fire that callback explicitly so the item is persisted now, then reset.
     */
    private function generateStaticItem(int $index, int $ttl): bool
    {
        \Speedy::_reset();

        $this->installRequestContext(
            $this->staticParams($index, $ttl),
            $this->itemUri($index),
            $this->itemContent($index)
        );

        $speedy = new \Speedy();
        $speedy->static();

        // static() registers a shutdown callback that performs the write. Fire it
        // now rather than waiting for PHP's real shutdown (which would only retain
        // the last item's callback, since registerShutdown() is guarded).
        $shutdown = \Speedy::_getShutdown();

        if ($shutdown === null) {
            // shouldCacheStaticFile() returned false — environment not eligible.
            return false;
        }

        $shutdown->call();

        return true;
    }

    /**
     * Swap in our simulated TMPL/uri so Speedy reads this item's params/content.
     */
    private function installRequestContext(array $params, string $uri, string $content): void
    {
        $tmpl = (new RequestSimulator())
            ->setParams($params)
            ->setContent($content);

        $uriSim = (new UriSimulator())->setUriString($uri);

        ee()->remove('TMPL');
        ee()->set('TMPL', $tmpl);

        ee()->remove('uri');
        ee()->set('uri', $uriSim);
    }

    private function fragmentParams(int $index, int $ttl): array
    {
        return [
            'key' => 'item-' . $index,
            'ttl' => (string) $ttl,
            'global' => 'no',
            'allow_empty_cache' => 'no',
        ];
    }

    private function staticParams(int $index, int $ttl): array
    {
        return [
            'ttl' => (string) $ttl,
            'allow_empty_cache' => 'no',
        ];
    }

    /**
     * Determine the language segment that real pages would carry. Only Publisher
     * adds one (via url_prefix="{publisher:current_language_code}"); we use its
     * default language's URL segment. Returns '' when Publisher is not installed,
     * so non-Publisher sites keep their existing un-prefixed key shape.
     */
    private function resolveLanguagePrefix(): string
    {
        try {
            $publisher = ee('Addon')->get('publisher');

            if ($publisher === null || !$publisher->isInstalled()) {
                return '';
            }
        } catch (Error | Exception $e) {
            return '';
        }

        // Prefer Publisher's own model; fall back to a direct query on its table.
        try {
            $language = ee('Model')->get('publisher:Language')
                ->filter('is_default', 'y')
                ->first();

            if ($language && !empty($language->short_name_segment)) {
                return trim($language->short_name_segment, '/');
            }
        } catch (Error | Exception $e) {
            // Model not available under that name; fall through to the query.
        }

        try {
            $row = ee()->db
                ->select('short_name_segment')
                ->where('is_default', 'y')
                ->limit(1)
                ->get('publisher_languages')
                ->row();

            if ($row && !empty($row->short_name_segment)) {
                return trim($row->short_name_segment, '/');
            }
        } catch (Error | Exception $e) {
            // Leave the prefix empty if we can't determine it.
        }

        return '';
    }

    private function itemUri(int $index): string
    {
        // Put the language segment in the URI itself (not a url_prefix param) so it
        // mirrors a real Publisher request, where the prefix is part of the actual
        // request URL. This keeps BOTH the cache key (static/en/...) AND the
        // recorded purge URL (https://host/en/...) prefixed consistently.
        $uri = 'speedy-generate/item-' . $index;

        if ($this->languagePrefix !== '') {
            $uri = $this->languagePrefix . '/' . $uri;
        }

        return $uri;
    }

    private function itemContent(int $index): string
    {
        return 'Speedy generated cache item #' . $index;
    }

    /**
     * Temporarily set the config and session state the caching guards require,
     * saving originals so they can be restored when the command finishes.
     */
    private function prepareEnvironment(string $mode, string $driverOverride): void
    {
        $needsStatic = ($mode === 'static' || $mode === 'both');

        // Resolve the language segment real pages would carry (Publisher's default
        // language code). Generated keys must include it or clearing won't match.
        $this->languagePrefix = $this->resolveLanguagePrefix();

        $overrides = [
            'speedy_enabled' => 'yes',
            // Static caching must be enabled for static()/handleShutdown() to run.
            // For fragment-only mode we DISABLE it, otherwise fragment() refuses to
            // cache unless speedy_driver_fragment is set and would ignore --driver.
            'speedy_static_enabled' => $needsStatic ? 'yes' : 'no',
            'speedy_ignore_post_requests' => 'yes',
            'speedy_ignore_action_requests' => 'no',
            'speedy_block_bots' => 'no',
            'speedy_logged_in_only' => 'no',
            'speedy_logged_out_only' => 'no',
            'speedy_exclude_404s' => 'no',
        ];

        if ($driverOverride !== '') {
            // For fragment-only mode (static disabled) fragments resolve via the
            // normal getCacheDriver() path, which honors speedy_driver.
            $overrides['speedy_driver'] = $driverOverride;

            // In "both" mode static is enabled, so fragment() reads
            // speedy_driver_fragment instead; point it at the override too so the
            // requested driver actually applies to the fragment items.
            if ($mode === 'both') {
                $overrides['speedy_driver_fragment'] = $driverOverride;
            }
        }

        foreach ($overrides as $key => $value) {
            $this->originalConfig[$key] = ee()->config->item($key);
            ee()->config->set_item($key, $value);
        }

        $this->originalTmpl = isset(ee()->TMPL) ? ee()->TMPL : null;
        $this->originalUri = isset(ee()->uri) ? ee()->uri : null;

        // Speedy's guards read ee()->session->userdata['member_id']. In CLI there
        // is no session, so inject a guest-session stub; if a real session is
        // already loaded, just remember member_id so we can restore it.
        if (isset(ee()->session) && isset(ee()->session->userdata)) {
            $this->originalMemberId = ee()->session->userdata['member_id'] ?? null;
            ee()->session->userdata['member_id'] = 0;
        } else {
            ee()->set('session', new SessionSimulator());
            $this->injectedSession = true;
        }
    }

    private function restoreEnvironment(): void
    {
        foreach ($this->originalConfig as $key => $value) {
            ee()->config->set_item($key, $value);
        }
        $this->originalConfig = [];

        ee()->remove('TMPL');
        if ($this->originalTmpl !== null) {
            ee()->set('TMPL', $this->originalTmpl);
        }

        ee()->remove('uri');
        if ($this->originalUri !== null) {
            ee()->set('uri', $this->originalUri);
        }

        if ($this->injectedSession) {
            ee()->remove('session');
            $this->injectedSession = false;
        } elseif ($this->originalMemberId !== null && isset(ee()->session) && isset(ee()->session->userdata)) {
            ee()->session->userdata['member_id'] = $this->originalMemberId;
        }

        if (class_exists('Speedy')) {
            \Speedy::_reset();
        }
    }

    private function reportSummary(string $mode, string $configuredDriver, string $driverOverride, int $written, float $elapsed): void
    {
        $this->output->outln('');
        $this->output->outln(sprintf('<<green>>Done. Wrote %s items in %ss.<<reset>>', number_format($written), $elapsed));

        if (($mode === 'static' || $mode === 'both') && !in_array($configuredDriver, ['static', 'redis'], true)) {
            $this->output->outln(sprintf(
                '<<yellow>>Note: in static mode Speedy forces the driver to "static" (configured driver "%s" was not static/redis), so static items were written to the static driver.<<reset>>',
                $configuredDriver
            ));
        }

        // Sanity check: report current item counts for the relevant drivers.
        foreach ($this->summaryDrivers($mode, $configuredDriver, $driverOverride) as $driverName) {
            $count = $this->countItemsSafely($driverName);
            if ($count !== null) {
                $this->output->outln(sprintf('<<green>>  %s driver now reports %s items.<<reset>>', $driverName, number_format($count)));
            }
        }
    }

    /**
     * The driver names items will actually be written to, mirroring the
     * resolution logic in mod.speedy.php for the prepared environment.
     *
     * @return string[]
     */
    private function summaryDrivers(string $mode, string $configuredDriver, string $driverOverride): array
    {
        $drivers = [];

        if ($mode === 'fragment' || $mode === 'both') {
            if ($mode === 'both') {
                // Static is enabled in "both" mode, so fragment() uses the
                // fragment driver (which prepareEnvironment points at the
                // override when one is given).
                $drivers[] = $driverOverride ?: (ee()->config->item('speedy_driver_fragment') ?: $configuredDriver);
            } else {
                // Fragment-only: static disabled, fragments use speedy_driver.
                $drivers[] = $driverOverride ?: $configuredDriver;
            }
        }

        if ($mode === 'static' || $mode === 'both') {
            // static() forces the driver to static unless it is already redis.
            $drivers[] = in_array($configuredDriver, ['static', 'redis'], true) ? $configuredDriver : 'static';
        }

        return array_values(array_unique(array_filter($drivers)));
    }

    /**
     * Verify each target driver is supported before generating, failing fast
     * with the specific reason (e.g. "Cache path is writable" => false) instead
     * of emitting raw mkdir warnings and writing nothing.
     */
    private function preflightDrivers(string $mode, string $configuredDriver, string $driverOverride): bool
    {
        foreach ($this->summaryDrivers($mode, $configuredDriver, $driverOverride) as $driverName) {
            /** @var DriverInterface|false $driver */
            $driver = ee('speedy:DriverFactory')->getDriver($driverName, ee('speedy:Logger'));

            if (!$driver instanceof DriverInterface) {
                $this->output->outln(sprintf('<<red>>Driver "%s" could not be resolved.<<reset>>', $driverName));
                return false;
            }

            if (!$driver->isSupported()) {
                $this->output->outln(sprintf('<<red>>Driver "%s" is not ready to write:<<reset>>', $driverName));

                foreach ($driver->getSupportValidator()->getSupportList() as $reason => $passed) {
                    $mark = $passed ? '<<green>>OK  <<reset>>' : '<<red>>FAIL<<reset>>';
                    $this->output->outln(sprintf('  [%s] %s', $mark, $reason));
                }

                $this->printPermissionTip();

                return false;
            }

            // isSupported() only checks the top-level cache path is writable; a
            // sub-directory (e.g. the per-site folder) created earlier by the web
            // server or root can still block the CLI user. Do a real save() probe
            // with a throwaway key to confirm we can actually persist, then clean
            // it up. save() returns an honest bool (unlike the static shutdown,
            // which discards it).
            $probeKey = 'speedy-generate/__write_probe__';

            try {
                $probeItem = (new CacheItem())
                    ->setKey($probeKey)
                    ->setValue('speedy generate write probe');
                $probeItem->setTTL(60);

                $saved = $driver->save($probeItem);
                $driver->deleteItem($probeKey);
            } catch (Error | Exception $e) {
                $saved = false;
            }

            if (!$saved) {
                $this->output->outln(sprintf('<<red>>Driver "%s" reported it is supported but a test write failed.<<reset>>', $driverName));
                $this->printPermissionTip();

                return false;
            }
        }

        return true;
    }

    private function printPermissionTip(): void
    {
        $this->output->outln('<<yellow>>This is almost always a cache-directory permission/ownership problem -- directories previously created by the web server or root that the CLI user cannot write into. Fix ownership on the cache path, then re-run.<<reset>>');
    }

    private function countItemsSafely(string $driverName): ?int
    {
        try {
            /** @var DriverInterface|false $driver */
            $driver = ee('speedy:DriverFactory')->getDriver($driverName, ee('speedy:Logger'));

            if (!$driver instanceof DriverInterface || !$driver->isSupported()) {
                return null;
            }

            return $driver->countItems();
        } catch (Error | Exception $e) {
            return null;
        }
    }
}
