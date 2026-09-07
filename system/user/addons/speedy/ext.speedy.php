<?php

use BoldMinded\Speedy\Library\Basee\App;
use BoldMinded\Speedy\Library\Basee\License;
use BoldMinded\Speedy\Library\Basee\Setting;
use BoldMinded\Speedy\Library\Basee\Trial;
use BoldMinded\Speedy\Library\Basee\Version;
use ExpressionEngine\Model\Category\Category;
use ExpressionEngine\Model\Channel\ChannelEntry;
use ExpressionEngine\Model\Comment\Comment;

class Speedy_ext
{
    /** @var string */
    public $name;

    /** @var string */
    public $version = SPEEDY_VERSION;

    /** @var string */
    public $description;

    /** @var string */
    public $settings_exist = 'n';

    /** @var string */
    public $docs_url = '';

    /** @var array */
    private static $deleted_entries = [];

    /** @var array */
    private static $deleted_comments = [];

    /** @var \BoldMinded\Speedy\Service\CacheBreaker */
    private $breaker;

    /** @var \BoldMinded\Speedy\Service\EscapeRepository */
    private $escapes;

    /**
     * Speedy_ext constructor.
     */
    public function __construct()
    {
        ee()->lang->loadfile('speedy');

        $this->name = lang('speedy_module_name');
        $this->description = lang('speedy_module_description');

        $this->breaker = ee('speedy:CacheBreaker');
        $this->escapes = ee('speedy:EscapeRepository');
    }

    public function core_boot()
    {
        $this->setFrontEditCookie();
        $this->updateMsmConfig();
    }

    /**
     * EE is supposed to set this, but for some reason which
     * I can't zero in on, it's not getting set and Speedy needs
     * it to check to see what it should do if the user is logged
     * in and FE is enabled and they're attempting to view a cached page.
     * See StaticCacheHelper.php for usage.
     */
    private function setFrontEditCookie(): void
    {
        try {
            if (REQ === 'PAGE'
                && defined('IS_PRO')
                && IS_PRO
                && !ee('pro:FrontEdit')->fronteditIsDisabled()
                && !array_key_exists('frontedit', $_COOKIE ?? [])
            ) {
                ee()->input->set_cookie('frontedit', 'on', 86400);
            }
        } catch (\Exception $exception) {
            ee('speedy:Logger')->error(sprintf(
                '[Speedy] Could not set frontedit cookie: %s',
                $exception->getMessage()
            ));
        }
    }

    private function updateMsmConfig(): void
    {
        $speedyMsm = ee()->config->item('speedy_msm');
        $validKeys = ['static_path', 'frontedit_check_url'];

        if ($speedyMsm && is_array($speedyMsm)) {
            $msmSettings = $speedyMsm[ee()->config->item('site_id')] ?? [];

            foreach ($msmSettings as $name => $value) {
                if (!in_array($name, $validKeys)) {
                    continue;
                }

                ee()->config->set_item('speedy_' . $name, $value);
            }
        }
    }

    /**
     * Access template data prior to template parsing.
     */
    public function template_fetch_template(array $row)
    {
        // We can't modify the template_data here so we pre-populate the
        // escape repository with tagged escapes that will be matched up
        // when processing the template in the module.
        $this->escape($row['template_data']);

        // We can modify the global variable though, so we use this opportunity
        // to perform any escapes in snippets and global variables.
        foreach (ee()->config->_global_vars as $index => $global) {
            if (is_string($global)) {
                ee()->config->_global_vars[$index] = $this->escape($global);
            }
        }
    }

    public function after_channel_entry_save(ChannelEntry $entry)
    {
        // Don't double handle deleted entries
        if (in_array($entry->getId(), self::$deleted_entries)) {
            return;
        }

        $this->breaker->breakEntryCache($entry);
    }

    public function after_channel_entry_delete(ChannelEntry $entry)
    {
        self::$deleted_entries[] = $entry->getId();

        $this->breaker->breakEntryCache($entry);
    }

    public function after_category_save(Category $category)
    {
        $this->breakCategoryCache($category);
    }

    public function after_category_delete(Category $category)
    {
        $this->breakCategoryCache($category);
    }

    private function breakCategoryCache(Category $category)
    {
        if (bool_config_item('speedy_disable_category_cache_breaking')) {
            return;
        }

        $entries = ee('Model')
            ->get('ChannelEntry')
            ->with('Categories')
            ->filter('Categories.cat_id', $category->getId())
            ->filter('status', '!=', 'closed')
            ->all();

        $allSettings = $this->breaker->getAllSettings();
        $relevantEntries = [];

        foreach ($allSettings as $settings) {
            $relevantEntries = array_merge(
                $relevantEntries,
                $this->breaker->collectRelevantEntries($entries, $settings)->indexByIds()
            );
        }

        $uniqueEntries = array_unique($relevantEntries);

        // Without a queue system this could be a lot of work in one request
        foreach ($uniqueEntries as $entry) {
            $this->breaker->breakEntryCache($entry);
        }

        $this->breaker->breakCategoryCache($category);
    }

    public function after_comment_save(Comment $comment)
    {
        // Don't double handle deleted entries
        if (in_array($comment->getId(), self::$deleted_comments)) {
            return;
        }

        $this->breaker->breakEntryCache($comment->Entry);
    }

    public function after_comment_delete(Comment $comment)
    {
        self::$deleted_comments[] = $comment->getId();

        $this->breaker->breakEntryCache($comment->Entry);
    }

    private function escape(string|null $tagdata): string
    {
        if ($tagdata && is_string($tagdata) && strpos($tagdata, '{exp:speedy:escape:') !== false) {
            preg_match_all('~\{(exp:speedy:escape:(\w+))[^}]*\}(.*)\{/\\1\}~is', $tagdata, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $tagdata = $this->escapes->create($match[3], $match[2]);
            }
        }

        return $tagdata ?? '';
    }

    public function cp_js_end(): string
    {
        $scripts = [];

        // If another extension shares the same hook
        if (ee()->extensions->last_call !== false) {
            $scripts[] = ee()->extensions->last_call;
        }

        // Don't load unnecessary files when it's a frontedit modal.
        if (App::isFrontEditRequest()) {
            return implode('', $scripts);
        }

        $modules[] = $this->versionCheck();

        return implode('', $scripts) . implode('', $modules);
    }

    private function versionCheck(bool $checkForUpdates = true): string
    {
        if ($checkForUpdates) {
            $version = new Version();
            $latest = $version->setAddon('speedy')->fetchLatest();

            if (isset($latest->version) && version_compare($latest->version, SPEEDY_VERSION, '>')) {
                /** @var Setting $setting */
                $setting = ee('speedy:Setting');

                $url = sprintf('https://boldminded.com/account/licenses?l=%s', $setting->get('license'));
                $script = License::getUpdateAvailableNotice('speedy', $url);
                return preg_replace("/\s+/", " ", $script);
            }
        }

        return '';
    }
}
