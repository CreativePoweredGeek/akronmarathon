<?php

use BoldMinded\Speedy\Library\Basee\Setting;
use BoldMinded\Speedy\Library\Basee\Version;
use BoldMinded\Speedy\Queue\Jobs\BreakCacheJob;
use BoldMinded\Speedy\Queue\Jobs\PurgeUrlJob;
use BoldMinded\Speedy\Queue\QueueableTrait;
use BoldMinded\Speedy\Service\Diagnostics;
use BoldMinded\Speedy\Service\Drivers\RedisDriver;
use BoldMinded\Speedy\Service\Drivers\StaticDriver;
use BoldMinded\Speedy\Framework\ControlPanel;
use BoldMinded\Speedy\Model\CacheBreaking;
use BoldMinded\Speedy\Service\DriverFactory;
use BoldMinded\Speedy\Service\Drivers\AbstractDriver;
use BoldMinded\Speedy\Service\Drivers\ConfigurableDriverInterface;
use BoldMinded\Speedy\Service\Drivers\DummyDriver;
use BoldMinded\Speedy\Service\Drivers\ItemNotFoundException;
use BoldMinded\Speedy\Service\Drivers\ReadableDriverInterface;
use BoldMinded\Speedy\Service\Drivers\Support\SupportValidator;
use BoldMinded\Speedy\Service\Logger\Logger;
use BoldMinded\Speedy\Service\Purgers\PurgerFactory;
use BoldMinded\Speedy\Service\Purgers\UrlCollector;
use BoldMinded\Speedy\Service\Request\Facade;
use BoldMinded\Speedy\Service\SpeedyUtil;
use ExpressionEngine\Library\CP\Table;
use ExpressionEngine\Library\CP\URL;
use ExpressionEngine\Service\Sidebar\Sidebar;

class Speedy_mcp extends ControlPanel
{
    use QueueableTrait;

    const FILE_CONFIGURED = 'configured.txt';

    const DRIVER_ITEMS_PER_PAGE = 100;

    const DRIVER_ITEMS_SEARCH_MIN = 3;

    private Diagnostics $diagnostics;
    private DriverFactory $drivers;
    private Facade $request;
    private Setting $setting;
    private Logger $logger;
    private int $siteId = 1;

    public function __construct()
    {
        parent::__construct(SPEEDY_CLASS_NAME);

        $this->diagnostics = ee('speedy:Diagnostics');
        $this->drivers = ee('speedy:DriverFactory');
        $this->request = ee('speedy:Request');
        $this->setting = ee('speedy:Setting');
        $this->logger = ee('speedy:Logger');
        $this->siteId = (int) ee()->config->item('site_id');

        $this->addAssets();
        $this->makeSidebar();
        $this->addDisabledWarning();
        $this->addBreadcrumb('speedy', $this->makeUrl());
    }

    private function getEncryptionKey()
    {
        if (ee()->config->item('encryption_key')) {
            return ee()->config->item('encryption_key');
        }

        return $this->setting->get('installed_date');
    }
    private function makeCsrfUrl(string $action = '', array $params = []): URL
    {
        if (defined('CSRF_TOKEN')) {
            $params['token'] = CSRF_TOKEN;
        }

        return $this->makeUrl($action, $params);
    }

    private function assertCsrfToken(): void
    {
        if (!defined('CSRF_TOKEN') || !$this->hasValidCsrfToken()) {
            show_error(lang('unauthorized_access'), 403);
        }
    }

    private function hasValidCsrfToken(): bool
    {
        $candidates = [
            ee()->input->get_post('token'),
            ee()->input->post('csrf_token'),
            ee()->input->post('XID'),
            ee()->input->server('HTTP_X_CSRF_TOKEN'),
            ee()->input->server('HTTP_X_EEXID'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && SpeedyUtil::hashEquals(CSRF_TOKEN, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function index()
    {
        $this->setHeading(lang('speedy_mcp_home'));

        $cacheTableData = $this->buildCacheTableData();
        $cacheData = $this->makeTable($cacheTableData, [
            'speedy_cache_info_driver',
            'speedy_cache_info_count',
            'speedy_cache_info_status' => [
                'encode' => false,
            ],
            'speedy_cache_info_manage' => [
                'type' => Table::COL_TOOLBAR,
            ],
        ]);

        $diagnosticsInfo = null;
        if ($this->diagnostics->isEnabled()) {
            $diagnosticsTableData = $this->buildDiagnosticsTableData(10);
            $diagnosticsInfo = $this->makeTable($diagnosticsTableData, [
                'speedy_diagnostics_info_key',
                'speedy_diagnostics_info_query_count',
                'speedy_diagnostics_info_execution_time',
                'speedy_diagnostics_info_manage' => [
                    'type' => Table::COL_TOOLBAR,
                ],
            ]);
        }

        $flushDriver = [
            'base_url' => $this->makeUrl('flush_driver'),
            'choices' => $this->getSelectDriverChoices(),
        ];

        $flushAll = [
            'base_url' => $this->makeUrl('flush_all'),
        ];

        $purger = (new PurgerFactory())->create();

        $purgeAll = [
            'base_url' => $this->makeUrl('purge_all'),
        ];

        $refreshAll = [
            'base_url' => $this->makeUrl('refresh_data'),
        ];

        $clearTags = [
            'base_url' => $this->makeUrl('tag_clearing'),
        ];

        $actions = [
            'break_cache' => $this->request->getActionUrl(SPEEDY_NAME, '_break_cache'),
        ];

        $this->showClearActionAlert($actions['break_cache']);
        $this->showFronteditCompatibilityAlert();
        $this->showCreateStaticFilesAlert();
        $this->showRecreateStaticFilesAlert();
        $this->showMsmAlert();
        $this->showQueueAvailableAlert();
        $this->showQueueEnabledAlert();

        $sites = ee('Model')
            ->get('Site')
            ->all()
            ->getDictionary('site_id', 'site_label');

        return $this->render('index', [
            'cacheData' => $cacheData->viewData(),
            'diagnosticsData' => $diagnosticsInfo ? $diagnosticsInfo->viewData() : [],
            'flushDriver' => $flushDriver,
            'flushAll'  => $flushAll,
            'purgeAll'  => $purgeAll,
            'refreshAll' => $refreshAll,
            'clearTags' => $clearTags,
            'actions' => $actions,
            'purger' => $purger,
            'hasMultipleSites' => count($sites) > 1,
            'sites' => $sites,
            'submitUrl' => $this->makeUrl('clear_form'),
            'ffEnableMsmClearing' => bool_config_item('speedy_enable_msm_clearing')
        ]);
    }

    public function clear_form()
    {
        $siteIds = ee('Request')->post('clear_site_ids') ?: [
            $this->siteId
        ];

        if (ee('Request')->post('actionRefresh')) {
            $this->refresh_data($siteIds);
        }

        if (ee('Request')->post('actionFlushDriver')) {
            // Already CSRF-protected by EE's automatic POST check.
            $this->flush_driver($siteIds, true);
        }

        if (ee('Request')->post('actionFlushAll')) {
            // Already CSRF-protected by EE's automatic POST check.
            $this->flush_all($siteIds, true);
        }

        if (ee('Request')->post('actionPurgeAll')) {
            $this->purge_all($siteIds);
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

    public function release_notes()
    {
        $this->setHeading(lang('speedy_mcp_release_notes'));

        try {
            $version = new Version();
            $allVersions = $version->setAddon('speedy')->fetchAll();

            $releases = [];

            foreach ($allVersions as $version) {
                $releases[] = [
                    'date' => $version->dateFormatted,
                    'version' => $version->version,
                    'notes' => html_entity_decode($version->notes),
                    'isNew' => version_compare($version->version, SPEEDY_VERSION, '>'),
                    'currentVersion' => SPEEDY_VERSION,
                ];
            }

            $vars['releases'] = $releases;
            $vars['message'] = '';

            if (isset($releases[0]['isNew']) && $releases[0]['isNew']) {
                $vars['message'] = ee('CP/Alert')->makeInline('speedy-releases')
                    ->asAttention()
                    ->cannotClose()
                    ->withTitle('Stay up-to-date!')
                    ->addToBody('The latest version of Speedy can be downloaded from your <a href="https://boldminded.com/account/licenses">BoldMinded account</a>')
                    ->render();
            }

        } catch (\Exception $e) {
            $vars['releases'] = [];
            $vars['message'] = ee('CP/Alert')->makeInline('speedy-releases')
                ->asIssue()
                ->cannotClose()
                ->withTitle('Could not load release notes.')
                ->addToBody($e->getMessage())
                ->addToBody('View the release notes at <a href="https://boldminded.com/account/licenses">https://boldminded.com/add-ons/speedy/change-log</a>')
                ->render();
        }

        $this->addBreadcrumb(lang('speedy_mcp_release_notes'), $this->makeUrl('releases'));

        return $this->render('release_notes', $vars);
    }

    public function diagnostics(): array
    {
        $this->setHeading(lang('speedy_mcp_diagnostics'));

        $limit = 25;
        $page = ee()->input->get('page') ? (int) ee()->input->get('page') : 1;
        $totalRows = $this->diagnosticsQuery()->count();

        $diagnosticsData = $this->buildDiagnosticsTableData($limit, $page);
        $diagnosticsInfo = $this->makeTable($diagnosticsData, [
            'speedy_diagnostics_info_key',
            'speedy_diagnostics_info_query_count',
            'speedy_diagnostics_info_execution_time',
            'speedy_diagnostics_info_manage' => [
                'type' => Table::COL_TOOLBAR,
            ],
        ], [
            'autosort' => false,
            'autosearch' => false,
            'limit' => $limit,
            'sort_dir' => 'desc',
            'sort_col' => 'execution_time',
        ]);

        $diagnosticsViewData = $diagnosticsInfo->viewData();

        $vars['delete_all_url'] = $this->makeCsrfUrl('delete_all_diagnostics');
        $vars['diagnosticsData'] = $diagnosticsViewData;
        $vars['pagination'] = ee('CP/Pagination', $totalRows)
            ->perPage($diagnosticsViewData['limit'])
            ->currentPage($diagnosticsViewData['page'])
            ->render($this->makeUrl('diagnostics'));

        $this->addBreadcrumb(lang('speedy_mcp_diagnostics'), $this->makeUrl('diagnostics'));

        return $this->render('diagnostics', $vars);
    }

    public function delete_diagnostic(): void
    {
        $this->assertCsrfToken();

        $key = ee()->input->get('key');
        $this->diagnostics->clearDiagnostics($key);

        $this->addInlineAlert()
            ->asSuccess()
            ->withTitle(lang('speedy_delete_diagnostic_success'))
            ->defer();

        ee()->functions->redirect($this->makeUrl('diagnostics'));
    }

    public function delete_all_diagnostics(): void
    {
        $this->assertCsrfToken();

        $this->diagnostics->clearAllDiagnostics();

        $this->addInlineAlert()
            ->asSuccess()
            ->withTitle(lang('speedy_delete_all_diagnostic_success'))
            ->defer();

        ee()->functions->redirect($this->makeUrl('diagnostics'));
    }

    public function diagnostic_item(): array
    {
        $key = ee()->input->get('key');
        $this->setHeading('speedy_diagnostic_item');
        $this->addBreadcrumb('speedy_diagnostic_item', $this->makeUrl('diagnostic_item', ['key' => $key]));

        try {
            $item = $this->diagnostics->getDiagnostic($key);
        } catch (ItemNotFoundException $e) {
            show_error(sprintf(
                lang('speedy_diagnostic_item_not_found'),
                $key
            ));
        }

        $tableData = [];

        if ($item->queries) {
            $queries = json_decode($item->queries, true);


            foreach ($queries as $query) {
                $tableData[] = [
                    'attrs' => [],
                    'columns' => [
                        '<div class="query">
                        <textarea rows="3">' . $query[0] .'</textarea>
                     </div>
                     <div class="caller" style="margin-top: 1em; color: var(--ee-text-secondary)">' . $query[1] . '</div>' ?? '',
                        '<div class="time">' . round($query[2], 5) .'</div>' ?? '',
                    ]
                ];
            }
        }

        $queriesTable = $this->makeTable($tableData, [
            'speedy_diagnostic_query' => [
                'encode' => false,
            ],
            'speedy_diagnostic_time' => [
                'encode' => false,
            ],
        ]);

        $cacheItem = null;
        $driver = $this->drivers->getDriver($item->driver);

        if ($driver) {
            $cacheItem = $driver->getItem($key);
        }

        if (!$cacheItem) {
            show_error(sprintf(
                lang('speedy_diagnostic_item_not_found'),
                $key
            ));
        }

        $itemUrl = $this->makeUrl('driver_item/' . $item->driver, ['path' => $key]);

        if (!$cacheItem->isHit()) {
            $itemUrl = '';

            $this->addInlineAlert()
                ->asImportant()
                ->withTitle(lang('speedy_diagnostic_item_miss'))
                ->now();
        }

        return $this->render('diagnostic_item', [
            'key'           => $key,
            'back_btn'      => lang('speedy_driver_item_back'),
            'back_btn_href' => $this->makeUrl('diagnostics'),
            'item_url'      => $itemUrl,
            'diagnostic'    => $item,
            'driver'        => $item->driver,
            'execution_time'=> $item->execution_time,
            'should_save_queries' => $this->diagnostics->shouldSaveQueries(),
            'queries'       => $queriesTable->viewData(),
            'query_count'   => $item->query_count,
            'delete_url'    => $this->makeCsrfUrl('delete_diagnostic', ['key' => $key])
        ]);
    }

    public function purger(): array
    {
        $this->setHeading(lang('speedy_mcp_purger'));
        $this->addBreadcrumb(lang('speedy_mcp_purger'), $this->makeUrl('purger'));

        $settings = json_decode($this->setting->get('settings_purger'), true) ?: [];
        $activePurger = ee()->input->get('provider') ?: ($settings['provider'] ?? '');

        ee()->cp->add_to_head('<script>
            document.addEventListener("DOMContentLoaded", function() {
                $(document).on("change", "input[name=\'purger[provider]\']", function() {
                    var url = "' . $this->makeUrl('purger') . '&provider=" + encodeURIComponent(this.value);
                    window.location.href = url;
                });
            });
        </script>');

        $sections = [
            [
                [
                    'title'  => 'Reverse Proxy Purger',
                    'desc'   => 'The purger for clearing the cache on a reverse proxy. See the <a href="https://docs.boldminded.com/speedy/docs">documentation</a> for how to add your own.',
                    'fields' => [
                        'purger[provider]' => [
                            'type' => 'dropdown',
                            'choices' => PurgerFactory::providers(),
                            'value' => $activePurger,
                        ],
                    ],
                ],
                [
                    'title'  => 'Cache Control',
                    'desc'   => 'Choose which cache headers to set. See the <a href="https://docs.boldminded.com/speedy/docs">documentation</a> for details.',
                    'fields' => [
                        'purger[cache_control]' => [
                            'type' => 'dropdown',
                            'choices' => [
                                'default' => 'Default &mdash; Uses ExpressionEngine cache headers and cache nothing.',
                                'partial' => 'Partial &mdash; Cloudflare & CDNs will cache pages, but browsers will not.',
                                'full' => 'Full &mdash; Browser, Cloudflare & CDNs will cache pages.',
                            ],
                            'value' => $settings['cache_control'] ?? '',
                        ],
                    ],
                ],
            ],
        ];

        if ($activePurger) {
            $provider = (new PurgerFactory($activePurger, $settings))->create();
            $sections = array_merge($sections, $provider->settings());
        }

        if (!empty($_POST['purger'])) {
            $postValues = ee()->input->post('purger');

            $this->setting->save([
                'settings_purger' => json_encode($postValues),
            ]);

            $this->addInlineAlert()
                ->asSuccess()
                ->withTitle(lang('speedy_purger_saved'))
                ->addToBody(lang('speedy_purger_saved_desc'))
                ->defer();

            ee()->functions->redirect($this->makeUrl('purger'));
        }

        return $this->render('purger', [
            'base_url'              => $this->makeUrl('purger'),
            'save_btn_text'         => 'btn_save_settings',
            'save_btn_text_working' => 'btn_saving',
            'sections'              => $sections,
        ]);
    }

    public function cache_clearing(): array
    {
        $loopbackTestUrl = $this->request->getActionUrl(SPEEDY_NAME, '_loopback_test');
        $canRefresh = $this->request->get($loopbackTestUrl);

        if (!$canRefresh) {
            $this->addInlineAlert()
                ->asWarning()
                ->cannotClose()
                ->withTitle(lang('speedy_loopback_check'))
                ->addToBody(lang('speedy_loopback_check_desc'))
                ->now();
        }

        $this->setHeading('speedy_mcp_cache_clearing');
        $this->addBreadcrumb('speedy_mcp_cache_clearing', $this->makeUrl('cache_clearing'));

        return $this->render('cache_clearing', [
            'channelTable' => $this->channelRulesTable()->viewData($this->makeUrl('cache_clearing')),
            'categoryGroupTable' => $this->categoryGroupRulesTable()->viewData($this->makeUrl('cache_clearing')),
        ]);
    }

    private function tableStatusOptions(): array
    {
        return [
            'yes'  => [
                'class'   => 'open',
                'content' => '<i class="fas fa-thumbs-up publisher-icon" style="color: var(--ee-success)" aria-hidden="true"></i>',
            ],
            'no' => [
                'class'   => 'closed',
                'content' => '<i class="fas fa-thumbs-down publisher-icon" aria-hidden="true"></i>',
            ],
        ];
    }

    private function channelRulesTable(): Table
    {
        $tableRows = [];

        // Add table row for "Any Channel"
        /** @var \BoldMinded\Speedy\Model\CacheBreaking $settings */
        $settings = ee('Model')->get('speedy:CacheBreaking')
            ->filter('entity_id', 0)
            ->filter('entity_type', 'channel')
            ->first();

        if ($settings === null) {
            $settings = ee('Model')->make('speedy:CacheBreaking');
        }

        $tableStatusOptions = $this->tableStatusOptions();

        $tableRows[] = [
            lang('speedy_any_channel'),
            $settings->refresh ? $tableStatusOptions['yes'] : $tableStatusOptions['no'],
            count($settings->tags),
            count($settings->items),
            [
                'toolbar_items' => [
                    'settings' => ['href' => $this->makeUrl('cache_clearing_settings/channel'), 'title' => lang('speedy_clearing_settings')],
                ],
            ],
        ];

        $channels = ee('Model')->get('Channel')
            ->filter('site_id', $this->siteId)
            ->order('channel_title', 'asc')
            ->all();

        // Add table row for each channel
        /** @var \ExpressionEngine\Model\Channel\Channel $channel */
        foreach ($channels as $channel) {
            /** @var \BoldMinded\Speedy\Model\CacheBreaking $settings */
            $settings = ee('Model')->get('speedy:CacheBreaking')
                ->filter('entity_id', $channel->channel_id)
                ->filter('entity_type', 'channel')
                ->first();

            if ($settings === null) {
                $settings = ee('Model')->make('speedy:CacheBreaking');
            }

            $tableRows[] = [
                $channel->channel_title,
                $settings->refresh ? $tableStatusOptions['yes'] : $tableStatusOptions['no'],
                count($settings->tags),
                count($settings->items),
                [
                    'toolbar_items' => [
                        'settings' => ['href' => $this->makeUrl('cache_clearing_settings/channel/' . $channel->channel_id), 'title' => lang('speedy_clearing_settings')],
                    ],
                ],
            ];
        }

        return $this->makeTable($tableRows, [
            'speedy_channel',
            'speedy_clearing_refresh' => [
                'type' => Table::COL_STATUS,
            ],
            'speedy_clearing_tags',
            'speedy_clearing_items',
            'speedy_manage'           => [
                'type' => Table::COL_TOOLBAR,
            ],
        ]);
    }

    private function categoryGroupRulesTable(): Table
    {
        $tableRows = [];

        // Add table row for "Any Channel"
        /** @var \BoldMinded\Speedy\Model\CacheBreaking $settings */
        $settings = ee('Model')->get('speedy:CacheBreaking')
            ->filter('entity_id', 0)
            ->filter('entity_type', 'category_group')
            ->first();

        if ($settings === null) {
            $settings = ee('Model')->make('speedy:CacheBreaking');
        }

        $tableStatusOptions = $this->tableStatusOptions();

        $tableRows[] = [
            lang('speedy_any_category_group'),
            $settings->refresh ? $tableStatusOptions['yes'] : $tableStatusOptions['no'],
            count($settings->tags),
            count($settings->items),
            [
                'toolbar_items' => [
                    'settings' => ['href' => $this->makeUrl('cache_clearing_settings/category_group'), 'title' => lang('speedy_clearing_settings')],
                ],
            ],
        ];

        $categoryGroups = ee('Model')->get('CategoryGroup')
            ->filter('site_id', $this->siteId)
            ->order('group_name', 'asc')
            ->all();

        foreach ($categoryGroups as $categoryGroup) {
            /** @var \BoldMinded\Speedy\Model\CacheBreaking $settings */
            $settings = ee('Model')->get('speedy:CacheBreaking')
                ->filter('entity_id', $categoryGroup->group_id)
                ->filter('entity_type', 'category_group')
                ->first();

            if ($settings === null) {
                $settings = ee('Model')->make('speedy:CacheBreaking');
            }

            $tableRows[] = [
                $categoryGroup->group_name,
                $settings->refresh ? $tableStatusOptions['yes'] : $tableStatusOptions['no'],
                count($settings->tags),
                count($settings->items),
                [
                    'toolbar_items' => [
                        'settings' => ['href' => $this->makeUrl('cache_clearing_settings/category_group/' . $categoryGroup->group_id), 'title' => lang('speedy_clearing_settings')],
                    ],
                ],
            ];
        }

        return $this->makeTable($tableRows, [
            'speedy_channel',
            'speedy_clearing_refresh' => [
                'type' => Table::COL_STATUS,
            ],
            'speedy_clearing_tags',
            'speedy_clearing_items',
            'speedy_manage'           => [
                'type' => Table::COL_TOOLBAR,
            ],
        ]);
    }

    public function cache_clearing_settings(string $entityType = 'channel', int $entityId = 0): array
    {
        if ($entityType && $entityId !== 0) {
            if ($entityType === 'category_group') {
                $entity = ee('Model')->get('CategoryGroup')
                    ->filter('group_id', $entityId)
                    ->first();

                if (!$entity) {
                    show_error(lang('unauthorized_access'), 403);
                    exit;
                }

                $title = $entity->group_name;
            } else {
                $entity = ee('Model')->get('Channel')
                    ->filter('channel_id', $entityId)
                    ->first();

                if (!$entity) {
                    show_error(lang('unauthorized_access'), 403);
                    exit;
                }

                $title = $entity->channel_title;
            }
        } elseif ($entityType && $entityId === 0) {
            if ($entityType === 'category_group') {
                $title = lang('speedy_any_category_group');
            } else {
                $title = lang('speedy_any_channel');
            }
        } else {
            show_error(lang('unauthorized_access'), 403);
            exit;
        }

        if ($entityId === 0) {
            $heading = sprintf(lang('speedy_mcp_cache_clearing_settings_' . $entityType), lang('speedy_any'));
        } else {
            $heading = sprintf(lang('speedy_mcp_cache_clearing_settings_' . $entityType), $title);
        }

        $this->setHeading($heading);
        $this->addBreadcrumb('speedy_mcp_cache_clearing', $this->makeUrl('cache_clearing'));

        /** @var \BoldMinded\Speedy\Model\CacheBreaking $settings */
        $settings = ee('Model')->get('speedy:CacheBreaking')
            ->filter('entity_id', $entityId)
            ->filter('entity_type', $entityType)
            ->first();

        if ($settings === null) {
            $settings = ee('Model')->make('speedy:CacheBreaking');
            $settings->entity_id = $entityId;
            $settings->entity_type = $entityType;
        }

        $formErrors = null;

        if (!empty($_POST)) {
            // Allow no selections to pass validation
            if (ee()->input->post('statuses') === '') {
                $_POST['statuses'] = [];
            }
            if (ee()->input->post('categories') === '') {
                $_POST['categories'] = [];
            }

            /** @var \ExpressionEngine\Service\Validation\Result $result */
            $settings->set($_POST);
            $result = $settings->validate();

            if (!$result->isValid()) {
                $formErrors = $result->renderErrors();
            }

            $settings->tags = $this->validateCacheBreakingTagsGrid($settings, $formErrors);
            $settings->items = $this->validateCacheBreakingItemsGrid($settings, $formErrors);
            $settings->statuses = $this->validateArraySettings($settings->statuses);
            $settings->categories = $this->validateArraySettings($settings->categories);

            if (!$formErrors) {
                $settings->save();

                $this->addInlineAlert()
                    ->asSuccess()
                    ->withTitle(lang('speedy_clearing_saved'))
                    ->addToBody(sprintf(lang('speedy_clearing_saved_desc'), $title))
                    ->now();
            } else {
                ee()->load->library('form_validation');
                ee()->form_validation->_error_array = $formErrors;

                if (isset(ee()->form_validation->_error_array['tags_grid'])) {
                    unset(ee()->form_validation->_error_array['tags_grid']);
                    ee()->form_validation->_error_array['tags'] = 'dummy';
                }

                if (isset(ee()->form_validation->_error_array['items_grid'])) {
                    unset(ee()->form_validation->_error_array['items_grid']);
                    ee()->form_validation->_error_array['items'] = 'dummy';
                }

                $this->addInlineAlert()
                    ->asIssue()
                    ->withTitle(lang('speedy_clearing_not_saved'))
                    ->addToBody(lang('speedy_clearing_not_saved_desc'))
                    ->now();
            }
        }

        $tagsGrid = $this->makeCacheBreakingTagsGrid($settings, $formErrors);
        $itemsGrid = $this->makeCacheBreakingItemsGrid($settings, $formErrors);

        if ($entityType === 'channel') {
            if ($entityId) {
                $channel = ee('Model')->get('Channel', $entityId)->first();
                $channelStatuses = $channel->Statuses->getDictionary('status_id', 'status') ?? [];
                $categories = [];

                // Because we have to support PHP <8 and can't do $channel?->
                if ($channel) {
                    $channelStatuses = $channel->Statuses->getDictionary('status_id', 'status');
                }

                $categoryGroups = $channel->CategoryGroups;
                foreach ($categoryGroups as $group) {
                    $categories += $group->Categories->getDictionary('cat_id', 'cat_name');
                }
            } else {
                $channelStatuses = ee('Model')->get('Status')->all()->getDictionary('status_id', 'status');
                $categories = ee('Model')->get('Category')->all()->getDictionary('cat_id', 'cat_name');
            }
        }

        $fieldSettings = [
            [
                'title'  => 'speedy_clearing_refresh',
                'desc'   => 'speedy_clearing_refresh_desc',
                'fields' => [
                    'refresh' => [
                        'type'     => 'yes_no',
                        'value'    => $settings->refresh,
                        'required' => true,
                    ],
                ],
            ],
        ];

        if ($entityType === 'channel') {
            $fieldSettings[] = [
                'title'  => 'speedy_clearing_statuses',
                'desc'   => 'speedy_clearing_statuses_desc',
                'fields' => [
                    'statuses' => [
                        'type'     => 'checkbox',
                        'choices'  => $channelStatuses,
                        'value'    => $settings->statuses,
                    ],
                ],
            ];

            $fieldSettings[] = [
                'title'  => 'speedy_clearing_categories',
                'desc'   => 'speedy_clearing_categories_desc',
                'fields' => [
                    'categories' => [
                        'type'     => 'checkbox',
                        'choices'  => $categories,
                        'value'    => $settings->categories,
                    ],
                ],
            ];
        }

        $tabs[lang('speedy_clearing_settings_tab')] = ee('View')
            ->make('ee:_shared/form/section')
            ->render(['name' => lang('speedy_clearing_settings_tab'), 'settings' => $fieldSettings]);

        $tabs[lang('speedy_clearing_tags')] = ee('View')
            ->make('ee:_shared/form/section')
            ->render(['name' => lang('speedy_clearing_tags'), 'settings' => [[
                'desc' => $this->addInlineAlert('alert-desc-cont')
                    ->asTip()
                    ->cannotClose()
                    ->addToBody(lang('speedy_clearing_tags_desc_' . $entityType))
                    ->addToBody(lang('speedy_clearing_tags_desc_cont_' . $entityType))
                    ->render(),
                'grid'      => true,
                'fields'    => [
                    'tags' => [
                        'type'    => 'html',
                        'content' => ee()->load->view('_shared/table', $tagsGrid->viewData(), true),
                    ],
                ],
            ]]]);

        $tabs[lang('speedy_clearing_items')] = ee('View')
            ->make('ee:_shared/form/section')
            ->render(['name' => lang('speedy_clearing_items'), 'settings' => [[
                'desc_cont' => $this->addInlineAlert('alert-desc-cont')
                    ->asTip()
                    ->cannotClose()
                    ->addToBody(lang('speedy_clearing_items_desc_' . $entityType))
                    ->addToBody(lang('speedy_clearing_items_desc_cont_' . $entityType))
                    ->render(),
                'grid'      => true,
                'fields'    => [
                    'items' => [
                        'type'    => 'html',
                        'content' => ee()->load->view('_shared/table', $itemsGrid->viewData(), true),
                    ],
                ],
            ]]]);

        return $this->render('cache_clearing_settings', [
            'base_url'              => $this->makeUrl('cache_clearing_settings/' . $entityType . '/' . $entityId),
            'save_btn_text'         => 'btn_save_settings',
            'save_btn_text_working' => 'btn_saving',
            'sections'              => [],
            'tabs'                  => $tabs,
        ]);
    }

    private function showClearActionAlert(string $breakCacheUrl): void
    {
        $secret = ee()->config->item('speedy_secret');

        if ($secret === false || trim((string) $secret) === '') {
            $this->addInlineAlert('alert-clear-cache-url')
                ->asWarning()
                ->cannotClose()
                ->withTitle(lang('speedy_cache_clear_url'))
                ->addToBody(
                    sprintf(lang('speedy_cache_clear_url_no_secret_desc'), $breakCacheUrl)
                )
                ->now();
        } else {
            $this->addInlineAlert('alert-clear-cache-url')
                ->asTip()
                ->cannotClose()
                ->withTitle(lang('speedy_cache_clear_url'))
                ->addToBody(
                    sprintf(lang('speedy_cache_clear_url_desc'), $breakCacheUrl)
                )
                ->now();
        }
    }

    private function showFronteditCompatibilityAlert(): void
    {
        $speedyMsm = ee()->config->item('speedy_msm');

        // If MSM specific config is defined just check for the existence of the action ID. Unfortunately at this point
        // there is no way to know what the URLs are to other MSM sites. Sites don't have URLs defined for them in any
        // config other than what might be in an $assign_to_config['site_url'] in a index.php file, which we don't
        // know the paths too.
        if ($speedyMsm && is_array($speedyMsm)) {
            $actionId = $this->request->fetchActionId(SPEEDY_NAME, '_is_frontedit_enabled');

            foreach ($speedyMsm as $settings) {
                $frontEditUrl = $settings['frontedit_check_url'] ?? '';

                if (!preg_match('/ACT=' . $actionId .'/', $frontEditUrl)) {
                    $message = sprintf(
                        lang('speedy_frontedit_check_msm_desc'),
                        $actionId,
                        $actionId
                    );

                    if ($this->shouldCreateStaticFiles()) {
                        $message = sprintf(
                            lang('speedy_frontedit_check_msm_desc') . lang('speedy_frontedit_check_static_msm_desc'),
                            $actionId,
                            $actionId,
                            $this->getStaticCachePath(),
                            $this->makeUrl('create_static_files', ['regenerate' => 1])
                        );
                    }

                    $this->addInlineAlert('alert-frontedit-check')
                        ->asIssue()
                        ->cannotClose()
                        ->withTitle(lang('speedy_frontedit_check'))
                        ->addToBody($message)
                        ->now();
                }
            }

            return;
        }

        $checkUrl = $this->request->getActionUrl(SPEEDY_NAME, '_is_frontedit_enabled');
        $configCheckUrl = ee()->config->item('speedy_frontedit_check_url');
        $isCheckUrlValid = $configCheckUrl !== false && $configCheckUrl === $checkUrl;

        if (defined('IS_PRO') && IS_PRO && !$isCheckUrlValid) {
            /** @var \BoldMinded\Speedy\Service\Request\Facade $request */

            $message = sprintf(
                lang('speedy_frontedit_check_desc'),
                $checkUrl
            );

            if ($this->shouldCreateStaticFiles()) {
                $message = sprintf(
                    lang('speedy_frontedit_check_desc') . lang('speedy_frontedit_check_static_desc'),
                    $this->request->getActionUrl(SPEEDY_NAME, '_is_frontedit_enabled'),
                    $this->getStaticCachePath(),
                    $this->makeUrl('create_static_files', ['regenerate' => 1])
                );
            }

            $this->addInlineAlert('alert-frontedit-check')
                ->asIssue()
                ->cannotClose()
                ->withTitle(lang('speedy_frontedit_check'))
                ->addToBody($message)
                ->now();
        }
    }

    private function showCreateStaticFilesAlert(): void
    {
        if (!$this->shouldCreateStaticFiles()) {
            return;
        }

        $this->addInlineAlert('alert-static-files')
            ->asIssue()
            ->withTitle(lang('speedy_static_files_not_created'))
            ->addToBody(sprintf(
                lang('speedy_static_files_not_created_desc'),
                $this->getStaticCachePath(),
                $this->makeUrl('create_static_files')
            ))
            ->now();
    }

    private function showRecreateStaticFilesAlert(): void
    {
        if (!$this->shouldCreateStaticFiles()) {
            return;
        }

        $this->addInlineAlert('alert-static-files')
            ->asIssue()
            ->cannotClose()
            ->withTitle(lang('speedy_mcp_regenerate'))
            ->addToBody(sprintf(
                lang('speedy_static_files_recreate_desc'),
                $this->getStaticCachePath(),
                $this->makeUrl('create_static_files', ['regenerate' => 1])
            ))
            ->now();
    }

    private function showMsmAlert(): void
    {
        $sites = ee('Model')->get('Site')->all();
        if (count($sites) === 1) {
            return;
        }

        $currentSite = ee('Model')->get('Site')->filter('site_id', $this->siteId)->first();

        $this->addInlineAlert('alert-msm')
            ->asAttention()
            ->cannotClose()
            ->withTitle(lang('speedy_mcp_msm'))
            ->addToBody(sprintf(
                lang('speedy_mcp_msm_desc'),
                $currentSite->site_label
            ))
            ->now();
    }

    private function showQueueEnabledAlert(): void
    {
        if (!$this->shouldUseQueue()) {
            return;
        }

        $this->addInlineAlert('alert-queue')
            ->asAttention()
            ->cannotClose()
            ->withTitle(lang('speedy_mcp_queue_enabled'))
            ->addToBody(lang('speedy_mcp_queue_enabled_desc'))
            ->now();
    }

    private function showQueueAvailableAlert(): void
    {
        if (
            $this->isQueueAvailable()
            && !$this->shouldUseQueue()
        ) {
            $this->addInlineAlert('alert-queue')
                ->asAttention()
                ->cannotClose()
                ->withTitle(lang('speedy_mcp_queue_available'))
                ->addToBody(lang('speedy_mcp_queue_available_desc'))
                ->now();
        }
    }

    /**
     * @param bool $forceRecreate
     * @return bool
     */
    private function shouldCreateStaticFiles(bool $forceRecreate = false): bool
    {
        $settings = $this->getDriverSettings('redis');
        $currentDriver = ee()->config->item('speedy_driver');
        $isStatic = $currentDriver === 'static';
        $isRedisAsStatic = $currentDriver === 'redis' && isset($settings['static']) && get_bool_from_string($settings['static']);
        $siteShortName = ee()->config->item('site_short_name');
        $configuredFileExists = file_exists($this->getStaticUtilitiesCachePath() . self::FILE_CONFIGURED);
        $indexFileExists = file_exists($this->getDocumentRootPath() . 'index_redis_' . $siteShortName . '.php');
        $settingsCacheExists = $this->setting->get('settings_cache');

        if ($isStatic || $isRedisAsStatic) {
            if ($forceRecreate
                || !$settingsCacheExists
                || !$configuredFileExists
                || ($isRedisAsStatic && !$indexFileExists)
                || $this->isSettingsFromCacheUpdated()
            ) {
                return true;
            }
        }

        return false;
    }

    private function getStaticCachePath(): string
    {
        $staticDriver = new StaticDriver();
        return $staticDriver->getCachePath() . '/';
    }

    private function getDocumentRootPath(): string
    {
        $staticDriver = new StaticDriver();
        return $staticDriver->getDocumentRootPath() . '/';
    }

    /**
     * Even if using Redis, the StaticDriver can get the path. RedisDriver does not know the filesystem
     * path, b/c it doesn't cache anything on the filesystem, but it needs to use the php files located there.
     *
     * @return string
     */
    private function getStaticUtilitiesCachePath()
    {
        return $this->getStaticCachePath() . 'utilities/';
    }

    /**
     * @return string
     */
    private function getUserCachePath()
    {
        return PATH_CACHE . 'speedy/';
    }

    /**
     * @param string $driverName
     * @return array
     */
    private function getDriverSettings($driverName)
    {
        /** @var \BoldMinded\Speedy\Model\DriverConfiguration $configuration */
        $configuration = ee('Model')->get('speedy:DriverConfiguration')
            ->filter('site_id', $this->siteId)
            ->filter('driver', $driverName)
            ->first();

        $settings = [];

        if ($configuration !== null) {
            $settings = $configuration->settings;
        }

        if (ee()->config->item('speedy_'. $driverName .'_settings')) {
            $settings = ee()->config->item('speedy_'. $driverName .'_settings');
        }

        return $settings;
    }

    /**
     * @return void
     */
    public function refresh_data(
        array $siteIds = [],
    ) {
        $success = true;

        foreach ($siteIds as $siteId) {
            $siteName = $this->getSiteName($siteId);

            /** @var AbstractDriver $driver */
            foreach ($this->drivers->getEnabledDrivers() as $driver) {
                $driver->setSiteName($siteName);

                // Intentionally execute method before combining with
                // success so that we continue to track whether any driver
                // failed, but don't stop refreshing other drivers.
                $success = $driver->refresh() && $success;
            }
        }

        if ($success) {
            $this->addInlineAlert('shared-form')
                ->asSuccess()
                ->withTitle(lang('speedy_refresh_data_success'))
                ->addToBody(lang('speedy_refresh_data_success_desc'))
                ->defer();
        } else {
            $this->addInlineAlert('shared-form')
                ->asIssue()
                ->withTitle(lang('speedy_refresh_data_error'))
                ->addToBody(lang('speedy_refresh_data_error_desc'))
                ->defer();
        }

        ee()->functions->redirect($this->makeUrl());
    }

    /**
     * @return void
     */
    public function flush_driver(
        array $siteIds = [],
        bool $authorized = false
    ) {
        if (!$authorized) {
            $this->assertCsrfToken();
        }

        $success = true;
        $name = ee()->input->get_post('driver_name', true) ?? null;

        if (!$name) {
            $this->addInlineAlert()
                ->asIssue()
                ->withTitle(lang('speedy_flush_driver_error'))
                ->addToBody(lang('speedy_flush_driver_error_desc'))
                ->defer();
        }

        $driver = $this->getSupportedDriver($name);

        foreach ($siteIds as $siteId) {
            $siteName = $this->getSiteName($siteId);

            $driver->setSiteName($siteName);
            // Intentionally execute method before combining with
            // success so that we continue to track whether any driver
            // failed, but don't stop refreshing other drivers.
            $success = $driver->clear() && $success;
        }

        if ($success) {
            $this->addInlineAlert()
                ->asSuccess()
                ->withTitle(lang('speedy_flush_driver_success'))
                ->addToBody(sprintf(lang('speedy_flush_driver_success_desc'), lang('speedy_driver_' . $name)))
                ->defer();
        } else {
            $this->addInlineAlert()
                ->asIssue()
                ->withTitle(lang('speedy_flush_driver_error'))
                ->addToBody(lang('speedy_flush_driver_error_desc'))
                ->defer();
        }

        ee()->functions->redirect($this->makeUrl());
    }

    /**
     * @return void
     */
    public function flush_all(
        array $siteIds = [],
        bool $authorized = false
    ) {
        if (!$authorized) {
            $this->assertCsrfToken();
        }

        if ($this->shouldUseQueue()) {
            if (!empty($siteIds)) {
                foreach ($siteIds as $siteId) {
                    $this->pushToQueue(BreakCacheJob::class, $siteId);
                }
            } else {
                $this->pushToQueue(BreakCacheJob::class, $this->siteId);
            }

            $this->addInlineAlert()
                ->asSuccess()
                ->withTitle(lang('speedy_flush_all_success_queue'))
                ->addToBody(lang('speedy_flush_all_success_queue_desc'))
                ->defer();

            ee()->functions->redirect($this->makeUrl());

            return;
        }

        /** @var \BoldMinded\Speedy\Service\CacheBreaker $breaker */
        $breaker = ee('speedy:CacheBreaker');
        $success = $breaker->_breakCache($siteIds);

        if ($success) {
            $this->addInlineAlert()
                ->asSuccess()
                ->withTitle(lang('speedy_flush_all_success'))
                ->addToBody(lang('speedy_flush_all_success_desc'))
                ->defer();
        } else {
            $this->addInlineAlert()
                ->asIssue()
                ->withTitle(lang('speedy_flush_all_error'))
                ->addToBody(lang('speedy_flush_all_error_desc'))
                ->defer();
        }

        ee()->functions->redirect($this->makeUrl());
    }

    public function purge_all(
        array $siteIds = [],
    ) {
        $purger = (new PurgerFactory())->create();
        $success = $purger->purgeAll();

        if ($success) {
            $this->addInlineAlert()
                ->asSuccess()
                ->withTitle(lang('speedy_purge_all_success'))
                ->addToBody(sprintf(lang('speedy_purge_all_success_desc'), $purger->getName()))
                ->defer();
        } else {
            $this->addInlineAlert()
                ->asIssue()
                ->withTitle(lang('speedy_purge_all_error'))
                ->addToBody(sprintf(lang('speedy_purge_all_error_desc'), $purger->getName()))
                ->defer();
        }

        ee()->functions->redirect($this->makeUrl());
    }

    public function tag_clearing(): array
    {
        $this->setHeading(lang('speedy_tag_clearing'));

        if ($name = ee()->input->get_post('tags', true)) {
            /** @var \BoldMinded\Speedy\Service\CacheBreaker $breaker */
            $breaker = ee('speedy:CacheBreaker');

            if (!is_array($name)) {
                $name = [$name];
            }

            $siteIds = ee('Request')->post('clear_site_ids') ?: [
                $this->siteId
            ];

            foreach ($siteIds as $siteId) {
                $breaker->clearTags($name, $siteId);
            }

            ee('CP/Alert')
                ->makeInline('shared-form')
                ->asSuccess()
                ->withTitle(lang('success'))
                ->addToBody(lang('speedy_tag_clearing_success'))
                ->now();
        }

        $allTags = ee('Model')
            ->get('speedy:Tag')
            ->filter('site_id', $this->siteId)
            ->order('tag', 'asc')
            ->all()
            ->getDictionary('tag', 'tag');

        if (empty($allTags)) {
            $sections = [
                [
                    [
                        'title' => 'speedy_tag_clearing_no_tags',
                        'desc' => lang('speedy_tag_clearing_no_tags_desc'),
                        'fields' => [
                        ]
                    ],
                ],
            ];
        } else {
            $sections = [
                [
                    [
                        'title' => 'speedy_tag_clearing',
                        'desc' => lang('speedy_tag_clearing_desc'),
                        'fields' => [
                            'tags' => [
                                'type' => 'checkbox',
                                'value' => '',
                                'choices' => $allTags,
                            ]
                        ]
                    ],
                ],
            ];
        }

        $sites = ee('Model')
            ->get('Site')
            ->all()
            ->getDictionary('site_id', 'site_label');

        $ffEnableMsmClearing = bool_config_item('speedy_enable_msm_clearing');

        if ($ffEnableMsmClearing && count($sites) > 1) {
            $sections[] = [
                [
                    'title' => 'Sites',
                    'desc' => 'By default, only the current site will be cleared. You can choose to clear matching tags on other selected sites as well.',
                    'fields' => [
                        'clear_site_ids' => [
                            'type' => 'checkbox',
                            'value' => '',
                            'choices' => $sites,
                            'multi' => true,
                            'toggle_all' => true,
                            'force_react' => false,
                        ]
                    ]
                ],
            ];
        }

        $vars['sections'] = $sections;
        $vars['base_url'] = $this->makeUrl('tag_clearing');
        $vars['save_btn_text'] = lang('speedy_clear_tags_button');
        $vars['save_btn_text_working'] = lang('speedy_clear_tags_button_working');
        $vars['cp_page_title'] = '';

        return $this->render('shared_form', $vars);
    }

    /**
     * @param string $name
     * @return array
     */
    public function driver_stats($name)
    {
        $this->setHeading(sprintf(lang('speedy_driver_stats'), lang('speedy_driver_' . $name)));

        $driver = $this->getSupportedReadableDriver($name);
        $stats = $driver->getStats();

        if (!isset($stats[0]['label'])) {
            $stats = [['label' => '', 'stats' => $stats]];
        }

        $formatted = [];
        foreach ($stats as $server) {
            $data = [
                'label' => $server['label'],
                'stats' => [],
            ];
            foreach ($server['stats'] as $key => $value) {
                $data['stats'][] = [
                    'title'       => lang('speedy_driver_stats_' . $key),
                    'description' => lang('speedy_driver_stats_' . $key . '_desc'),
                    'content'     => $this->formatDriverStat($key, $value),
                ];
            }
            $formatted[] = $data;
        }

        return $this->render('driver_stats', [
            'stats'         => $formatted,
            'back_btn'      => lang('speedy_driver_stats_back'),
            'back_btn_href' => $this->makeUrl(),
        ]);
    }

    /**
     * @param string $name
     * @return array
     */
    public function driver_items($name)
    {
        $cp_page_title = lang('speedy_driver_items');
        $this->setHeading($cp_page_title);
        $this->addBreadcrumb('speedy_driver_' . $name, $this->makeUrl('driver_items/' . $name));

        $driver = $this->getSupportedReadableDriver($name);
        $path = ee()->input->get('path', true);

        $this->createBreadcumbsFromPath($name, $path);

        $limit = self::DRIVER_ITEMS_PER_PAGE;
        $page = ee()->input->get('page') ? max(1, (int) ee()->input->get('page')) : 1;
        $offset = ($page - 1) * $limit;

        $keyword = trim(ee('Request')->post('keyword') ?? '');
        $isSearch = mb_strlen($keyword) >= self::DRIVER_ITEMS_SEARCH_MIN;
        $searchAlert = '';

        if ($isSearch) {
            // Recursive, full set of matching item paths (relative to $path).
            $matches = $this->searchDriverItems($driver, $path, $keyword);
            $totalRows = count($matches);
            $pageEntries = array_slice($matches, $offset, $limit);

            // Flat results: each match links straight to its detail view. No
            // ".." row and no per-folder grouping while searching.
            $data = $this->buildDriverSearchData($driver, $path, $pageEntries);

            $searchAlert = $totalRows > 0
                ? sprintf(lang('speedy_driver_items_search_results'), $totalRows, htmlspecialchars($keyword, ENT_QUOTES))
                : sprintf(lang('speedy_driver_items_search_none'), htmlspecialchars($keyword, ENT_QUOTES));
        } else {
            // Single, alphabetical list of this level's entries. We page over it
            // before reading per-item metadata so only the visible rows hit disk.
            $entries = $this->getDriverItemsAtPath($driver, $path);
            $totalRows = count($entries);
            $pageEntries = array_slice($entries, $offset, $limit);

            $data = $this->buildDriverItemsData($driver, $path, $pageEntries, $page === 1);

            if ($keyword !== '') {
                $searchAlert = lang('speedy_driver_items_search_min');
            }
        }

        $table = $this->makeTable($data, [
            'speedy_driver_items_key',
            'speedy_driver_items_created',
            'speedy_driver_items_expires' => [
                'encode' => false,
            ],
            'speedy_driver_items_manage'  => [
                'type' => Table::COL_TOOLBAR,
            ],
        ], [
            'autosort' => false,
            'autosearch' => false,
            'limit' => $limit,
        ]);

        // Pagination links (GET) must preserve both path and keyword.
        $baseParams = [];
        if ($path !== '') {
            $baseParams['path'] = $path;
        }
        if ($isSearch) {
            $baseParams['keyword'] = $keyword;
        }

        $pagination = ee('CP/Pagination', $totalRows)
            ->perPage($limit)
            ->currentPage($page)
            ->render($this->makeUrl('driver_items/' . $name, $baseParams));

        // The search form POSTs to the current URL (action=""), like EE's own CP
        // search box. EE's route lives in the query string, which a GET form would
        // discard; POST leaves the current URL intact. The current "path" rides
        // along as a hidden field, and pagination links carry the keyword as GET.
        return $this->render('driver_items', [
            'cp_page_title' => $cp_page_title,
            'driver_items'  => $table->viewData(),
            'pagination'    => $pagination,
            'search_path'   => $path,
            'search_keyword'=> $isSearch ? $keyword : '',
            'search_alert'  => $searchAlert,
            'clear_search_url' => (string) $this->makeUrl(
                'driver_items/' . $name,
                $path !== '' ? ['path' => $path] : []
            ),
            'flush_driver'  => [
                'href'    => $this->makeCsrfUrl('flush_driver', ['driver_name' => $name]),
                'content' => lang('speedy_driver_items_clear_cache'),
            ],
        ]);
    }

    public function driver_item(string $name): array
    {
        $this->setHeading('speedy_driver_item');
        $this->addBreadcrumb('speedy_driver_' . $name, $this->makeUrl('driver_items/' . $name));

        $driver = $this->getSupportedReadableDriver($name);
        $path = ee()->input->get('path', true);

        $this->createBreadcumbsFromPath($name, $path);

        try {
            $item = $driver->getItem($path);
            $meta = $driver->getItemMetadata($path);
        } catch (ItemNotFoundException $e) {
            /* Disabled b/c I'm not sure if I like this
            if ($this->diagnostics->isEnabled() && ee()->input->get('referrer') === 'diagnostics') {
                $this->diagnostics->clearDiagnostics($path);
                $this->addInlineAlert('alert-clear-item')
                    ->asImportant()
                    ->cannotClose()
                    ->withTitle(lang('speedy_diagnostics_delete_item_success'))
                    ->addToBody(
                        sprintf(lang('speedy_diagnostics_delete_item_extended_success_desc'), $path)
                    )
                    ->defer();
                ee()->functions->redirect($this->makeUrl('diagnostics'));
            }
            */

            show_error(lang('speedy_driver_item_not_found'));
        }

        $diagnostics = [];
        $diagnosticUrl = '';

        if (ee('speedy:Diagnostics')->isEnabled()) {
            $diagnostics = $this->diagnostics->getDiagnostic($path);
            $diagnosticUrl = $this->makeUrl('diagnostic_item', ['key' => $path]);
        }

        return $this->render('driver_item', [
            'key'           => $path,
            'ttl'           => $this->formatTTL($meta[ReadableDriverInterface::META_TTL]),
            'ttl_remaining' => $meta[ReadableDriverInterface::META_TTL_REMAINING],
            'size'          => (string) ee('Format')->make('Number', $meta[ReadableDriverInterface::META_SIZE])->bytes(),
            'size_bytes'    => $meta[ReadableDriverInterface::META_SIZE],
            'content'       => $item->getValue(),
            'created_at'    => $this->formatDate($meta[ReadableDriverInterface::META_CREATED_AT]),
            'expires_at'    => $this->formatDate($meta[ReadableDriverInterface::META_EXPIRES_AT]),
            'back_btn'      => lang('speedy_driver_item_back'),
            'back_btn_href' => $this->getParentViewItemUrl($name, $path),
            'diagnostics'   => $diagnostics ? $diagnostics->toArray() : [],
            'diagnostic_url'=> $diagnosticUrl,
            'delete_url'    => $this->makeCsrfUrl('delete_item/' . $name, ['path' => $path])
        ]);
    }

    private function createBreadcumbsFromPath(string $driverName, string $path)
    {
        $pathSegments = explode('/', $path);
        $soFar =[];

        foreach ($pathSegments as $segment) {
            $soFar[] = $segment;
            $this->addBreadcrumb(
                $segment,
                $this->makeUrl('driver_items/' . $driverName, ['path' => implode('/', $soFar)])
            );
        }
    }

    public function delete_item(string $name, $is_path = false): void
    {
        $this->assertCsrfToken();

        $this->setHeading('speedy_driver_items');
        $this->addBreadcrumb('speedy_driver_' . $name, $this->makeUrl('driver_stats/' . $name));

        $driver = $this->getSupportedDriver($name);
        $path = ee()->input->get('path', true);

        if (empty($path)) {
            show_error('The requested path does not exist.');
            exit;
        }

        $method = $is_path ? 'deletePath' : 'deleteItem';
        $lang_prefix = $is_path ? 'speedy_delete_path' : 'speedy_delete_item';

        // Collect keys before the driver deletes them (path drivers wipe the
        // directory, so enumeration must happen first).
        if ($is_path) {
            /** @var \BoldMinded\Speedy\Service\CacheBreaker $breaker */
            $breaker = ee('speedy:CacheBreaker');
            $keys = $breaker->collectItemsFromPath(rtrim($path, '/') . '/');
        } else {
            $keys = [$path];
        }

        if ($driver->{$method}($path)) {
            $this->addInlineAlert()
                ->asSuccess()
                ->withTitle(lang($lang_prefix . '_success'))
                ->addToBody(lang($lang_prefix . '_success_desc'))
                ->defer();

            ee('speedy:Diagnostics')->clearDiagnostics($path);

            $urls = (new UrlCollector($keys))->collect();
            if (count($urls) > 0) {
                if ($this->shouldUseQueue()) {
                    foreach ($urls as $url) {
                        $this->pushToQueue(PurgeUrlJob::class, ['url' => $url]);
                    }
                } else {
                    (new PurgerFactory())->create()->purgeUrls($urls);
                }
            }
        } else {
            $this->addInlineAlert()
                ->asIssue()
                ->withTitle(lang($lang_prefix . '_error'))
                ->addToBody(lang($lang_prefix . '_error_desc'))
                ->defer();
        }

        ee()->functions->redirect($this->getParentViewItemUrl($name, $path));
    }

    public function delete_path(string $name): void
    {
        $this->delete_item($name, true);
    }

    public function driver_settings(string $name): array
    {
        $this->setHeading(sprintf(lang('speedy_driver_settings'), lang('speedy_driver_' . $name)));

        $driver = $this->getSupportedConfigurableDriver($name, true);
        $form = $driver->getConfigurationForm();

        /** @var \BoldMinded\Speedy\Model\DriverConfiguration $configuration */
        $configuration = ee('Model')->get('speedy:DriverConfiguration')
            ->filter('site_id', $this->siteId)
            ->filter('driver', $name)
            ->first();

        if ($configuration === null) {
            $configuration = ee('Model')->make('speedy:DriverConfiguration');
            $configuration->site_id = $this->siteId;
            $configuration->driver = $name;
            $configuration->settings = [];
        }

        // Allow config overrides
        $configSettings = ee()->config->item('speedy_'. $name .'_settings');

        if ($configSettings && is_array($configSettings) && !empty($configSettings)) {
            $configuration = ee('Model')->make('speedy:DriverConfiguration');
            $configuration->site_id = $this->siteId;
            $configuration->driver = $name;
            $configuration->settings = $configSettings;

            if ($driver instanceof ConfigurableDriverInterface) {
                $driver->setHasFileConfigOverride(true);

                $this->addInlineAlert()
                    ->asAttention()
                    ->withTitle(lang('speedy_driver_settings_config_override'))
                    ->addToBody(sprintf(lang('speedy_driver_settings_config_override_desc'), 'speedy_'. $name .'_settings'))
                    ->now();
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $validate = $form->validate($configuration);

            // If existing settings were removed and saved
            if (!isset($configuration->settings)) {
                $configuration->settings = [];
            }

            if ($validate) {
                $configuration->save();

                $this->addInlineAlert()
                    ->asSuccess()
                    ->withTitle(lang('speedy_driver_settings_saved'))
                    ->addToBody(lang('speedy_driver_settings_saved_desc'))
                    ->defer();

                ee()->functions->redirect($this->makeUrl('driver_settings/' . $name));
            } else {
                ee()->load->library('form_validation');
                ee()->form_validation->_error_array = $form->getErrors();

                $this->addInlineAlert()
                    ->asIssue()
                    ->withTitle(lang('speedy_driver_settings_error'))
                    ->addToBody(lang('speedy_driver_settings_error_desc'))
                    ->now();
            }
        }

        return $this->render('driver_settings', [
            'settings' => [
                'base_url'              => $this->makeUrl('driver_settings/' . $name),
                'save_btn_text'         => 'speedy_driver_settings_save_btn',
                'save_btn_text_working' => 'speedy_driver_settings_save_btn_working',
                'sections'              => $form->getSections($configuration),
            ],
        ]);
    }

    private function saveSettingsCache(bool $forceRecreate = false): void
    {
        if (!$this->shouldCreateStaticFiles($forceRecreate)) {
            return;
        }

        $sites = ee('Model')
            ->get('Site')
            ->all();

        // When MSM is enabled we want regenerating static cache files and settings triggered
        // in 1 site to affect all sites, otherwise the alert pops up in every site and requires
        // manually retriggering the regeneration.
        foreach ($sites as $site) {
            $this->setting->save([
                'settings_cache' => $this->encryptSettingsCache()
            ], $site->site_id);
        }
    }

    /**
     * @todo PHP8 return bool|string
     * @return false|string
     */
    private function getSettingsCache()
    {
        return $this->setting->get('settings_cache');
    }

    /**
     * Detect changes across the config.php file $config['speedy_*'] values, and the driver
     * settings in the database. If anything is changed, we may need to regenerate static utility files.
     *
     * @return bool
     */
    private function isSettingsFromCacheUpdated(): bool
    {
        $settingsCache = $this->decryptSettingsCache();
        $expectedSettingsCache = $this->getSpeedySettings();

        // If using the msm specific config array, remove the root elements before performing
        // the comparison. The speedy_static_path and speedy_frontedit_check_url are set dynamically
        // in the ext.speedy.php file based on the site being accessed. Without removing these
        // elements, the comparison will always fail.
        if (isset($settingsCache['file']['speedy_msm'])) {
            unset($settingsCache['file']['speedy_static_path']);
            unset($settingsCache['file']['speedy_frontedit_check_url']);
        }

        if (isset($expectedSettingsCache['file']['speedy_msm'])) {
            unset($expectedSettingsCache['file']['speedy_static_path']);
            unset($expectedSettingsCache['file']['speedy_frontedit_check_url']);
        }

        if ($settingsCache && $settingsCache !== $expectedSettingsCache) {
            return true;
        }

        return false;
    }

    /**
     * Encrypt the settings b/c it might contain authentication information.
     * Ideally the user/cache folder will be above the web root too for even more security.
     */
    private function encryptSettingsCache(): string
    {
        return ee('Encrypt')->encode(json_encode($this->getSpeedySettings()));
    }

    private function decryptSettingsCache(): array
    {
        $settingsCache = $this->getSettingsCache();

        if (!$settingsCache) {
            return [];
        }

        $decoded = json_decode(ee('Encrypt')->decode($settingsCache), true);

        if (json_last_error()) {
            return [];
        }

        return $decoded;
    }

    /**
     * Get all driver settings, and config file settings and merge into a single array so we can track changes.
     */
    private function getSpeedySettings(): array
    {
        $eeConfig = ee()->config->config;
        /** @var \ExpressionEngine\Service\Model\Collection $configuration */
        $driverConfig = ee('Model')->get('speedy:DriverConfiguration')->all()->toArray();

        $speedyFileConfig = array_intersect_key(
            $eeConfig,
            array_flip(
                preg_grep('/speedy_(.*?)/', array_keys($eeConfig))
            )
        );

        return [
            'file' => $speedyFileConfig,
            'driver' => $driverConfig
        ];
    }

    /**
     * Load CSS and JS into control panel.
     */
    private function addAssets()
    {
        $css = file_get_contents(__DIR__ . '/styles/speedy.css');
        ee()->cp->add_to_head('<style>' . $css . '</style>');
    }

    private function buildCacheTableData(): array
    {
        $data = [];

        /** @var AbstractDriver $driver */
        foreach ($this->drivers->getDrivers() as $driver) {
            $driverName = $driver->getName();
            $name = lang('speedy_driver_' . $driverName);
            $count = '';
            $support = $driver->getSupportValidator();
            $toolbar = [];

            if ($driverName === 'redis') {
                // $this->seedCache($driver);
            }

            if ($support->isSupported()) {
                $requiresConfiguration = $driver instanceof ConfigurableDriverInterface && !$driver->isConfigured();

                if ($requiresConfiguration) {
                    $status = '<a href="" class="m-link status-tag st-pending" rel="' . $driverName . '">' . lang('speedy_driver_status_unconfigured') . '</span></a>';
                    $this->addModal($driverName, $this->buildSupportModalHtml($driverName, $driver->getConfiguredValidator(), true));
                } else {
                    $count = $driver->countItems();
                    $status = '<a href="" class="m-link status-tag st-enable" rel="' . $driverName . '">' . lang('speedy_driver_status_available') . '</span></a>';
                    $this->addModal($driverName, $this->buildSupportModalHtml($driverName, $support));
                }

                // A disabled/mis-configured driver can be set to active in the config.
                // If this is the case then it  could be a telling sign of Speedy not being implemented correctly.
                if ($driverName === ee()->config->item('speedy_driver')) {
                    $status .= ' <span class="status-tag st-info">Default</span>';
                }

                if ($driver instanceof ReadableDriverInterface && !$requiresConfiguration) {
                    $toolbar['stats'] = [
                        'href'  => $this->makeUrl('driver_stats/' . $driverName),
                        'title' => lang('speedy_cache_info_manage_view'),
                        'class' => 'fas fa-chart-simple'
                    ];
                    $toolbar['view'] = [
                        'href'  => $this->makeUrl('driver_items/' . $driverName),
                        'title' => lang('speedy_cache_info_manage_items'),
                    ];
                }

                if ($driver instanceof ConfigurableDriverInterface) {
                    $toolbar['settings'] = [
                        'href'  => $this->makeUrl('driver_settings/' . $driverName),
                        'title' => lang('speedy_cache_info_manage_settings'),
                    ];
                }
            } else {
                $status = '<a href="" class="m-link status-tag st-disable" rel="' . $driverName . '">' . lang('speedy_driver_status_unsupported') . '</span></a>';
                $this->addModal($driverName, $this->buildSupportModalHtml($driverName, $support));
            }

            $data[] = [$name, $count, $status, ['toolbar_items' => $toolbar]];
        }

        return $data;
    }

    private function seedCache($driver)
    {
        $i=0;

        while ($i < 10000) {
            $text = $this->generateText(rand(10, 500));
            $cacheItem = new BoldMinded\Speedy\Service\CacheItem();
            $cacheItem
                ->setKey('test_' . $i)
                ->setValue($text)
                ->setTTL(86400);

            $driver->save($cacheItem);
            $i++;
        }
    }

    private function generateText($sizeInKB): string {
        $lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit ';
        $targetBytes = $sizeInKB * 1024;
        $output = '';

        while (strlen($output) < $targetBytes) {
            $output .= $lorem;
        }

        return substr($output, 0, $targetBytes);
    }

    private function diagnosticsQuery(int $limit = 0, int $page = 1)
    {
        $diagnostics = ee('Model')
            ->get('speedy:Diagnostics')
            ->filter('site_id', $this->siteId)
            ->order('execution_time', 'desc');

        if ($limit > 0) {
            $diagnostics->limit($limit);
        }

        $offset = ($page - 1) * $limit;
        $diagnostics->offset($offset);

        return $diagnostics->all();
    }

    private function buildDiagnosticsTableData(int $limit = 0, int $page = 1): array
    {
        $data = [];

        $diagnostics = $this->diagnosticsQuery($limit, $page);

        foreach ($diagnostics as $row) {
            $driver = $this->drivers->getDriver($row->driver);
            $cacheItem = $driver->getItem($row->key);

            $toolbar = [];
            $toolbar['stats'] = [
                'href'  => $this->makeUrl('diagnostic_item', ['key' => $row->key, 'referrer' => 'diagnostics']),
                'title' => lang('speedy_cache_info_manage_view'),
                'class' => 'fas fa-chart-simple'
            ];

            // Only show the link if it's currently in the cache
            if ($cacheItem) {
                $toolbar['view'] = [
                    'href'  => $this->makeUrl('driver_item/' . $row->driver, ['path' => $row->key, 'referrer' => 'diagnostics']),
                    'title' => lang('speedy_cache_info_manage_view'),
                ];
            }

            $toolbar['remove'] = [
                'href'  => $this->makeCsrfUrl('delete_diagnostic', ['key' => $row->key, 'referrer' => 'diagnostics']),
                'title' => lang('speedy_cache_info_manage_delete'),
            ];

            $data[] = [
                'attrs' => [],
                'columns' => [
                    $row->key,
                    $row->query_count,
                    $row->execution_time,
                    ['toolbar_items' => $toolbar],
                ]
            ];
        }

        return $data;
    }

    /**
     * @param string                                                      $name
     * @param \BoldMinded\Speedy\Service\Drivers\Support\SupportValidator $support
     * @return string
     */
    private function buildSupportModalHtml($name, SupportValidator $support, $configuration = false)
    {
        $checklist = [];
        $crosslist = [];

        foreach ($support->getSupportList() as $reason => $passed) {
            if ($passed) {
                $checklist[] = $reason;
            } else {
                $crosslist[] = $reason;
            }
        }

        return $this->renderView('support_modal', [
            'name'          => $name,
            'supported'     => $support->isSupported(),
            'configuration' => $configuration,
            'checklist'     => $checklist,
            'crosslist'     => $crosslist,
        ]);
    }

    /**
     * @param \BoldMinded\Speedy\Service\Drivers\ReadableDriverInterface $driver
     * @param string                                                     $path
     * @return array
     */
    /**
     * Return this level's entries as a single alphabetical list (directories
     * keep their trailing slash, but are NOT grouped before files). The "..".
     * parent-navigation row is handled separately by buildDriverItemsData so it
     * only appears on the first page.
     *
     * @return string[]
     */
    private function getDriverItemsAtPath(ReadableDriverInterface $driver, $path): array
    {
        $items = $driver->getItemsAtPath($path);

        sort($items, SORT_STRING | SORT_FLAG_CASE);

        return $items;
    }

    /**
     * Recursively find item paths under $path whose key contains $keyword
     * (case-insensitive, any part of the string). Returns paths relative to
     * $path, alphabetically sorted.
     *
     * @return string[]
     */
    private function searchDriverItems(ReadableDriverInterface $driver, $path, string $keyword): array
    {
        $items = $driver->getAllItemsFromPath($path);

        $matches = [];
        foreach ($items as $item) {
            // getItemsFromPath() can return an item both as "item-1" and as the
            // directory variant "item-1/" (static stores items as directories).
            // Normalise to a single key per item so results aren't duplicated.
            $key = rtrim($item, '/');

            if ($key === '' || mb_stripos($key, $keyword) === false) {
                continue;
            }

            $matches[$key] = true;
        }

        $matches = array_keys($matches);
        sort($matches, SORT_STRING | SORT_FLAG_CASE);

        return $matches;
    }

    /**
     * Build flat table rows for search matches. Each match is a full item path
     * relative to $path and links straight to its detail view.
     */
    private function buildDriverSearchData(ReadableDriverInterface $driver, $path, array $matches): array
    {
        $name = $driver->getName();
        $rows = [];

        foreach ($matches as $match) {
            $item_path = trim($path . '/' . $match, '/');

            try {
                $meta = $driver->getItemMetadata($item_path);
            } catch (ItemNotFoundException $e) {
                continue;
            }

            $item_name = [
                'href'    => $this->makeUrl('driver_item/' . $name, ['path' => $item_path]),
                'content' => $match,
            ];

            $toolbar = [
                'view' => [
                    'href'  => $this->makeUrl('driver_item/' . $name, ['path' => $item_path]),
                    'title' => lang('speedy_driver_items_view_item'),
                ],
                'remove' => [
                    'href'  => $this->makeCsrfUrl('delete_item/' . $name, ['path' => $item_path]),
                    'title' => lang('speedy_driver_items_delete_item'),
                ],
            ];

            $rows[] = [
                $item_name,
                $this->formatDate($meta['created_at']),
                $this->formatDate($meta['expires_at']),
                ['toolbar_items' => $toolbar],
            ];
        }

        return $rows;
    }

    /**
     * Build the table rows for a (already paginated) slice of this level's
     * entries. $entries is the alphabetical page slice from getDriverItemsAtPath.
     * $includeParent adds the ".." parent-navigation row (first page only).
     */
    private function buildDriverItemsData(
        ReadableDriverInterface $driver,
        $path,
        array $entries,
        bool $includeParent = true
    ) {
        $name = $driver->getName();
        $rows = [];

        // If we are in a nested directory, add option to navigate up to parent.
        if ($includeParent) {
            $parent_url = $this->getParentViewItemUrl($name, $path, true);
            if ($parent_url) {
                $item_name = ['content' => '..', 'href' => $parent_url];
                $rows[] = [$item_name, '', '', ['toolbar_items' => []]];
            }
        }

        foreach ($entries as $item) {
            $toolbar = [];
            $item_path = trim($path . '/' . $item, '/');
            $is_directory = substr($item, -1) === '/';

            if ($is_directory) {
                $item_name = [
                    'href'    => $this->makeUrl('driver_items/' . $name, ['path' => $item_path]),
                    'content' => $item,
                ];

                $toolbar['remove'] = [
                    'href'  => $this->makeCsrfUrl('delete_path/' . $name, ['path' => $item_path]),
                    'title' => lang('speedy_driver_items_delete_path'),
                ];

                $rows[] = [$item_name, '', '', ['toolbar_items' => $toolbar]];
            } else {
                try {
                    $meta = $driver->getItemMetadata($item_path);
                } catch (ItemNotFoundException $e) {
                    continue;
                }

                $item_name = [
                    'href'    => $this->makeUrl('driver_item/' . $name, ['path' => $item_path]),
                    'content' => $item,
                ];
                $created_at = $this->formatDate($meta['created_at']);
                $expires_at = $this->formatDate($meta['expires_at']);

                $toolbar['view'] = [
                    'href'  => $this->makeUrl('driver_item/' . $name, ['path' => $item_path]),
                    'title' => lang('speedy_driver_items_view_item'),
                ];

                $toolbar['remove'] = [
                    'href'  => $this->makeCsrfUrl('delete_item/' . $name, ['path' => $item_path]),
                    'title' => lang('speedy_driver_items_delete_item'),
                ];

                $rows[] = [$item_name, $created_at, $expires_at, ['toolbar_items' => $toolbar]];
            }
        }

        return $rows;
    }

    /**
     * @param string $key
     * @param string $value
     * @return string
     */
    private function formatDriverStat($key, $value)
    {
        if (!$value) {
            return '<span style="color:gray">' . lang('speedy_driver_stats_na') . '</span>';
        }

        switch ($key) {
            case ReadableDriverInterface::STATS_HITS:
            case ReadableDriverInterface::STATS_MISSES:
                return $value;

            case ReadableDriverInterface::STATS_UPTIME:
                // TODO: Format driver uptime
                return $value;

            case ReadableDriverInterface::STATS_MEMORY_USAGE:
            case ReadableDriverInterface::STATS_MEMORY_AVAILABLE:
                return (string) ee('Format')->make('Number', $value)->bytes();

            default:
                return $value;
        }
    }

    /**
     * @param int $timestamp
     * @return string
     */
    private function formatDate($timestamp)
    {
        if ($timestamp === 0) {
            return '&infin;';
        }

        return ee()->localize->format_date('%Y-%m-%d %g:%i:%s%a', $timestamp, true);
    }

    /**
     * @param int $ttl
     * @return string
     */
    private function formatTTL($ttl)
    {
        if ($ttl === 0) {
            return '&infin;';
        }

        return sprintf(lang('speedy_driver_item_ttl_format'), $ttl);
    }

    /**
     * @param string $driver
     * @param string $path
     * @param bool   $strict
     * @return \ExpressionEngine\Library\CP\URL
     */
    private function getParentViewItemUrl($driver, $path, $strict = false)
    {
        $parent_path = dirname($path);

        if ($parent_path && $parent_path !== '.') {
            return $this->makeUrl('driver_items/' . $driver, ['path' => $parent_path]);
        }

        if ($strict && trim($path, '.') === '') {
            return null;
        }

        return $this->makeUrl('driver_items/' . $driver);
    }

    /**
     * @return array
     */
    private function getSelectDriverChoices()
    {
        $choices = [];

        /** @var AbstractDriver $driver */
        foreach ($this->drivers->getDrivers() as $driver) {
            $name = $driver->getName();

            // Skip dummy and non-supported drivers
            if ($name === DummyDriver::NAME || !$driver->isSupported()) {
                continue;
            }

            // Skip non-configured drivers
            if ($driver instanceof ConfigurableDriverInterface && !$driver->isConfigured()) {
                continue;
            }

            $choices[$name] = lang('speedy_driver_' . $name);
        }

        return $choices;
    }

    /**
     * @param string $name
     * @param bool   $ignore_configuration
     * @return \BoldMinded\Speedy\Service\Drivers\DriverInterface
     */
    private function getSupportedDriver($name, $ignore_configuration = false)
    {
        $driver = $this->drivers->getDriver($name);

        if ($driver === false || !$driver->isSupported()) {
            show_error(sprintf(lang('speedy_driver_not_supported'), lang('speedy_driver_' . $name)));
        }

        if ($ignore_configuration) {
            return $driver;
        }

        if ($driver instanceof ConfigurableDriverInterface && !$driver->isConfigured()) {
            show_error(sprintf(lang('speedy_driver_not_configured'), lang('speedy_driver_' . $name)));
        }

        return $driver;
    }

    /**
     * @param string $name
     * @return \BoldMinded\Speedy\Service\Drivers\ReadableDriverInterface
     */
    private function getSupportedReadableDriver($name)
    {
        $driver = $this->getSupportedDriver($name);

        if (!$driver instanceof ReadableDriverInterface) {
            show_error(sprintf(lang('speedy_driver_not_supported'), lang('speedy_driver_' . $name)));
        }

        return $driver;
    }

    /**
     * @param string $name
     * @param bool   $ignore_configuration
     * @return \BoldMinded\Speedy\Service\Drivers\ConfigurableDriverInterface
     */
    private function getSupportedConfigurableDriver($name, $ignore_configuration = false)
    {
        $driver = $this->getSupportedDriver($name, $ignore_configuration);

        if (!$driver instanceof ConfigurableDriverInterface) {
            show_error(sprintf(lang('speedy_driver_not_supported'), lang('speedy_driver_' . $name)));
        }

        return $driver;
    }

    /**
     * Build sidebar.
     */
    protected function makeSidebar(): Sidebar
    {
        /** @var Sidebar $sidebar */
        $sidebar = ee('CP/Sidebar')->make();
        $lastSegment = end(ee()->uri->rsegments);

        $sidebar->addHeader(lang('speedy_mcp_home'), $this->makeUrl());
        $rules = $sidebar->addHeader(lang('speedy_mcp_cache_clearing'), $this->makeUrl('cache_clearing'));

        if ($lastSegment === 'cache_clearing_settings') {
            $rules->isActive();
        }

        if ($this->shouldCreateStaticFiles()) {
            $sidebar->addHeader(lang('speedy_mcp_regenerate'), $this->makeUrl('create_static_files', ['regenerate' => 1]));
        }

        if ($this->diagnostics->isEnabled()) {
            $diagnostics = $sidebar->addHeader(lang('speedy_mcp_diagnostics'), $this->makeUrl('diagnostics'));
        }

        if ($lastSegment === 'diagnostic_item') {
            $diagnostics->isActive();
        }

        $purger = $sidebar->addHeader(lang('speedy_mcp_purger'), $this->makeUrl('purger'));

        if ($lastSegment === 'purger') {
            $purger->isActive();
        }

        $sidebar->addHeader(lang('speedy_mcp_documentation'), 'https://docs.boldminded.com/speedy/docs');
        $sidebar->addHeader(lang('speedy_mcp_release_notes'), $this->makeUrl('release_notes'));

        return $sidebar;
    }

    /**
     * Copy our service file to the cache directory so each cache file can use it.
     * Strip the namespace b/c it's included without any autoloading.
     *
     * If Redis as Static is enabled, we still need to copy files to the static folder so the user doesn't have
     * to create files manually at the root of their site. Easiest setup for them is to modify .htaccess, then
     * click a button that copies the files for them.
     */
    public function create_static_files()
    {
        $speedyMsm = ee()->config->item('speedy_msm') ?: [];

        // If MSM specific config is defined just check for the existence of the action ID. Unfortunately at this point
        // there is no way to know what the URLs are to other MSM sites. Sites don't have URLs defined for them in any
        // config other than what might be in an $assign_to_config['site_url'] in a index.php file, which we don't
        // know the paths too.

        $msmStaticPaths = array_column($speedyMsm, 'static_path');

        if ($speedyMsm
            && is_array($speedyMsm)
            && count($msmStaticPaths) > 0
        ) {
            $paths = [];

            foreach ($speedyMsm as $siteId => $settings) {
                $siteName = $this->getSiteName($siteId);
                $paths[] = $settings['static_path'];

                $success = $this->write_static_files(
                    $settings['static_path'],
                    $siteName,
                );
            }

            if ($success) {
                $this->saveSettingsCache(true);

                $this->addInlineAlert('shared-form')
                    ->asSuccess()
                    ->withTitle(lang('speedy_create_static_files_success'))
                    ->addToBody(sprintf(lang('speedy_create_static_files_success_desc'), implode(', ', $paths)))
                    ->defer();
            } else {
                $this->addInlineAlert('shared-form')
                    ->asIssue()
                    ->withTitle(lang('speedy_create_static_files_error'))
                    ->addToBody(sprintf(lang('speedy_create_static_files_error_desc'), implode(', ', $paths)))
                    ->defer();
            }

        } else {
            $staticDriver = new StaticDriver();
            $cachePath = $staticDriver->getCachePath();
            $success = $this->write_static_files(
                $cachePath,
                ee()->config->item('site_short_name'),
            );

            if ($success) {
                $this->saveSettingsCache(true);

                $this->addInlineAlert('shared-form')
                    ->asSuccess()
                    ->withTitle(lang('speedy_create_static_files_success'))
                    ->addToBody(sprintf(lang('speedy_create_static_files_success_desc'), $cachePath))
                    ->defer();
            } else {
                $this->addInlineAlert('shared-form')
                    ->asIssue()
                    ->withTitle(lang('speedy_create_static_files_error'))
                    ->addToBody(sprintf(lang('speedy_create_static_files_error_desc'), $cachePath))
                    ->defer();
            }
        }

        ee()->functions->redirect($this->makeUrl('/'));
    }

    private function write_static_files(
        string $cachePath = '',
        string $siteShortName = 'default_site'
    ): bool
    {
        $regenerate = ee('Request')->get('regenerate') === '1';
        $redisDriver = new RedisDriver();
        $staticUtilitiesPath = $cachePath . '/utilities';
        $success = true;

        $files = [
            [
                'contents' => '<html>
                    <head>
                        <title>403 Forbidden</title>
                    </head>
                    <body>
                        <p>Directory access is forbidden.</p>
                    </body>
                    </html>',
                'target' => $cachePath . '/' . $siteShortName . '/index.html',
            ],
            [
                'contents' => lang('speedy_configured_file_message'),
                'target' => $staticUtilitiesPath . '/' . self::FILE_CONFIGURED,
            ],
            [
                'source' => SPEEDY_APP_PATH . 'Service/StaticCacheHelper.php',
                'target' => $staticUtilitiesPath . '/StaticCacheHelper.php',
                'replace' => [
                    'namespace BoldMinded\Speedy\Service;' => '',
                    'use BoldMinded\Speedy\Service\Csrf\SpeedyCsrf;' => '',
                ]
            ],
            [
                'source' => SPEEDY_APP_PATH . 'Service/Csrf/SpeedyCookie.php',
                'target' => $staticUtilitiesPath . '/Csrf/SpeedyCookie.php',
                'replace' => [
                    'namespace BoldMinded\Speedy\Service\Csrf;' => '',
                ]
            ],
            [
                'source' => SPEEDY_APP_PATH . 'Service/Csrf/SpeedyDatabase.php',
                'target' => $staticUtilitiesPath . '/Csrf/SpeedyDatabase.php',
                'replace' => [
                    'namespace BoldMinded\Speedy\Service\Csrf;' => '',
                ]
            ],
            [
                'source' => SPEEDY_APP_PATH . 'Service/Csrf/SpeedyCsrf.php',
                'target' => $staticUtilitiesPath . '/Csrf/SpeedyCsrf.php',
                'replace' => [
                    'namespace BoldMinded\Speedy\Service\Csrf;' => '',
                ]
            ],
            [
                'source' => SPEEDY_APP_PATH . 'Service/Csrf/SpeedyCsrfStorageInterface.php',
                'target' => $staticUtilitiesPath . '/Csrf/SpeedyCsrfStorageInterface.php',
                'replace' => [
                    'namespace BoldMinded\Speedy\Service\Csrf;' => '',
                ]
            ]
        ];

        if (ee()->config->item('speedy_driver') === RedisDriver::NAME) {
            $settings = $this->getDriverSettings('redis');
            $allowList = ee()->config->item('speedy_query_cache_allowlist') ?: [];
            $indexFileName = ee()->config->item('index_page') ?: 'index.php';
            $options = [
                // Set a few path vars based off constants since we won't be booting EE.
                'appPath' => APPPATH,
                'basePath' => BASEPATH,
                // Prefix check borrowed from core.
                'cookiePrefix' => (!ee()->config->item('cookie_prefix')) ? 'exp_' : ee()->config->item('cookie_prefix') . '_',
                'configPath' => SYSPATH . 'user/config/config.php' ,
                'databasePrefix' => ee()->db->dbprefix ?? 'exp_',
                'frontEditCheckUrl' => ee()->config->item('speedy_frontedit_check_url') ?? '',
                'frontEditCheckInsecure' => ee()->config->item('speedy_frontedit_check_insecure') === 'yes',
                'systemPath' => SYSPATH,
            ];

            $files[] = [
                'source' => SPEEDY_APP_PATH . 'index_redis.php',
                'target' => $this->getDocumentRootPath() . 'index_redis_' . $siteShortName . '.php',
                'replace' => [
                    'private $keyPrefix = null;' => 'private $keyPrefix = \''. $redisDriver->getPrefix() . '/static\';',
                    'private $settings = null;' => 'private $settings = '. var_export($settings, true) .';',
                    'private $allowList = [];' => 'private $allowList = '. var_export($allowList, true) .';',
                    'private $options = [];' => 'private $options = '. var_export($options, true) .';',
                    '{{index_path}}' =>  $this->getDocumentRootPath() . $indexFileName,
                    '{{utilitiesCachePath}}' => $staticUtilitiesPath,
                ]
            ];
        }

        foreach ($files as $file) {
            if (!file_exists($file['target']) || $regenerate) {
                if (isset($file['contents'])) {
                    $contents = $file['contents'];
                } else {
                    $contents = file_get_contents($file['source']);
                }
                if (isset($file['replace'])) {
                    foreach ($file['replace'] as $find => $replace) {
                        $contents = str_replace($find, $replace, $contents);
                    }
                }

                $success = $this->writeFile($file['target'], $contents);
            }
        }

        return $success;
    }

    /**
     * @param string $file
     * @param string $data
     * @return bool
     */
    private function writeFile($file, $data)
    {
        try {
            // Attempt to create the path to the cache file.
            $path = dirname($file);

            if (!@mkdir($path, 0775, true) && !is_dir($path)) {
                $this->logger->error(sprintf(
                    'Could not make file: %s. Be sure %s is a directory and is writable with 775 permissions.',
                    $file,
                    $path
                ));

                return false;
            }

            if (file_put_contents($file, $data) === false) {
                @chmod($file, 0644);

                $this->logger->error(sprintf(
                    'Could not write to file: %s. Be sure it exists and is writable with 644 permissions.',
                    $file
                ));

                return false;
            }

            $this->logger->info(sprintf(
                'Created file: %s',
                $file
            ));

            return true;
        } catch (\Exception $exception) {
            show_error($exception->getMessage());
        }
    }

    /**
     * Display a warning that the add-on is disabled.
     */
    private function addDisabledWarning()
    {
        if (ee()->config->item('speedy_enabled') === 'no') {
            $this->addInlineAlert()
                ->asWarning()
                ->withTitle(lang('speedy_disabled'))
                ->addToBody(lang('speedy_disabled_desc'))
                ->cannotClose()
                ->now();
        }
    }

    /**
     * @param \BoldMinded\Speedy\Model\CacheBreaking $settings
     * @param array                                  $form_errors
     * @return \ExpressionEngine\Library\CP\GridInput
     */
    private function makeCacheBreakingTagsGrid(CacheBreaking $settings, array|null $form_errors = null)
    {
        /** @var \ExpressionEngine\Library\CP\GridInput $grid */
        $grid = ee('CP/GridInput', [
            'field_name' => 'tags_grid',
            'reorder'    => false,
        ]);
        $grid->loadAssets();
        $grid->setColumns(['speedy_clearing_tags']);
        $grid->setBlankRow($this->makeCacheBreakingTagsGridRow());
        $grid->setNoResultsText('speedy_clearing_no_tags', 'speedy_clearing_add_tag');

        $grid_data = [];
        $validation_data = ee('Request')->post('tags_grid');

        if (!empty($validation_data)) {
            foreach ($validation_data['rows'] as $row_id => $columns) {
                $row_errors = [];
                if (isset($form_errors['tags_grid'][$row_id])) {
                    $row_errors = array_map('strip_tags', $form_errors['tags_grid'][$row_id]);
                }

                $grid_data[$row_id] = [
                    'attrs'   => ['row_id' => str_replace('row_id_', '', $row_id)],
                    'columns' => $this->makeCacheBreakingTagsGridRow($columns['tag_name'], $row_errors),
                ];
            }
        } elseif (!empty($settings->tags)) {
            foreach ($settings->tags as $index => $tag_name) {
                $grid_data[] = [
                    'attrs'   => ['row_id' => $index],
                    'columns' => $this->makeCacheBreakingTagsGridRow($tag_name),
                ];
            }
        }

        if (count($grid_data)) {
            $grid->setData($grid_data);
        }

        return $grid;
    }

    /**
     * @param \BoldMinded\Speedy\Model\CacheBreaking $settings
     * @param array                                  $form_errors
     * @return \ExpressionEngine\Library\CP\GridInput
     */
    private function makeCacheBreakingItemsGrid(CacheBreaking $settings, array|null $form_errors = null)
    {
        /** @var \ExpressionEngine\Library\CP\GridInput $grid */
        $grid = ee('CP/GridInput', [
            'field_name' => 'items_grid',
            'reorder'    => false,
        ]);
        $grid->loadAssets();
        $grid->setColumns(['speedy_clearing_items']);
        $grid->setNoResultsText('speedy_clearing_no_items', 'speedy_clearing_add_item');
        $grid->setBlankRow($this->makeCacheBreakingItemsGridRow());

        $grid_data = [];
        $validation_data = ee('Request')->post('items_grid');

        if (!empty($validation_data)) {
            foreach ($validation_data['rows'] as $row_id => $columns) {
                $row_errors = [];
                if (isset($form_errors['items_grid'][$row_id])) {
                    $row_errors = array_map('strip_items', $form_errors['items_grid'][$row_id]);
                }

                $grid_data[$row_id] = [
                    'attrs'   => ['row_id' => str_replace('row_id_', '', $row_id)],
                    'columns' => $this->makeCacheBreakingItemsGridRow($columns['item_name'], $row_errors),
                ];
            }
        } elseif (!empty($settings->items)) {
            foreach ($settings->items as $index => $item_name) {
                $grid_data[] = [
                    'attrs'   => ['row_id' => $index],
                    'columns' => $this->makeCacheBreakingItemsGridRow($item_name),
                ];
            }
        }

        if (count($grid_data)) {
            $grid->setData($grid_data);
        }

        return $grid;
    }

    /**
     * @param string $tag_name
     * @param array  $row_errors
     * @return array
     */
    private function makeCacheBreakingTagsGridRow($tag_name = '', array $row_errors = [])
    {
        return [
            'tag_name' => [
                'html'  => form_input('tag_name', form_prep($tag_name)),
                'error' => isset($row_errors['tag_name']) ? $row_errors['tag_name'] : null,
            ],
        ];
    }

    /**
     * @param string $item_name
     * @param array  $row_errors
     * @return array
     */
    private function makeCacheBreakingItemsGridRow($item_name = '', array $row_errors = [])
    {
        return [
            'item_name' => [
                'html'  => form_input('item_name', form_prep($item_name)),
                'error' => isset($row_errors['item_name']) ? $row_errors['item_name'] : null,
            ],
        ];
    }

    /**
     * @param \BoldMinded\Speedy\Model\CacheBreaking $settings
     * @param array                                  $form_errors
     * @return array
     */
    private function validateCacheBreakingTagsGrid(CacheBreaking $settings, &$form_errors)
    {
        $tags_grid = ee('Request')->post('tags_grid');
        $tags_values = [];

        if (isset($tags_grid['rows'])) {
            foreach ($tags_grid['rows'] as $row_id => $columns) {
                // Decode the form_prep()-encoded value the grid posts back, otherwise
                // quotes are stored as entities and re-encoded on every save.
                $tag_name = trim($this->decodeGridEntities($columns['tag_name']));
                if ($tag_name === '') {
                    continue;
                }

                $tags_values[] = $tag_name;

                $tag_result = $settings->validateTag($tag_name);
                if (!$tag_result->isValid()) {
                    $form_errors['tags_grid'][$row_id] = $tag_result->renderErrors();
                }
            }

            $tags_values = array_unique($tags_values);
        }

        return $tags_values;
    }

    /**
     * @param \BoldMinded\Speedy\Model\CacheBreaking $settings
     * @param array                                  $form_errors
     * @return array
     */
    private function validateCacheBreakingItemsGrid(CacheBreaking $settings, &$form_errors)
    {
        $items_grid = ee('Request')->post('items_grid');
        $items_values = [];

        if (isset($items_grid['rows'])) {
            foreach ($items_grid['rows'] as $row_id => $columns) {
                // The grid posts back the form_prep()-encoded value, so decode it before
                // storing. Without this, quotes in tag params like {entry_date format="%Y"}
                // are saved as entities and re-encoded on every subsequent save.
                $item_name = trim($this->decodeGridEntities($columns['item_name']));
                if ($item_name === '') {
                    continue;
                }

                $items_values[] = $item_name;

                $item_result = $settings->validateItem($item_name);
                if (!$item_result->isValid()) {
                    $form_errors['items_grid'][$row_id] = $item_result->renderErrors();
                }
            }

            $items_values = array_unique($items_values);
        }

        return $items_values;
    }

    private function validateArraySettings(array $setting)
    {
        return array_filter(array_unique($setting));
    }

    /**
     * Recursively decode HTML entities from a value the CP grid posts back. The grid
     * renders existing rows through form_prep() (htmlspecialchars with ENT_QUOTES) and
     * submits that encoded value verbatim, so quotes in tag parameters such as
     * {entry_date format="%Y"} arrive entity-encoded. Decoding until stable also heals
     * values that compounded across earlier saves (&#039; -> &amp;#039; -> ...).
     *
     * @param string $value
     * @return string
     */
    private function decodeGridEntities($value)
    {
        $value = (string) $value;

        do {
            $decoded = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        } while (true);

        return $value;
    }
}
