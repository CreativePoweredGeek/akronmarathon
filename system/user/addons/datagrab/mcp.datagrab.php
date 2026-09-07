<?php

use BoldMinded\DataGrab\ControlPanel\ModalController;
use BoldMinded\DataGrab\DataTypes\AbstractDataType;
use BoldMinded\DataGrab\Dependency\Litzinger\Basee\App;
use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Version;
use BoldMinded\DataGrab\Service\ConfigurationFactory;
use BoldMinded\DataGrab\Service\DataGrabLoader;
use BoldMinded\DataGrab\Model\ImportStatus;
use ExpressionEngine\Service\Addon\Mcp;
use ExpressionEngine\Service\Sidebar\BasicList;
use ExpressionEngine\Service\Sidebar\FolderItem;
use ExpressionEngine\Service\Sidebar\Header;
use BoldMinded\DataGrab\Traits\FileUploadDestinations;

/**
 * @package     ExpressionEngine
 * @subpackage  Module
 * @category    DataGrab
 * @author      Brian Litzinger
 * @copyright   Copyright (c) 2022 - BoldMinded, LLC
 * @link        https://boldminded.com/add-ons/datagrab
 * @license
 *
 * All rights reserved.
 *
 * This source is commercial software. Use of this software requires a
 * site license for each domain it is used on. Use of this software or any
 * of its source code without express written permission in the form of
 * a purchased commercial or other license is prohibited.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 *
 * As part of the license agreement for this software, all modifications
 * to this source must be submitted to the original author for review and
 * possible inclusion in future releases. No compensation will be provided
 * for patches, although where possible we will attribute each contribution
 * in file revision notes. Submitting such modifications constitutes
 * assignment of copyright to the original author (Brian Litzinger and
 * BoldMinded, LLC) for such modifications. If you do not wish to assign
 * copyright to the original author, your license to  use and modify this
 * source is null and void. Use of this software constitutes your agreement
 * to this clause.
 */
class Datagrab_mcp extends Mcp
{
    use FileUploadDestinations;

    protected $addon_name = 'datagrab';

    /**
     * @var DataGrabLoader
     */
    private $loader;

    private $settings;

    private int|null $importId;

    private bool $isWordpressEdition;

    private $allowedSettings = [
        'type',
        'channel',
        'update',
        'unique',
        'author',
        'author_field',
        'author_check',
        'username',
        'email',
        'screen_name',
        'member_role',
        'member_role_field',
        'offset',
        'limit',
        'title',
        'title_suffix',
        'url_title',
        'url_title_suffix',
        'date',
        'expiry_date',
        'timestamp',
        'delete_old',
        'anonymize_deleted',
        'soft_delete',
        'delete_by_timestamp',
        'delete_by_timestamp_duration',
        'cat_default',
        'cat_field',
        'cat_group',
        'cat_delimiter',
        'cat_sub_delimiter',
        'cat_allow_numeric_names',
        'id',
        'entry_status',
        'update_status',
        'import_comments',
        'comment_author',
        'comment_email',
        'comment_date',
        'comment_url',
        'comment_body',
        'ajw_entry_id',
        'c_groups',
        'update_edit_date',
    ];

    function __construct()
    {
        $this->loader = new DataGrabLoader;

        //$this->checkRequirements();
        $this->generateSidebar();
        $this->loadCss('datagrab');

        $this->isWordpressEdition = DATAGRAB_WORDPRESS;
    }

    private function checkRequirements()
    {
        // Can't do this check here b/c it is only available on the CLI
        if (!extension_loaded('pcntl')) {
            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->cannotClose()
                ->withTitle(lang('error'))
                ->addToBody(lang('datagrab_requirement_pcntl'))
                ->now();
        }
    }

    private function generateSidebar()
    {
        $lastSegment = end(ee()->uri->rsegments);

        /** @var Sidebar $sidebar */
        $sidebar = ee('CP/Sidebar')->make();

        /** @var Header $heading */
        $heading = $sidebar->addHeader(
            'Imports',
            ee('CP/URL')->make('addons/settings/datagrab')
        );

        if (in_array($lastSegment, ['index', 'configure_import', 'settings', 'check_settings', 'save'])) {
            $heading->isActive();
        }

        /** @var BasicList $list */
        $list = $heading->addBasicList();
        $steps = [
            'settings',
            'check_settings',
            'configure_import',
        ];
        $isStep = in_array($lastSegment, $steps);
        $importId = $this->getSession('id', true);

        if ($isStep) {
            foreach ($steps as $page) {
                // Only make linkable if the import has been saved to the DB.
                // Don't want users jumping around out of order.
                if ($importId) {
                    $url = ee('CP/URL')->make('addons/settings/datagrab/' . $page, ['id' => $importId]);
                } else {
                    $url = '#';
                }

                /** @var FolderItem $item */
                $item = $list->addItem(lang('step_' . $page), $url);

                if ($page === $lastSegment) {
                    $item->isActive();
                }
            }
        }

        if (ee()->db->table_exists('datagrab_endpoints')) {
            $heading = $sidebar->addHeader(
                'Endpoints',
                ee('CP/URL')->make('addons/settings/datagrab/endpoints')
            );

            $segments = ee()->uri->rsegments;
            array_pop($segments);

            if (str_contains(end($segments), 'endpoint')) {
                $heading->isActive();
            }
        }

        $sidebar->addHeader(
            'Release Notes',
            ee('CP/URL')->make('addons/settings/datagrab/releases')
        );

        $sidebar->addHeader(
            'Documentation',
            'https://docs.boldminded.com/datagrab/docs',
        );
    }

    /**
     * @param string $filename
     */
    private function loadJavaScript(string $filename = '')
    {
        $contents = file_get_contents(PATH_THIRD . sprintf('datagrab/scripts/%s.js', $filename));
        ee()->cp->add_to_foot(sprintf('<script type="text/javascript">%s</script>', $contents));
    }

    /**
     * @param string $filename
     */
    private function loadCss(string $filename = '')
    {
        $contents = file_get_contents(PATH_THIRD . sprintf('datagrab/styles/%s.css', $filename));
        ee()->cp->add_to_head(sprintf('<style type="text/css">%s</style>', $contents));
    }

    /**
     * @param string $actionName
     * @param int $importId
     * @param array $params
     * @return string
     */
    private function getActionUrl(string $actionName, int $importId = 0, array $params = []): string
    {
        $url = ee()->functions->fetch_site_index(0, 0) .
            QUERY_MARKER . 'ACT=' . ee()->cp->fetch_action_id('Datagrab', $actionName);

        if ($importId) {
            $url = $url . AMP . 'id=' . $importId;
        }

        if (!empty($params)) {
            $url = $url . AMP . http_build_query($params);
        }

        return $url;
    }

    /**
     * Generates the mcp view for the controller action
     *
     * @param string $name
     * @param array $vars
     * @return array
     */
    private function renderView(string $fileName, array $vars = [], array $breadcrumbs = []): array
    {
        return [
            'breadcrumb' => $breadcrumbs,
            'body' => ee('View')->make('datagrab:' . $fileName)->render($vars)
        ];
    }

    private function shouldUpgrade(): bool
    {
        /** @var \ExpressionEngine\Service\Addon\Addon $addon */
        $addon = ee('Addon')->get('datagrab');
        $installedVersion = $addon->getInstalledVersion();
        $fileVersion = $addon->getVersion();

        if (version_compare($fileVersion, $installedVersion, '>')) {
            return true;
        }

        return false;
    }

    public function index()
    {
        if ($this->shouldUpgrade()) {
            return $this->renderView('upgrade');
        }

        // Clear session data
        $this->getSession('settings');
        $this->loadJavaScript('datagrab');

        ee()->view->cp_page_title = DATAGRAB_NAME;

        // Load helpers
        ee()->load->library('table');
        ee()->load->helper('form');
        ee()->load->library('relative_date');

        // Set data
        $data['title'] = DATAGRAB_NAME;
        $data['types'] = ee('datagrab:Importer')->fetch_datatype_names();
        $data['isWordpressEdition'] = $this->isWordpressEdition;

        ee()->javascript->set_global([
            'datagrab.fetch_queue_status' => $this->getActionUrl('fetch_queue_status'),
            'datagrab.purge_queue' => $this->getActionUrl('purge_queue'),
            'datagrab.sort_imports' => $this->getActionUrl('sort_imports'),
        ]);

        // @todo change to Table class and make sortable. See FilePicker->buildTableFromFileCollection for example.
        // ee()->cp->add_js_script('ui', 'sortable');
        $table = ee('CP/Table', [
            'limit' => 0,
            'autosort' => false,
        ]);

        $table->setColumns([
            'Name' => ['encode' => false],
            'Type' => ['encode' => false],
            'Import' => ['encode' => false],
            'Queue' => ['encode' => false],
            'Status' => ['encode' => false],
            'Last run',
            'Manage'  => ['encode' => false],
        ]);

        $table->setNoResultsText(lang('datagrab_no_results'));

        /** @var \BoldMinded\DataGrab\Dependency\Illuminate\Queue\QueueManager $queue */
        $queueConnection = ee('datagrab:QueueManager')->connection('default');

        $modalController = new ModalController();
        $rowData = [];

        $imports = ee('Model')->get('datagrab:Import')
            ->filter('site_id', ee()->config->item('site_id'))
            ->order('order', 'ASC')
            ->order('name', 'ASC')
            ->all();

        foreach ($imports as $row) {
            $id = $row->id;

            $importSettings = json_decode($row->settings, true);
            $channel = $this->getChannel($importSettings['import']['channel'] ?? 0);
            $importType = $importSettings['import']['import_type'] ?? '';

            $importName = $row->name;
            $importDescription = $row->description;
            $importStatus = $row->status;
            $settingsUrl = ee('CP/URL')->make('addons/settings/datagrab/load', ['id' => $row->id]);

            $importAlert = '';
            if (!$channel && $importType === 'entry') {
                $importAlert = ' <i class="fas fa-circle-exclamation" title="The channel assigned to this import no longer exists."></i>';
            }

            $queryParams = [];

            if (isset($row->passkey) && $row->passkey !== '') {
                $queryParams['passkey'] = $row->passkey;
            }

            $importUrl = $this->getActionUrl('run_action', $id, array_merge($queryParams, ['iframe' => 'yes']));
            $directUrl = $this->getActionUrl('run_action', $id, $queryParams);
            // $debugUrl = $this->getActionUrl('run_action', $id, array_merge($queryParams, ['debug' => 'yes']));

            if (ee('datagrab:Importer')->getDeleteQueueSize($id) > 0) {
                $importStatus = ImportStatus::WAITING;
            }

            $types = [
                'wordpress' => 'fa-brands fa-wordpress-simple',
                'wordpress_legacy' => 'fa-brands fa-wordpress-simple',
                'json' => 'fas fa-brackets-curly',
                'json_legacy' => 'fas fa-brackets-curly',
                'csv' => 'fas fa-file-csv',
                'csv_legacy' => 'fas fa-file-csv',
                'xml' => 'fas fa-code',
                'xml_legacy' => 'fas fa-code',
            ];

            $colType = sprintf(
                '<i class="%s"><span class="hidden">%s</span></i>',
                $types[$importSettings['import']['type']] ?? '',
                $importSettings['import']['type']
            );

            $colTitle = sprintf(
                '<a href="%s" title="%s">%s</a>%s',
                $settingsUrl,
                $importDescription,
                $importName,
                $importAlert
            );

            $colActions = '<div class="button-group button-group-xsmall">';
            $colActions .= '<a class="button button--default fas fa-sync disabled" title="' . ($importStatus === ImportStatus::WAITING ? 'Continue' : 'Start') . ' import" data-status="' . $importStatus . '" data-action="dg-sync" data-id="' . $id . '" href="#"><span class="hidden">Sync</span></a>';
            $colActions .= '<a class="button button--default fas fa-power-off disabled" title="Reset import" data-action="dg-reset" data-id="' . $id . '" href="' . ee('CP/URL')->make('addons/settings/datagrab/reset', ['id' => $id]) . '"><span class="hidden">Reset Import</span></a>';
            $colActions .= '<a class="button button--default fas fa-trash-can-xmark disabled hidden" title="Purge Queue" data-action="dg-purge" data-id="' . $id . '" href="#"><span class="hidden">Purge Queue</span></a>';
            $colActions .= '</div>';

            $colQueueSize = '<div class="queue-size" data-id="' . $row->id . '">' . ee('datagrab:Importer')->getImportQueueSize($id) . '</div>';

            $colStatus = ImportStatus::getDisplayStatus(
                $id,
                $row->status,
                $row->last_record ?? 0,
                $row->total_records ?? 0,
                $row->error_records ?? 0,
                $queueConnection->size(ee('datagrab:Importer')->getImportQueueName($id)),
                $queueConnection->size(ee('datagrab:Importer')->getDeleteQueueName($id))
            );

            $colStatus .= '<div class="import-progress"><div class="import-progress-bar" data-id="' . $id . '" data-src="' . $importUrl .'">
                <div class="progress-bar">
                    <div class="progress" style="width: '. ImportStatus::getPercentage($row->last_record, $row->total_records) .'%;"></div>
                </div>
            </div></div>';

            $colLastRun = ee()->localize->human_time($row->last_run);

            $colToolbar = '<div class="button-group button-group-xsmall">';
            $colToolbar .= '<a class="button button--default handle" title="Drag to reorder" href="#"><i class="icon--reorder"></i></a>';
            $colToolbar .= '<a class="button button--default dropdown-toggle js-dropdown-toggle" data-dropdown-pos="bottom-end"><i class="fal fa-angle-down"></i></a>';
            $colToolbar .= '<div class="dropdown" x-placement="bottom-end">';
            $colToolbar .= '<a class="dropdown__link" title="Edit saved import name, description, and passkey" href="' . ee('CP/URL')->make('addons/settings/datagrab/load', ['id' => $id, 'redirect' => 'settings']) . '"><i class="fas fa-pencil"></i> Edit Settings</a>';
            $colToolbar .= '<a class="dropdown__link" title="Edit import field configuration" href="' . ee('CP/URL')->make('addons/settings/datagrab/load', ['id' => $id]) . '"><i class="fas fa-cog"></i> Configure</a>';
            $colToolbar .= '<a class="dropdown__link" title="Display URL to run import from outside Control Panel" onclick="alert(\'' . $directUrl . '\'); return false;" href="' . $directUrl . '"><i class="fas fa-hashtag"></i> Import URL</a>';
            $colToolbar .= '<a class="dropdown__link" title="Clone import" href="' . ee('CP/URL')->make('addons/settings/datagrab/clone', ['id' => $id]) . '"><i class="fas fa-copy"></i> Clone</a>';
            $colToolbar .= '<a class="dropdown__link dropdown__link--danger m-link" rel="modal-confirm-remove-'. $id .'" data-confirm="'. $importName .'"title="Delete saved import" href="#"><i class="fas fa-trash-alt"></i> Delete</a>';
            // $col['toolbar'] .= '<a class="dropdown__link" title="Debug" href="' . $debugUrl . '">Debug</a>';
            $colToolbar .= '</div>';
            $colToolbar .= '</div>';

            $sortOrder = '<input type="hidden" name="order[]" value="' . $row->id .'">';

            $column = [
                $colTitle . $sortOrder,
                $colType,
                $colActions,
                $colQueueSize,
                $colStatus,
                $colLastRun,
                $colToolbar,
            ];

            $rowData[] = [
                'attrs' => [],
                'columns' => $column
            ];

            $modalController->create('modal-confirm-remove-' . $id, 'ee:_shared/modal_confirm_remove', [
                'form_url' => ee('CP/URL')->make('addons/settings/datagrab/delete', ['id' => $id]),
                'hidden' => ['id' => $id],
                'checklist' => [['kind' => 'Import', 'desc' => $importName]]
            ]);
        }

        $table->setData($rowData);

        $data['table'] = $table;
        $data['form_action'] = ee('CP/URL', 'addons/settings/datagrab/settings');
        $data['releases_url'] = ee('CP/URL', 'addons/settings/datagrab/releases');

        $this->clearSession();

        return $this->renderView('index', $data);
    }

    /**
     * @param int $channelId
     * @return null|Channel
     */
    private function getChannel(int $channelId = 0)
    {
        if ($channelId === 0) {
            $channelId = $this->settings['import']['channel'] ?? 0;
        }

        $channel = null;

        if ($channelId) {
            $channel = ee('Model')->get('Channel', (int) $channelId)->first();
        }

        return $channel;
    }

    public function settings()
    {
        $this->getInput();
        $this->loadJavaScript('settings');

        ee()->lang->loadfile('datagrab');

        // Fetch channel names
        $query = ee()->db
            ->select('channel_id, channel_title')
            ->where('site_id', ee()->config->item('site_id'))
            ->order_by('channel_title')
            ->get('channels');

        $channels = [];
        foreach ($query->result_array() as $row) {
            $channels[$row['channel_id']] = $row['channel_title'];
        }

        // Get settings form for type
        /** @var AbstractDataType $currentType */
        $currentType = ee('datagrab:Importer')->datatypes[$this->settings['import']['type']] ?? null;

        if (!$currentType) {
            ee()->functions->redirect(ee('CP/URL', 'addons/settings/datagrab')->compile());
        }

        $dataTypeSettings = $currentType->settings_form($this->settings) ?: [];

        if (App::isLtEE7() || $this->isWordpressEdition) {
            $importTypes = ['entry' => 'Entry'];
        } else {
            $importTypes = ['entry' => 'Entry', 'file' => 'File', 'member' => 'Member'];
        }

        $sections = [
            [
                'title' => $currentType->datatype_info['name'] . ' v' . $currentType->datatype_info['version'],
                'desc' => $currentType->datatype_info['description']
            ],
            [
                'title' => 'Import Type',
                'desc' => 'Select which type of import to perform',
                'attrs' => [
                    'class' => 'js-dg-import-type',
                ],
                'fields' => [
                    'import_type' => [
                        'required' => true,
                        'type' => 'select',
                        'choices' => $importTypes,
                        'value' => $this->settings['import']['import_type'] ?? 'entry',
                    ],
                ]
            ],
            [
                'title' => 'Channel',
                'desc' => 'Select the channel to import the data into',
                'attrs' => [
                    'class' => 'js-dg-import-channel hidden',
                ],
                'fields' => [
                    'channel' => [
                        'required' => true,
                        'type' => 'select',
                        'choices' => $channels,
                        'value' => $this->settings['import']['channel'] ?? '',
                    ],
                ]
            ],
            [
                'title' => 'File Directory',
                'desc' => 'Select the directory to import files into',
                'attrs' => [
                    'class' => 'js-dg-import-file hidden',
                ],
                'fields' => [
                    'file_directory' => [
                        'required' => true,
                        'type' => 'html',
                        'content' => $this->buildFileUploadDropdown(
                            fieldName: 'file_directory',
                            defaultValue: $this->settings['import']['file_directory'] ?? '',
                        ),
                    ],
                ]
            ],
        ];

        // Append additional settings for the requested datatype
        foreach ($dataTypeSettings as $setting) {
            $sections[] = $setting;
        }

        $data['cp_page_title'] = '1. Import Settings';
        $data['sections'] = [$sections];
        $data['base_url'] = ee('CP/URL')->make('addons/settings/datagrab/check_settings')->compile();
        $data['save_btn_text'] = 'Check Settings';
        $data['save_btn_text_working'] = 'Checking...';
        $data['form_hidden'] = [
            'datagrab_step' => 'settings',
        ];

        return [
            'body' => ee('View')->make('ee:_shared/form')->render($data),
            'breadcrumb' => [
                ee('CP/URL', 'addons/settings/datagrab')->compile() => ee()->lang->line('datagrab_module_name'),
                ee('CP/URL', 'addons/settings/datagrab/settings')->compile() => '1. Import Settings',
            ],
        ];
    }

    public function check_settings()
    {
        $this->getInput();

        $data['id'] = $this->getImportId();
        $data['cp_page_title'] = '2. Check Settings';
        $data['sections'] = [$this->checkSettingsFields()];
        $data['base_url'] = ee('CP/URL')->make('addons/settings/datagrab/configure_import')->compile();
        $data['save_btn_text'] = 'Configure Import';
        $data['save_btn_text_working'] = 'btn_saving';
        $data['form_hidden'] = [
            'datagrab_step' => 'check_settings',
            'id' => $data['id'],
        ];

        return [
            'body' => ee('View')->make('ee:_shared/form')->render($data),
            'breadcrumb' => [
                ee('CP/URL', 'addons/settings/datagrab')->compile() => ee()->lang->line('datagrab_module_name'),
                ee('CP/URL', 'addons/settings/datagrab/check_settings')->compile() => '2. Check Settings',
            ],
        ];
    }

    private function checkSettingsFields(): array
    {
        try {
            // https://boldminded.com/support/ticket/2940
            if (
                empty($this->settings) ||
                !isset($this->settings['import']['type'])
            ) {
                $this->loadSettings();
            }

            $importType = $this->settings['import']['type'] ?? '';
            /** @var AbstractDataType $currentType */
            $currentType = ee('datagrab:Importer')->datatypes[$importType] ?? null;

            if (!$currentType) {
                ee('datagrab:Importer')->logger->log(lang('datagrab_import_type_not_found'));
                throw new Error(lang('datagrab_import_type_not_found'));
            }

            $currentType->isConfigMode = true;
            $currentType->initialise($this->settings);
            $ret = $currentType->fetch();
        } catch (Error $exception) {
            ee('CP/Alert')->makeInline('datagrab-form')
                ->asIssue()
                ->cannotClose()
                ->withTitle(lang('datagrab_configuration_error'))
                ->addToBody($exception->getMessage())
                ->addToBody(lang('datagrab_troubleshooting'))
                ->now();
        }

        if ($currentType === null) {
            ee('CP/Alert')->makeInline('datagrab-form')
                ->asIssue()
                ->cannotClose()
                ->withTitle(lang('datagrab_configuration_error'))
                ->addToBody(lang('datagrab_troubleshooting_import_type'))
                ->now();

            ee('datagrab:Importer')->logger->log(sprintf('Import settings: %s', print_r($this->settings, true)));
        }

        if ($currentType && count($currentType->getErrors()) > 0) {
            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->cannotClose()
                ->withTitle(lang('datagrab_configuration_error'))
                ->addToBody($currentType->getErrors())
                ->addToBody(lang('datagrab_troubleshooting'))
                ->now();

            return [];
        }

        if ($currentType && count($currentType->getWarnings()) > 0) {
            ee('CP/Alert')->makeInline('shared-form')
                ->asWarning()
                ->cannotClose()
                ->withTitle(lang('datagrab_configuration_warning'))
                ->addToBody(lang('datagrab_configuration_warning_desc'))
                ->addToBody($currentType->getWarnings())
                ->addToBody(lang('datagrab_troubleshooting'))
                ->now();
        }

        $fields = [];

        if ($currentType && $ret != -1) {
            $titles = $currentType->fetch_columns();

            if (empty($titles)) {
                ee('CP/Alert')->makeInline('datagrab-form')
                    ->asIssue()
                    ->cannotClose()
                    ->withTitle(lang('datagrab_configuration_error'))
                    ->addToBody(sprintf(lang('datagrab_no_fields_found'), $currentType->type))
                    ->now();
            } else {
                foreach ($titles as $value) {
                    $fields[] = array($value);
                }
            }
        }

        return [
            [
                'title' => 'Fields',
                'desc' => 'The following unique fields were found in your import file:',
                'fields' => [
                    'html' => [
                        'type' => 'html',
                        'content' => implode('<br />', array_column($fields, 0)),
                    ]
                ]
            ]
        ];
    }

    private function configureFileImport($currentType, array $data = []): array
    {
        $this->getInput();

        ee()->load->library('table');
        ee()->load->helper('form');

        $importName = $this->getSession('name', true);

        $data['title'] = $importName ? sprintf('Configure Import: %s', $importName) : 'Configure Import';
        $data['datatype_info'] = $currentType->datatype_info;
        $data['datatype_settings'] = $currentType->settings;
        $data['default_settings'] = $this->settings;
        $data['data_fields'][''] = '';

        $currentType->isConfigMode = true;
        $fields = $currentType->fetch_columns();

        if (is_array($fields)) {
            foreach ($fields as $key => $value) {
                $data['data_fields'][$key] = $value;
            }
        }

        $data['cf_config'] = [];

        $handler = $this->loader->loadFieldTypeHandler('file', true);

        $data['cf_config'][] = $handler->display_configuration(
            ee('datagrab:Importer'),
            'import_file',
            'File',
            'file',
            true,
            $data
        );

        $data['category_groups'] = [];
        $data['import_directory_name'] = '';

        $uploadDirectoryId = $data['default_settings']['import']['file_directory'] ?? null;

        if ($uploadDirectoryId) {
            $directory = $this->getUploadDirectory($uploadDirectoryId);
            $destination = $this->getUploadDestination($directory->upload_location_id);

            $data['import_directory_name'] = $directory->title;

            foreach ($destination->getCategoryGroups() as $row) {
                $data['category_groups'][$row->group_id] = $row->group_name;
            }
        }

        $data['data_fields'][''] = '';
        $currentType->isConfigMode = true;
        $fields = $currentType->fetch_columns();
        if (is_array($fields)) {
            foreach ($fields as $key => $value) {
                $data['data_fields'][$key] = $value;
            }
        }

        // Get list of authors
        // @todo: filter this list by member groups
        $data['authors'] = [];

        ee()->db->select('member_id, screen_name');
        $query = ee()->db->get('exp_members');
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $data['authors'][$row['member_id']] = $row['screen_name'];
            }
        }

        $data['author_fields'] = array(
            'member_id' => 'ID',
            'username' => 'Username',
            'screen_name' => 'Screen Name',
            'email' => 'Email address'
        );

        ee()->db->select('m_field_id, m_field_label');
        ee()->db->from('exp_member_fields');
        ee()->db->order_by('m_field_order ASC');
        $query = ee()->db->get();
        if ($query->num_rows() > 0) {
            $memberFields = [];
            foreach ($query->result_array() as $row) {
                $memberFields['m_field_id_' . $row['m_field_id']] = $row['m_field_label'];
            }
            $data['author_fields']['Custom Fields'] = $memberFields;
        }

        return $data;
    }

    private function configureMemberImport($currentType, array $data = []): array
    {
        $this->getInput();

        ee()->load->library('table');
        ee()->load->helper('form');

        $importName = $this->getSession('name', true);

        $data['title'] = $importName ? sprintf('Configure Import: %s', $importName) : 'Configure Import';
        $data['datatype_info'] = $currentType->datatype_info;
        $data['datatype_settings'] = $currentType->settings;
        $data['default_settings'] = $this->settings;

        $data['data_fields'][''] = '';
        $currentType->isConfigMode = true;
        $fields = $currentType->fetch_columns();

        if (is_array($fields)) {
            foreach ($fields as $key => $value) {
                $data['data_fields'][$key] = $value;
            }
        }

        // Member fields are global rather than scoped to a channel
        $data['custom_fields'] = [];
        $data['field_types'] = [];
        $data['field_settings'] = [];
        $data['field_required'] = [];
        $data['category_groups'] = [];
        $data['cf_config'] = [];

        $memberFields = ee('Model')->get('MemberField')
            ->order('m_field_order', 'ASC')
            ->all();

        foreach ($memberFields as $field) {
            $data['custom_fields'][$field->m_field_name] = $field->m_field_label;
            $data['field_types'][$field->m_field_name] = $field->m_field_type;
            $data['field_settings'][$field->m_field_name] = $field->m_field_settings;
            $data['field_required'][$field->m_field_name] = $field->m_field_required;

            $handler = $this->loader->loadFieldTypeHandler($field->m_field_type, true);

            $data['cf_config'][] = $handler->display_configuration(
                ee('datagrab:Importer'),
                $field->m_field_name,
                $field->m_field_label,
                $field->m_field_type,
                $field->m_field_required === 'y',
                $data
            );
        }

        // Roles available to assign to imported members
        $data['roles'] = [];

        foreach (ee('Model')->get('Role')->order('name', 'ASC')->all() as $role) {
            $data['roles'][$role->role_id] = $role->name;
        }

        return $data;
    }

    private function configureChannelImport($currentType, array $data = []): array
    {
        /** @var \ExpressionEngine\Model\Channel\Channel $channel */
        $channel = $this->getChannel();

        if (!$channel) {
            ee('CP/Alert')->makeBanner()
                ->asIssue()
                ->withTitle('The requested channel does not exist.')
                ->addToBody(sprintf('The channel <i>%s</i> was configured with no longer exists. You will need to delete this import configuration and re-create it.', $channel->getName()))
                ->defer();

            ee()->functions->redirect(ee('CP/URL', 'addons/settings/datagrab')->compile());
        }

        $data['channel_title'] = $channel->channel_title;
        $data['custom_fields'] = [];
        $data['unique_fields'] = [];
        $data['field_settings'] = [];
        $data['field_required'] = [];
        $data['unique_fields'][''] = '';
        $data['unique_fields']['title'] = 'Title';
        $data['unique_fields']['url_title'] = 'URL Title';
        $data['field_types'] = [];

        foreach ($channel->getAllCustomFields() as $field) {
            $data['custom_fields'][$field->field_name] = $field->field_label;
            $data['field_types'][$field->field_name] = $field->field_type;
            $data['field_settings'][$field->field_name] = $field->field_settings;
            $data['field_required'][$field->field_name] = $field->field_required;

            // Filter out complex types that can't easily do a scalar comparison
            if (!in_array(
                $field->field_type,
                ['grid', 'relationship', 'fluid_field', 'bloqs', 'file_grid', 'ansel'])
            ) {
                $data['unique_fields'][$field->field_name] = $field->field_label;
            }
        }

        $data['category_groups'] = [];
        foreach ($channel->getCategoryGroups() as $row) {
            $data['category_groups'][$row->group_id] = $row->group_name;
        }

        $data['data_fields'][''] = '';
        $currentType->isConfigMode = true;
        $fields = $currentType->fetch_columns();
        if (is_array($fields)) {
            foreach ($fields as $key => $value) {
                $data['data_fields'][$key] = $value;
            }
        }

        // Get list of authors
        // @todo: filter this list by member groups
        $data['authors'] = [];

        ee()->db->select('member_id, screen_name');
        $query = ee()->db->get('exp_members');
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $data['authors'][$row['member_id']] = $row['screen_name'];
            }
        }

        $data['author_fields'] = array(
            'member_id' => 'ID',
            'username' => 'Username',
            'screen_name' => 'Screen Name',
            'email' => 'Email address'
        );

        ee()->db->select('m_field_id, m_field_label');
        ee()->db->from('exp_member_fields');
        ee()->db->order_by('m_field_order ASC');
        $query = ee()->db->get();
        if ($query->num_rows() > 0) {
            $memberFields = [];
            foreach ($query->result_array() as $row) {
                $memberFields['m_field_id_' . $row['m_field_id']] = $row['m_field_label'];
            }
            $data['author_fields']['Custom Fields'] = $memberFields;
        }

        // Get statuses
        $data['status_fields'] = array_filter(array_merge(
            ['default' => 'Channel default'],
            $channel->Statuses->getDictionary('status', 'status'),
            $data['data_fields']
        ));

        // Allow comments - check datatype ?
        $allowComments = $currentType->datatype_info['allow_comments'] ?? false;
        $data['allow_comments'] = (bool) $allowComments;

        // Allow multiple fields?
        $data['allow_multiple_fields'] = $currentType->datatype_info['allow_multiple_fields'] ?? false;

        // Load up any custom config tables for 3rd party add-ons
        $moduleHandlers = $this->loader->fetchModuleHandlers();
        foreach ($moduleHandlers as $handler) {
            $data['cm_config'][$handler->getDisplayName()] =
                $handler
                    ->setSettings($this->settings)
                    ->displayConfiguration(ee('datagrab:Importer'), $data);
        }

        $data['all_fields'] = [];
        $data['all_fields']['title'] = 'Title';
        $data['all_fields']['exp_channel_titles.entry_id'] = 'Entry ID';
        $data['all_fields']['exp_channel_titles.url_title'] = 'URL Title';

        $all_fields = ee('Model')
            ->get('ChannelField')
            ->filter('site_id', 'IN', [0, ee()->config->item('site_id')])
            ->all();

        foreach ($all_fields as $field) {
            $data['all_fields']['field_id_' . $field->field_id] = $field->field_label;
        }

        // Default settings
        if (isset ($currentType->config_defaults)) {
            foreach ($currentType->config_defaults as $field => $value) {
                if (!isset($this->settings[$field])) {
                    $this->settings['config'][$field] = $value;
                }
            }
        }

        $data['datatype_info'] = $currentType->datatype_info;
        $data['datatype_settings'] = $currentType->settings;
        $data['default_settings'] = $this->settings;
        $data['cf_config'] = [];

        // Build configuration table for custom fields
        foreach ($data['custom_fields'] as $field_name => $field_label) {
            $fieldType = $data['field_types'][$field_name];
            $fieldRequired = $data['field_required'][$field_name];

            /** @var AbstractDataType $handler */
            $handler = $this->loader->loadFieldTypeHandler($fieldType, true);

            if ($handler) {
                $data['cf_config'][] = $handler->display_configuration(
                    ee('datagrab:Importer'),
                    $field_name,
                    $field_label,
                    $fieldType,
                    $fieldRequired,
                    $data
                );
            }
        }

        if (!empty($currentType->getErrors())) {
            ee('CP/Alert')->makeInline('datagrab-form')
                ->asIssue()
                ->cannotClose()
                ->withTitle(lang('datagrab_configuration_error'))
                ->addToBody($currentType->getErrors())
                ->addToBody(lang('datagrab_troubleshooting'))
                ->now();
        }

        if (!empty($currentType->getWarnings())) {
            ee('CP/Alert')->makeInline('datagrab-form')
                ->asWarning()
                ->cannotClose()
                ->withTitle(lang('datagrab_configuration_warning'))
                ->addToBody(lang('datagrab_configuration_warning_desc'))
                ->addToBody($currentType->getWarnings())
                ->addToBody(lang('datagrab_troubleshooting'))
                ->now();
        }

        return $data;
    }

    /**
     * @return array
     */
    public function configure_import(): array
    {
        $this->getInput();
        $this->loadJavaScript('configure');

        ee()->load->library('table');
        ee()->load->helper('form');

        $importType = $this->settings['import']['import_type'] ?? 'entry';
        $importName = $this->getSession('name', true);

        $data['title'] = $importName ? sprintf('Configure Import: <b>%s</b>', $importName) : 'Configure Import';
        $data['content'] = 'configure_import';
        $data['importType'] = $importType;

        try {
            // Get list of fields from the datatype
            /** @var AbstractDataType $currentType */
            $currentType = ee('datagrab:Importer')->datatypes[$this->settings['import']['type']];

            if ($currentType === null) {
                $message = sprintf(
                    'Can not initialize datatype: %s',
                    $this->settings['import']['type']
                );

                ee('datagrab:Importer')->logger->log($message);

                throw new Exception($message);
            }

            $currentType->initialise($this->settings);
            $currentType->fetch();
        } catch (Exception $exception) {
            ee('CP/Alert')->makeInline('datagrab-form')
                ->asIssue()
                ->cannotClose()
                ->withTitle(lang('datagrab_configuration_error'))
                ->addToBody($exception->getMessage())
                ->addToBody(lang('datagrab_troubleshooting'))
                ->now();

            return [
                'body' => ee()->load->view('error', [], true),
                'breadcrumb' => [
                    ee('CP/URL', 'addons/settings/datagrab')->compile() => ee()->lang->line('datagrab_module_name'),
                    ee('CP/URL', 'addons/settings/datagrab/configure_import')->compile() => '3. Configure Import',
                ],
            ];
        }

        $import = ee('Model')->get('datagrab:Import')
            ->filter('id', $this->getImportId())
            ->first();

        $legacySettings = $import?->settings_legacy ? unserialize($import->settings_legacy) : '';

        if ($importType === 'file') {
            $data = $this->configureFileImport($currentType, $data);

            $data['legacySettings'] = $legacySettings;
            $data['fieldSets'] = [];

            $configurationFactory = new ConfigurationFactory(
                allowComments: $data['allow_comments'] ?? false,
                authors: $data['authors'] ?? [],
                authorFields: $data['author_fields'] ?? [],
                categoryGroups: $data['category_groups'] ?? [],
                customFields: $data['cf_config'] ?? [],
                dataFields: $data['data_fields'] ?? [],
                defaultSettings: $data['default_settings']['config'] ?? [],
                importId: $this->getImportId(),
                statusFields: $data['status_fields'] ?? [],
                uniqueFields: $data['unique_fields'] ?? [],
            );

            $data['checkSettings'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'fieldset_group',
                    'settings' => $this->checkSettingsFields()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Import Properties',
                    'settings' => $configurationFactory->fieldSetImportProperties()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Default Entry Fields',
                    'settings' => $configurationFactory->fieldSetFileDefault()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Custom File Fields',
                    'settings' => $configurationFactory->fieldSetCustom()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Categories',
                    'settings' => $configurationFactory->fieldSetCategories()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Additional Options',
                    'settings' => $configurationFactory->fieldSetAdditionalOptions()
                ]);
        } elseif ($importType === 'member') {
            $data = $this->configureMemberImport($currentType, $data);

            $data['legacySettings'] = $legacySettings;
            $data['fieldSets'] = [];

            $configurationFactory = new ConfigurationFactory(
                customFields: $data['cf_config'] ?? [],
                dataFields: $data['data_fields'] ?? [],
                defaultSettings: $data['default_settings']['config'] ?? [],
                importId: $this->getImportId(),
                roles: $data['roles'] ?? [],
                isMemberImport: true,
            );

            $data['checkSettings'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'fieldset_group',
                    'settings' => $this->checkSettingsFields()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Import Properties',
                    'settings' => $configurationFactory->fieldSetImportProperties()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Default Member Fields',
                    'settings' => $configurationFactory->fieldSetMemberDefault()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Custom Member Fields',
                    'settings' => $configurationFactory->fieldSetCustom()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Handle Duplicates',
                    'settings' => $configurationFactory->fieldSetMemberHandleDuplicates()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Additional Options',
                    'settings' => $configurationFactory->fieldSetAdditionalOptions()
                ]);
        } else {
            $data = $this->configureChannelImport($currentType, $data);

            $data['legacySettings'] = $legacySettings;
            $data['fieldSets'] = [];

            $configurationFactory = new ConfigurationFactory(
                allowComments: $data['allow_comments'] ?? false,
                authors: $data['authors'] ?? [],
                authorFields: $data['author_fields'] ?? [],
                categoryGroups: $data['category_groups'] ?? [],
                customFields: $data['cf_config'] ?? [],
                dataFields: $data['data_fields'] ?? [],
                defaultSettings: $data['default_settings']['config'] ?? [],
                importId: $this->getImportId(),
                statusFields: $data['status_fields'] ?? [],
                uniqueFields: $data['unique_fields'] ?? [],
            );

            $data['checkSettings'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'fieldset_group',
                    'settings' => $this->checkSettingsFields()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Import Properties',
                    'settings' => $configurationFactory->fieldSetImportProperties()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Default Entry Fields',
                    'settings' => $configurationFactory->fieldSetDefault()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Custom Entry Fields',
                    'settings' => $configurationFactory->fieldSetCustom()
                ]);

            if (isset($data['cm_config'])) {
                foreach ($data['cm_config'] as $moduleName => $moduleSettings) {
                    $data['fieldSets'][] = ee('View')
                        ->make('ee:_shared/form/section')
                        ->render([
                            'name' => $moduleName,
                            'settings' => $moduleSettings
                        ]);
                }
            }

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Categories',
                    'settings' => $configurationFactory->fieldSetCategories()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Handle Duplicates',
                    'settings' => $configurationFactory->fieldSetHandleDuplicates()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Comments',
                    'settings' => $configurationFactory->fieldSetComments()
                ]);

            $data['fieldSets'][] = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'Additional Options',
                    'settings' => $configurationFactory->fieldSetAdditionalOptions()
                ]);
        }

        $cfFieldNames = array_keys($data['custom_fields'] ?? []);
        $shared = array_intersect($this->allowedSettings, $cfFieldNames);

        if (!empty($shared)) {
            ee('CP/Alert')
                ->makeInline('datagrab-form')
                ->asIssue()
                ->withTitle('Custom Field Name Collision')
                ->addToBody('The following custom fields use the same name as a reserved setting name in DataGrab. 
                    Please change the following custom field short names, otherwise the import will fail.')
                ->addToBody($shared)
                ->now();
        }

        // Form action URLs
        $data['form_action'] = ee('CP/URL', 'addons/settings/datagrab/save_configuration');
        $data['back_link'] = ee('CP/URL')->make('addons/settings/datagrab/settings', ['id' => $this->getImportId()]);
        $data['form_hidden'] = [
            'datagrab_step' => 'configure_import',
            'id' => $this->getImportId(),
        ];

        $data['importFormat'] = $this->settings['import']['type'] ?? 'entry';
        $data['importType'] = $this->settings['import']['import_type'] ?? 'entry';
        $data['importId'] = $this->getImportId();

        return [
            'body' => ee()->load->view('_wrapper', $data, true),
            'breadcrumb' => [
                ee('CP/URL', 'addons/settings/datagrab')->compile() => ee()->lang->line('datagrab_module_name'),
                ee('CP/URL', 'addons/settings/datagrab/configure_import')->compile() => '3. Configure Import',
            ],
        ];
    }

    /**
     * @throws \Random\RandomException
     */
    public function save_configuration()
    {
        $this->getInput();

        ee()->load->helper('date');

        $importId = $this->getImportId();
        // Import props are saved under this array key as to avoid collision
        // with fields that could also be named "name", "description", which
        // are pretty common custom field names. This is only necessary after
        // moving the import props fields the main config page to avoid an
        // extra step in the save process.
        $importProps = ee()->input->post('dg_import_props');

        $data = [
            'name' => $importProps['name'] ?? '',
            'description' => $importProps['description'] ?? '',
            'passkey' => $importProps['passkey'] ?? '',
            'migration' => $importProps['migration'] ?? 0,
            'last_run' => now(),
        ];

        if (isset($this->settings['import']['type'])) {
            $data['settings'] = json_encode($this->settings);
        } else {
            // Fetch settings from database
            ee()->db->select('settings');
            ee()->db->where('id', $importId);
            $query = ee()->db->get('datagrab');
            $row = $query->row_array();
            $data['settings'] = $row['settings'];
            $this->settings = json_decode($data['settings'], true);
        }

        $data['site_id'] = ee()->config->item('site_id');

        if (!$importId) {
            if ($data['passkey'] === '') {
                $data['passkey'] = $this->generatePasskey();
            }

            ee()->db->insert('datagrab', array_merge($data, [
                'status' => ImportStatus::NEW,
                'version' => (float) ee('Addon')->get('datagrab')->getInstalledVersion(),
            ]));

            $importId = ee()->db->insert_id();

            $this->settings['import']['id'] = $importId;
            $data['settings'] = json_encode($this->settings);
        }

        ee()->db->where('id', $importId);
        ee()->db->update('datagrab', $data);

        $alertMessage = '';

        if (ee()->input->post('migration')) {
            $alertMessage = 'Migration created';
        }

        $props = ee()->input->post('dg_import_props');

        $alert = ee('CP/Alert');
        $alert
            ->makeInline('shared-form')
            ->asSuccess()
            ->withTitle(sprintf('<i>%s</i> import saved.', $props['name']))
            ->addToBody($alertMessage)
            ->defer();

        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/datagrab'));
    }

    /**
     * @throws \Random\RandomException
     */
    private function generatePasskey(int $length = 16): string
    {
        $chars = '0123456789ABCDEF';
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $randomString;
    }

    public function load()
    {
        $this->loadSettings();
        $page = 'configure_import';

        if (ee('Request')->get('redirect')) {
            $page = ee('Request')->get('redirect');
        }

        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/datagrab/' . $page, [
            'id' => ee()->input->get('id')
        ]));
    }

    private function loadSettings()
    {
        $id = ee()->input->get('id');

        if ($id) {
            /** @var CI_DB_result $query */
            $query = ee('db')->where('id', $id)->get('datagrab');
            $row = $query->row_array();
            $this->settings = json_decode($row['settings'], true);
            $this->settings['import']['id'] = $id;
            $this->setSession('settings', json_encode($this->settings));
            $this->setSession('name', $row['name']);
            $this->setSession('id', $id);
        }
    }

    public function reset()
    {
        if (ee()->input->get('id')) {
            ee('datagrab:Importer')->resetImport(ee()->input->get('id'), true);
        }

        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/datagrab'));
    }

    public function clone()
    {
        if (ee()->input->get('id')) {
            $db = ee('db');

            /** @var CI_DB_result $query */
            $query = $db->where('id', ee()->input->get('id'))->get('datagrab');
            $row = $query->row_array();

            $name = $row['name'] . ' [Clone]';

            $db->insert('datagrab', [
                'name' => $name,
                'description' => $row['description'],
                'settings' => $row['settings'],
                'passkey' => $row['pass_key'],
                'site_id' => $row['site_id'],
                'total_records' => 0,
                'last_record' => 0,
                'total_delete_records' => 0,
                'last_delete_record' => 0,
                'error_records' => 0,
                'status' => ImportStatus::NEW,
                'last_run' => null,
                'last_started' => null,
            ]);

            $newId = $db->insert_id();

            $this->settings = json_decode($row['settings'], true);
            $this->settings['import']['id'] = $newId;
            $this->setSession('settings', json_encode($this->settings));
            $this->setSession('name', $name);
            $this->setSession('id', $newId);
        }

        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/datagrab/configure_import'));
    }

    function delete()
    {
        $id = ee()->input->post('id');

        if ($id != '' && $id != '0') {
            $import = ee('Model')->get('datagrab:Import', $id)->first();
            $import->delete();

            ee('CP/Alert')->makeInline('shared-form')
                ->asSuccess()
                ->cannotClose()
                ->withTitle(lang('success'))
                ->addToBody(sprintf('%s deleted', $import->name))
                ->defer();
        }

        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/datagrab'));
    }

    /*

    HELPER FUNCTIONS

    */

    /**
     * Add $data to user session
     *
     * @param string $key
     * @param string $data
     * @return void
     */
    private function setSession($key, $data): void
    {
        @session_start();

        if (!isset($_SESSION[DATAGRAB_NAME])) {
            $_SESSION[DATAGRAB_NAME] = [];
        }

        $_SESSION[DATAGRAB_NAME][$key] = $data;
    }

    /**
     * Retrieve data from session. Data is removed from session unless $keep is
     * set to true
     *
     * @param string $key
     * @param string $keep
     * @return string $data
     */
    private function getSession($key, $keep = false): string
    {
        @session_start();

        if (isset($_SESSION[DATAGRAB_NAME]) && isset($_SESSION[DATAGRAB_NAME][$key])) {
            $data = $_SESSION[DATAGRAB_NAME][$key];

            if (!$keep) {
                unset($_SESSION[DATAGRAB_NAME][$key]);
            }

            return $data;
        }

        return '';
    }

    private function clearSession()
    {
        $_SESSION[DATAGRAB_NAME] = [];
    }

    private function getImportId(): int
    {
        $getPostImportId = ee()->input->get_post('id');

        if ($getPostImportId) {
            return (int) $getPostImportId;
        }

        return (int) $this->getSession('id', true);
    }

    /**
     * Handle input from forms, sessions
     *
     * Collects data from forms, query strings and sessions. Only keeps relevant data
     * for the current import data type. Stores in session to allow back-and-forth
     * through 'wizard'
     *
     */
    private function getInput()
    {
        // Grab them before they're erased and SESSION is reset
        $importName = $this->getSession('name', true);
        $this->importId = $this->getImportId();

        // Get current settings from session
        $this->settings = json_decode($this->getSession('settings'), true) ?: [];
        $datagrabStep = ee()->input->get_post('datagrab_step', 'default');

        switch ($datagrabStep) {
            // Step 1: choose import type
            case 'index':
            {
                $this->settings['import']['type'] = ee()->input->get_post('type');
                break;
            }
            // Step 2: set up datatype
            case 'settings':
            {
                $importType = ee()->input->get_post('import_type');

                $this->settings['import']['import_type'] = $importType;

                if ($importType === 'file') {
                    $this->settings['import']['file_directory'] = ee()->input->get_post('file_directory_filedir');
                } elseif ($importType === 'member') {
                    // Member fields are global, so there is no target to store.
                } else {
                    $this->settings['import']['channel'] = ee()->input->get_post('channel');
                }

                // Check datatype specific settings
                if (isset($this->settings['import']['type']) && $this->settings['import']['type'] != '') {
                    /** @var AbstractDataType $currentType */
                    $currentType = ee('datagrab:Importer')->datatypes[$this->settings['import']['type']];
                    $dataTypeSettings = $currentType->settings;
                    foreach ($dataTypeSettings as $option => $value) {
                        if (ee()->input->get_post($option) !== false) {
                            $this->settings['datatype'][$option] = ee()->input->get_post($option);
                        }
                    }
                }

                break;
            }
            case 'configure_import':
            {
                // Look through permitted settings, check whether a new POST var exists, and update
                foreach ($this->allowedSettings as $setting) {
                    if (ee()->input->post($setting) !== false) {
                        $this->settings['config'][$setting] = ee()->input->post($setting);
                    }
                }

                if (ee()->input->post('limit') !== false) {
                    $this->settings['import']['limit'] = ee()->input->post('limit');
                }

                // Don't allow value below 1. Recommended default is 50.
                if ($this->settings['import']['limit'] < 1) {
                    $this->settings['import']['limit'] = 1;
                }

                // Hack to handle checkboxes (whose post vars are not set if unchecked)
                // todo: improve this - use hidden field?
                if (ee()->input->get('method') == 'import') {
                    $checkboxes = ['update', 'delete_old', 'anonymize_deleted', 'soft_delete', 'import_comments'];
                    foreach ($checkboxes as $check) {
                        if (!isset($this->settings['config'][$check])) {
                            $this->settings['config'][$check] = ee()->input->post($check);
                        }
                    }
                }

                // Get category group details
                $categorySettings = [
                    'cat_default',
                    'cat_field',
                    'cat_delimiter',
                    'cat_sub_delimiter',
                    'cat_allow_numeric_names',
                ];
                $categoryGroups = ee()->input->post('c_groups');
                foreach (explode('|', $categoryGroups) as $cat_group_id) {
                    foreach ($categorySettings as $cs) {
                        $setting = $cs . '_' . $cat_group_id;
                        if (ee()->input->post($setting) !== false) {
                            $this->settings['config'][$setting] = ee()->input->post($setting);
                        }
                    }
                }

                if (
                    isset($this->settings['import']['import_type']) &&
                    $this->settings['import']['import_type'] === 'file'
                ) {
                    $this->settings['cf'] = [];

                    if (ee()->input->post('import_file') !== false) {
                        $this->settings['cf']['import_file'] = ee()->input->post('import_file');
                    }

                    $handler = $this->loader->loadFieldTypeHandler('file');

                    $this->settings['cf']['import_file'] = $handler->save_configuration(
                        ee('datagrab:Importer'),
                        'import_file',
                        $this->settings['cf']['import_file']
                    );
                } else if (
                    isset($this->settings['import']['import_type']) &&
                    $this->settings['import']['import_type'] === 'member'
                ) {
                    $this->settings['cf'] = [];

                    $memberFields = ee('Model')->get('MemberField')
                        ->order('m_field_order', 'ASC')
                        ->all();

                    foreach ($memberFields as $row) {
                        if (ee()->input->post($row->m_field_name) !== false) {
                            $this->settings['cf'][$row->m_field_name] = ee()->input->post($row->m_field_name);
                        }

                        $handler = $this->loader->loadFieldTypeHandler($row->m_field_type);

                        if (!$handler) {
                            $handler = $this->loader->loadFieldTypeHandler('default');
                        }

                        if (!isset($this->settings['cf'][$row->m_field_name]) ||
                            !is_array($this->settings['cf'][$row->m_field_name])
                        ) {
                            ee('datagrab:Importer')->logger->log(sprintf(
                                'Error saving configuration for member field: %s',
                                $row->m_field_name,
                            ));
                        }

                        $this->settings['cf'][$row->m_field_name] = $handler->save_configuration(
                            ee('datagrab:Importer'),
                            $row->m_field_name,
                            $this->settings['cf'][$row->m_field_name] ?? []
                        );
                    }
                } else if (
                    isset($this->settings['import']['channel']) &&
                    $this->settings['import']['channel'] != ''
                ) {
                    $this->settings['cf'] = [];
                    $channel = ee('Model')->get('Channel', $this->settings['import']['channel'])->first();

                    // Look through field types and see if they need to register any extra variables
                    foreach ($channel->getAllCustomFields() as $row) {
                        if (ee()->input->post($row->field_name) !== false) {
                            $this->settings['cf'][$row->field_name] = ee()->input->post($row->field_name);
                        }

                        $handler = $this->loader->loadFieldTypeHandler($row->field_type);

                        if (!$handler) {
                            $handler = $this->loader->loadFieldTypeHandler('default');
                        }

                        if (!is_array($this->settings['cf'][$row->field_name])) {
                            ee('datagrab:Importer')->logger->log(sprintf(
                                'Error saving configuration for field: %s',
                                $row->field_name,
                            ));
                        }

                        $this->settings['cf'][$row->field_name] = $handler->save_configuration(
                            ee('datagrab:Importer'),
                            $row->field_name,
                            $this->settings['cf'][$row->field_name]
                        );
                    }
                }

                // Load up any custom config tables for 3rd party add-ons
                $moduleHandlers = $this->loader->fetchModuleHandlers();
                foreach ($moduleHandlers as $handler) {
                    $this->settings['cm'][$handler->getName()] = $handler->saveConfiguration(ee('datagrab:Importer'));
                }

                break;
            }
        }

        // Get saved import id
        if (ee()->input->get_post('id')) {
            $this->settings['import']['id'] = ee()->input->get_post('id');
        }

        // Store settings in session
        $this->setSession('settings', json_encode($this->settings));
        $this->setSession('name', $importName);
        $this->setSession('id', $this->importId);
    }

    public function releases()
    {
        $version = new Version();
        $allVersions = $version->setAddon('datagrab')->fetchAll();

        $releases = [];

        foreach ($allVersions as $version) {
            $releases[] = [
                'date' => $version->dateFormatted,
                'version' => $version->version,
                'notes' => html_entity_decode($version->notes),
                'isNew' => version_compare($version->version, DATAGRAB_VERSION, '>'),
                'currentVersion' => DATAGRAB_VERSION,
            ];
        }

        $vars['releases'] = $releases;

        $vars['message'] = ee('CP/Alert')->makeInline('datagrab-releases')
            ->asAttention()
            ->cannotClose()
            ->withTitle('Stay up-to-date!')
            ->addToBody('The latest version of '. DATAGRAB_NAME .' can be downloaded from your <a href="https://boldminded.com/account/licenses">BoldMinded account</a> or <a href="https://expressionengine.com/store/licenses-add-ons">ExpressionEngine.com</a>')
            ->render();

        return $this->renderView('releases', $vars, [
            ee('CP/URL', 'addons/settings/datagrab')->compile() => ee()->lang->line('datagrab_module_name'),
            ee('CP/URL', 'addons/settings/datagrab/releases')->compile() => 'Release Notes',
        ]);
    }
}

