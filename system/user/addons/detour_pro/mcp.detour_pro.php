<?php

/**
 * Detour Pro Module Control Panel File
 *
 * @category   Module
 * @package    ExpressionEngine
 * @subpackage Addons
 * @author     EEHarbor <help@eeharbor.com>
 * @license    https://eeharbor.com/license EEHarbor Add-on License
 * @link       https://eeharbor.com/detour_pro
 */

// This is what we have to do for EE2 support
require_once 'addon.setup.php';

use EEHarbor\DetourPro\FluxCapacitor\Base\Mcp;
use EEHarbor\DetourPro\FluxCapacitor\FluxCapacitor;
use EEHarbor\DetourPro\Service\AnalyticsService;
use EEHarbor\DetourPro\Service\ChartDataService;

class Detour_pro_mcp extends Mcp
{
    public $flux;
    public $return_data;
    public $return_array = array();

    private $settings;
    private $_base_url;
    private $_data           = array();
    private $_module         = 'detour_pro';
    private $_detour_methods = array(
        '301' => '301',
        '302' => '302',
    );

    private $search   = '';
    private $sort     = '';
    private $sort_dir = 'asc';
    private $analytics_service;
    private $chart_data_builder;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->_base_url  = $this->flux->getBaseURL();
        $this->settings   = $this->flux->getSettings();

        // For the EE3 version, setup the header area and settings cog.
        ee()->view->header = array(
            'title'         => lang('detour_pro_module_name'),
            'toolbar_items' => array(
                'settings' => array(
                    'href'  => ee('CP/URL', 'addons/settings/detour_pro/settings'),
                    'title' => lang('title_setting'),
                ),
            ),
        );
    }

    private function analyticsService()
    {
        $site_id = (int) ee()->config->item('site_id');

        if (!$this->analytics_service instanceof AnalyticsService) {
            $this->analytics_service = ee('detour_pro:AnalyticsService');
        }

        $this->analytics_service->set_site_id($site_id);

        return $this->analytics_service;
    }

    private function chartDataBuilder()
    {
        $palette = $this->get_dashboard_palette();

        if (!$this->chart_data_builder instanceof ChartDataService) {
            $this->chart_data_builder = ee('detour_pro:ChartDataService');
        }

        $this->chart_data_builder->set_palette($palette);

        return $this->chart_data_builder;
    }

    // ----------------------------------------------------------------

    //! Index View and Save

    /**
     * Index Function
     *
     * @return  void
     */
    public function index()
    {
        return $this->dashboard();
    }

    public function detours()
    {
        // Load language file
        ee()->lang->loadfile('detour_pro');
        ee()->javascript->set_global('lang.remove_confirm', lang('detour_redirects') . ': <b>### ' . lang('detour_redirects') . '</b>');

        $this->ensureExtensionEnabled();

        if (!defined('URL_THIRD_THEMES')) {
            define('URL_THIRD_THEMES', ee()->config->slash_item('theme_folder_url') . 'third_party/');
        }

        ee()->cp->add_to_foot('<script type="text/javascript" charset="utf-8" src="' . URL_THIRD_THEMES . 'detour_pro/js/chart.min.js?v' . $this->version . '"></script>');

        $displayHits                 = false;
        $this->_data['display_hits'] = false;

        // Find out if we need to display the hits counter or not.
        if (isset($this->settings->hit_counter) && $this->settings->hit_counter == 'y') {
            $displayHits                 = true;
            $this->_data['display_hits'] = true;
        }

        // Get search query from request
        if (!empty(ee()->input->get('search'))) {
            $this->search = urldecode((string) ee()->input->get('search'));
        }

        // Create table using CP/Table service with fromGlobals for automatic parameter handling
        $base_url = ee('CP/URL')->make('addons/settings/detour_pro/detours');

        $table = ee('CP/Table', array(
            'sort_col_qs_var' => 'sort_col',
            'sort_dir_qs_var' => 'sort_dir',
            'limit' => 20,
            'search' => $this->search
        ));

        // Map column labels to database field names for sorting
        $sort_map = array(
            'title_url' => 'original_url',
            'title_redirect' => 'new_url',
            'title_method' => 'detour_method',
            'title_start' => 'sort_start_date',
            'title_end' => 'sort_end_date'
        );

        // Define columns
        $columns = array(
            'title_url' => array(
                'sort' => true
            ),
            'title_redirect' => array(
                'sort' => true
            ),
            'title_note' => array(
                'encode' => false
            ),
            'title_method' => array(
                'sort' => true,
                'encode' => false
            ),
            'title_start' => array(
                'sort' => true
            ),
            'title_end' => array(
                'sort' => true
            )
        );

        // Add hits column if enabled
        if ($displayHits) {
            $columns['title_hits'] = array(
                'sort' => true
            );
            $sort_map['title_hits'] = 'sort_hits';
        }

        // Add checkbox column for bulk delete
        $columns[] = array(
            'type' => \ExpressionEngine\Library\CP\Table::COL_CHECKBOX
        );

        $table->setColumns($columns);

        // Set no results text
        $table->setNoResultsText(
            ee()->lang->line('dir_no_detours'),
            '',
            ''
        );

        // Get sort column and direction from table
        $sort_col_label = $table->sort_col;
        $sort_dir = $table->sort_dir;

        // Map label to database field
        $this->sort = isset($sort_map[$sort_col_label]) ? $sort_map[$sort_col_label] : 'original_url';
        $this->sort_dir = $sort_dir;

        // Use resolved CP/Table paging values from globals (native EE pattern).
        $per_page = isset($table->config['limit']) ? (int) $table->config['limit'] : 20;
        if ($per_page < 1) {
            $per_page = 20;
        }
        $page = isset($table->config['page']) ? (int) $table->config['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $per_page;

        // Get total count for pagination + current page rows.
        $total_detours = $this->count_detours();
        $current_detours = $this->get_detours(null, $offset, $per_page, $displayHits);

        // Format data for table
        $data = array();
        foreach ($current_detours as $detour) {
            $display_new_url = (isset($detour['new_url']) && $detour['new_url'] !== '') ? $detour['new_url'] : '/';
            $note_icon = '';

            if (isset($detour['note']) && trim((string) $detour['note']) !== '') {
                $note_icon = '<span class="detour-note-icon" role="img" aria-label="' . htmlspecialchars(lang('label_detour_note'), ENT_QUOTES, 'UTF-8') . '"><i class="fal fa-comment" aria-hidden="true"></i></span>';
            }

            $row = array(
                'title_url' => array(
                    'content' => $detour['original_url'],
                    'href' => $detour['update_link']
                ),
                'title_redirect' => $display_new_url,
                'title_note' => $note_icon,
                'title_method' => '<strong>' . $detour['detour_method'] . '</strong>',
                'title_start' => $detour['start_date'],
                'title_end' => $detour['end_date']
            );

            if ($displayHits) {
                $row['title_hits'] = $detour['hits'];
            }

            $row[] = array(
                'name' => 'detour_delete[]',
                'value' => $detour['detour_id'],
                'data' => array(
                    'confirm' => ee()->lang->line('title_detour') . ': <b>' . htmlspecialchars((string) $detour['original_url'], ENT_QUOTES, 'UTF-8') . '</b>'
                )
            );

            $data[] = $row;
        }

        $table->setData($data);

        // Render table and pagination from resolved base URL.
        $this->_data['table'] = $table->viewData($base_url);
        $pagination_base_url = $this->_data['table']['base_url'];
        $this->_data['pagination'] = ee('CP/Pagination', $total_detours)
            ->perPage($per_page)
            ->currentPage($page)
            ->render($pagination_base_url);

        // If we're not on page 1 and there are no detours, redirect to page 1.
        if ($page > 1 && count($current_detours) == 0) {
            ee()->functions->redirect($pagination_base_url);
        }

        $this->_data['ee_ver'] = substr(APP_VER, 0, 1);
        $this->_data['search_query'] = $this->search;
        $this->_data['base_url'] = $base_url;
        $this->_data['search_url'] = $this->_form_url('search_post', array('orig_search' => 'detours'));
        $this->_data['delete_action_url'] = $this->_form_url('delete_detours');
        $this->_data['add_detour_link'] = $this->flux->moduleURL('addUpdate');
        $this->_data['cp_heading'] = ee()->lang->line('nav_home');
        $this->_data['toolbar_items'] = array(
            'add' => array(
                'href' => $this->_data['add_detour_link'],
                'title' => ee()->lang->line('label_add_detour'),
                'content' => '<i class="fal fa-plus"></i> ' . ee()->lang->line('label_add_detour')
            )
        );
        $this->_data['detour_options'] = array(
            'detour' => ee()->lang->line('option_detour'),
            'ignore' => ee()->lang->line('option_ignore'),
        );
        $this->_data['detour_methods'] = $this->_detour_methods;
        $this->_data['current_detours'] = $current_detours;
        $this->_data['hit_counter_enabled'] = $displayHits;
        $this->_data['enable_hit_counter_url'] = $this->_form_url('enable_hit_counter');
        $detours_default_range = 'month';
        $detours_range_definitions = array(
            'day' => array('days' => 1),
            'week' => array('days' => 7),
            'month' => array('days' => 30),
            'year' => array('days' => 365),
        );
        $detours_chart_ranges = array();
        $empty_chart = array('labels' => array(), 'datasets' => array());

        foreach ($detours_range_definitions as $range_key => $range) {
            $days = (int) $range['days'];
            $top_redirect_rows = array();

            if ($displayHits) {
                $rows = $this->analyticsService()->get_top_301_hit_rows($days, 10);
                foreach ($rows as $row) {
                    $detour_id = isset($row['detour_id']) ? (int) $row['detour_id'] : 0;
                    $top_redirect_rows[] = array(
                        'label' => isset($row['label']) ? $row['label'] : '',
                        'hits' => isset($row['hits']) ? (int) $row['hits'] : 0,
                        'link' => $detour_id > 0 ? $this->flux->moduleURL('addUpdate', array('id' => $detour_id)) : '',
                    );
                }
            }

            $top_redirects_chart = $displayHits
                ? $this->chartDataBuilder()->build_top_301_chart(
                    lang('detours_top_10_redirects_hit'),
                    $top_redirect_rows
                )
                : $empty_chart;

            $detours_chart_ranges[$range_key] = array(
                'top_redirects_title' => lang('detours_top_10_redirects_hit'),
                'top_redirects' => $top_redirects_chart,
            );
        }

        $this->_data['detours_chart_data'] = json_encode(array(
            'default_range' => $detours_default_range,
            'ranges' => $detours_chart_ranges,
        ));

        $this->skinSupport();
        return $this->flux->view('index', $this->_data, true);
    }

    public function import()
    {
        ee()->lang->loadfile('detour_pro');

        $this->ensureExtensionEnabled();
        $this->skinSupport();
        $this->cleanupImportStages();

        $delimiter_options = array(
            'comma' => lang('import_delimiter_comma'),
            'tab' => lang('import_delimiter_tab'),
            'semicolon' => lang('import_delimiter_semicolon'),
        );

        $selected_delimiter = ee()->input->post('delimiter') ? ee()->input->post('delimiter') : 'comma';
        if (!array_key_exists((string) $selected_delimiter, $delimiter_options)) {
            $selected_delimiter = 'comma';
        }

        $skip_existing = ee()->input->post('skip_existing') ? true : false;
        if (!ee()->input->post('submit_upload')) {
            $skip_existing = true;
        }

        $selected_method = ee()->input->post('fallback_method')
            ? ee()->input->post('fallback_method')
            : (isset($this->settings->default_method) ? $this->settings->default_method : '301');
        if (!array_key_exists($selected_method, $this->_detour_methods)) {
            $selected_method = '301';
        }

        if (ee()->input->post('submit_upload')) {
            $stage = $this->createImportStageFromUpload(
                isset($_FILES['import_file']) ? $_FILES['import_file'] : array(),
                $selected_delimiter,
                $skip_existing,
                $selected_method
            );

            if (!empty($stage['success']) && !empty($stage['token'])) {
                ee()->functions->redirect($this->flux->moduleURL('import_map', array('token' => $stage['token'])));
            }

            ee('CP/Alert')->makeInline()
                ->asIssue()
                ->addToBody(!empty($stage['message']) ? $stage['message'] : lang('import_error_unreadable'))
                ->defer();
        }

        $this->_data['wizard_step'] = 'upload';
        $this->_data['action_url'] = $this->_form_url('import');
        $this->_data['delimiter_options'] = $delimiter_options;
        $this->_data['selected_delimiter'] = $selected_delimiter;
        $this->_data['detour_methods'] = $this->_detour_methods;
        $this->_data['selected_method'] = $selected_method;
        $this->_data['skip_existing'] = $skip_existing;

        return $this->flux->view('import', $this->_data, true);
    }

    public function import_map()
    {
        ee()->lang->loadfile('detour_pro');

        $this->ensureExtensionEnabled();
        $this->skinSupport();
        $this->cleanupImportStages();

        $token = (string) ee()->input->get_post('token');
        $stage = $this->loadImportStage($token);

        if (empty($stage)) {
            ee('CP/Alert')->makeInline()
                ->asIssue()
                ->addToBody(lang('import_error_stage_invalid'))
                ->defer();
            ee()->functions->redirect($this->flux->moduleURL('import'));
        }

        $field_labels = $this->getImportFieldLabels();
        $header_options = array();
        foreach ($stage['headers'] as $index => $header) {
            $header_label = trim((string) $header);
            if ($header_label === '') {
                $header_label = sprintf(lang('import_column_label'), $index + 1);
            }
            $header_options[(string) $index] = $header_label;
        }

        $mapping = isset($stage['mapping']) && is_array($stage['mapping'])
            ? $stage['mapping']
            : $this->suggestImportMapping($stage['headers']);
        $selected_method = ee()->input->post('selected_method')
            ? (string) ee()->input->post('selected_method')
            : (isset($stage['selected_method']) ? (string) $stage['selected_method'] : (string) $stage['fallback_method']);

        if (ee()->input->post('submit_mapping')) {
            $mapping_result = $this->validateImportMapping(ee()->input->post('mapping'), $stage['headers']);
            $resolved_method = $this->resolveImportMethod($selected_method, '');
            $mapping_errors = $mapping_result['errors'];

            if ($resolved_method === false) {
                $mapping_errors[] = lang('import_error_row_invalid_method');
            }

            if (!$mapping_result['valid'] || !empty($mapping_errors)) {
                ee('CP/Alert')->makeInline()
                    ->asIssue()
                    ->addToBody(implode('<br>', $mapping_errors))
                    ->defer();
                $mapping = $mapping_result['mapping'];
            } else {
                $stage['mapping'] = $mapping_result['mapping'];
                $stage['selected_method'] = $resolved_method;
                $this->saveImportStage($stage['token'], $stage);
                ee()->functions->redirect($this->flux->moduleURL('import_preview', array('token' => $stage['token'])));
            }
        }

        $sample_rows = $this->readImportSampleRows($stage, 5);
        $optional_header_options = array('' => lang('import_do_not_import')) + $header_options;

        if (!defined('URL_THIRD_THEMES')) {
            define('URL_THIRD_THEMES', ee()->config->slash_item('theme_folder_url') . 'third_party/');
        }
        ee()->cp->add_to_foot('<script type="text/javascript" charset="utf-8" src="' . URL_THIRD_THEMES . 'detour_pro/js/import_wizard.js?v' . $this->version . '"></script>');

        $this->_data['wizard_step'] = 'map';
        $this->_data['token'] = $stage['token'];
        $this->_data['action_url'] = $this->_form_url('import_map');
        $this->_data['back_url'] = $this->flux->moduleURL('import');
        $this->_data['preview_url'] = $this->flux->moduleURL('import_preview', array('token' => $stage['token']));
        $this->_data['headers'] = $stage['headers'];
        $this->_data['header_options'] = $header_options;
        $this->_data['optional_header_options'] = $optional_header_options;
        $this->_data['mapping'] = $mapping;
        $this->_data['selected_method'] = $selected_method;
        $this->_data['detour_methods'] = $this->_detour_methods;
        $this->_data['field_labels'] = $field_labels;
        $this->_data['sample_rows'] = $sample_rows;
        $this->_data['sample_rows_json'] = json_encode($sample_rows);

        return $this->flux->view('import', $this->_data, true);
    }

    public function import_preview()
    {
        ee()->lang->loadfile('detour_pro');

        $this->ensureExtensionEnabled();
        $this->skinSupport();

        $token = (string) ee()->input->get_post('token');
        $stage = $this->loadImportStage($token);

        if (empty($stage)) {
            ee('CP/Alert')->makeInline()
                ->asIssue()
                ->addToBody(lang('import_error_stage_invalid'))
                ->defer();
            ee()->functions->redirect($this->flux->moduleURL('import'));
        }

        $mapping_result = $this->validateImportMapping(isset($stage['mapping']) ? $stage['mapping'] : array(), $stage['headers']);
        if (!$mapping_result['valid']) {
            ee('CP/Alert')->makeInline()
                ->asIssue()
                ->addToBody(lang('import_error_mapping_required'))
                ->defer();
            ee()->functions->redirect($this->flux->moduleURL('import_map', array('token' => $stage['token'])));
        }

        $stage['mapping'] = $mapping_result['mapping'];
        $stage['selected_method'] = $this->resolveImportMethod(
            isset($stage['selected_method']) ? $stage['selected_method'] : '',
            isset($stage['fallback_method']) ? $stage['fallback_method'] : '301'
        );
        if ($stage['selected_method'] === false) {
            ee('CP/Alert')->makeInline()
                ->asIssue()
                ->addToBody(lang('import_error_row_invalid_method'))
                ->defer();
            ee()->functions->redirect($this->flux->moduleURL('import_map', array('token' => $stage['token'])));
        }
        $this->saveImportStage($stage['token'], $stage);

        $result = $this->runImportProcess($stage, true);
        $field_labels = $this->getImportFieldLabels();

        $this->_data['wizard_step'] = 'preview';
        $this->_data['token'] = $stage['token'];
        $this->_data['action_url'] = $this->_form_url('import_execute');
        $this->_data['back_url'] = $this->flux->moduleURL('import_map', array('token' => $stage['token']));
        $this->_data['preview_rows'] = $result['preview_rows'];
        $this->_data['invalid_rows'] = $result['invalid_rows'];
        $this->_data['counters'] = $result['counters'];
        $this->_data['field_labels'] = $field_labels;

        return $this->flux->view('import', $this->_data, true);
    }

    public function import_execute()
    {
        ee()->lang->loadfile('detour_pro');

        $this->ensureExtensionEnabled();
        $this->skinSupport();

        $token = (string) ee()->input->post('token');
        $stage = $this->loadImportStage($token);

        if (empty($stage)) {
            ee('CP/Alert')->makeInline()
                ->asIssue()
                ->addToBody(lang('import_error_stage_invalid'))
                ->defer();
            ee()->functions->redirect($this->flux->moduleURL('import'));
        }

        $mapping_result = $this->validateImportMapping(isset($stage['mapping']) ? $stage['mapping'] : array(), $stage['headers']);
        if (!$mapping_result['valid']) {
            ee('CP/Alert')->makeInline()
                ->asIssue()
                ->addToBody(lang('import_error_mapping_required'))
                ->defer();
            ee()->functions->redirect($this->flux->moduleURL('import_map', array('token' => $stage['token'])));
        }

        $stage['mapping'] = $mapping_result['mapping'];
        $stage['selected_method'] = $this->resolveImportMethod(
            isset($stage['selected_method']) ? $stage['selected_method'] : '',
            isset($stage['fallback_method']) ? $stage['fallback_method'] : '301'
        );
        if ($stage['selected_method'] === false) {
            ee('CP/Alert')->makeInline()
                ->asIssue()
                ->addToBody(lang('import_error_row_invalid_method'))
                ->defer();
            ee()->functions->redirect($this->flux->moduleURL('import_map', array('token' => $stage['token'])));
        }
        $result = $this->runImportProcess($stage, false);

        $inserted_count = isset($result['counters']['inserted']) ? (int) $result['counters']['inserted'] : (isset($result['counters']['insert']) ? (int) $result['counters']['insert'] : 0);
        $updated_count = isset($result['counters']['updated']) ? (int) $result['counters']['updated'] : (isset($result['counters']['update']) ? (int) $result['counters']['update'] : 0);
        $skipped_existing_count = isset($result['counters']['skipped_existing']) ? (int) $result['counters']['skipped_existing'] : (isset($result['counters']['skip_existing']) ? (int) $result['counters']['skip_existing'] : 0);
        $skipped_invalid_count = isset($result['counters']['skipped_invalid']) ? (int) $result['counters']['skipped_invalid'] : (isset($result['counters']['skip_invalid']) ? (int) $result['counters']['skip_invalid'] : 0);

        $summary = sprintf(
            lang('import_summary'),
            $inserted_count,
            $updated_count,
            $skipped_existing_count,
            $skipped_invalid_count
        );

        if (!empty($result['invalid_rows'])) {
            $summary .= '<br><br>' . lang('import_invalid_rows_intro') . '<ul>';
            foreach ($result['invalid_rows'] as $error_row) {
                $summary .= '<li>' . htmlspecialchars(sprintf(lang('import_invalid_row_line_reason'), $error_row['line'], $error_row['reason']), ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $summary .= '</ul>';
        }

        $this->deleteImportStage($stage);

        ee('CP/Alert')->makeInline()
            ->asSuccess()
            ->addToBody($summary)
            ->defer();

        ee()->functions->redirect($this->flux->moduleURL('detours'));
    }

    private function createImportStageFromUpload($file, $delimiter_key, $skip_existing, $fallback_method)
    {
        $delimiter = $this->getImportDelimiterFromKey($delimiter_key);
        if ($delimiter === null) {
            return array('success' => false, 'message' => lang('import_error_invalid_delimiter'));
        }

        if (empty($file) || !isset($file['tmp_name']) || !isset($file['error'])) {
            return array('success' => false, 'message' => lang('import_error_no_file'));
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return array('success' => false, 'message' => lang('import_error_no_file'));
        }

        $tmp_name = (string) $file['tmp_name'];
        $handle = is_uploaded_file($tmp_name) ? fopen($tmp_name, 'r') : false;
        if ($handle === false) {
            return array('success' => false, 'message' => lang('import_error_unreadable'));
        }

        $headers = fgetcsv($handle, 20000, $delimiter, '"', '\\');
        fclose($handle);

        if (!is_array($headers) || empty($headers)) {
            return array('success' => false, 'message' => lang('import_error_header_required'));
        }

        $headers = array_values(array_map('trim', $headers));
        if (isset($headers[0])) {
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
        }

        $has_header_value = false;
        foreach ($headers as $header) {
            if ($header !== '') {
                $has_header_value = true;
                break;
            }
        }

        if (!$has_header_value) {
            return array('success' => false, 'message' => lang('import_error_header_required'));
        }

        $token = md5(uniqid((string) mt_rand(), true));
        if (function_exists('random_bytes')) {
            try {
                $token = bin2hex(random_bytes(16));
            } catch (\Exception $e) {
                // Fallback to md5 uniqid token for older PHP/runtime environments.
            }
        }

        $stage_dir = $this->getImportStageDir();
        $csv_path = $stage_dir . $token . '.csv';

        if (!move_uploaded_file($tmp_name, $csv_path) && !copy($tmp_name, $csv_path)) {
            return array('success' => false, 'message' => lang('import_error_unreadable'));
        }

        $stage = array(
            'token' => $token,
            'site_id' => (int) ee()->config->item('site_id'),
            'member_id' => (int) ee()->session->userdata('member_id'),
            'created_at' => time(),
            'csv_path' => $csv_path,
            'delimiter' => $delimiter_key,
            'skip_existing' => !empty($skip_existing),
            'fallback_method' => $fallback_method,
            'headers' => $headers,
            'mapping' => array(),
        );

        if (!$this->saveImportStage($token, $stage)) {
            if (is_file($csv_path)) {
                @unlink($csv_path);
            }

            return array('success' => false, 'message' => lang('import_error_stage_write'));
        }

        return array('success' => true, 'token' => $token);
    }

    private function loadImportStage($token)
    {
        $token = trim((string) $token);
        if ($token === '' || !preg_match('/^[a-f0-9]{16,64}$/', $token)) {
            return false;
        }

        $path = $this->getImportStageDir() . $token . '.json';
        if (!is_file($path)) {
            return false;
        }

        $json = file_get_contents($path);
        $stage = json_decode((string) $json, true);
        if (!is_array($stage)) {
            return false;
        }

        if (!isset($stage['token']) || $stage['token'] !== $token) {
            return false;
        }

        $current_site_id = (int) ee()->config->item('site_id');
        $current_member_id = (int) ee()->session->userdata('member_id');

        if (!isset($stage['site_id'], $stage['member_id']) || (int) $stage['site_id'] !== $current_site_id || (int) $stage['member_id'] !== $current_member_id) {
            return false;
        }

        if (empty($stage['csv_path']) || !is_file($stage['csv_path']) || empty($stage['headers']) || !is_array($stage['headers'])) {
            return false;
        }

        return $stage;
    }

    private function saveImportStage($token, $stage)
    {
        $path = $this->getImportStageDir() . $token . '.json';
        $encoded = json_encode($stage);

        return file_put_contents($path, $encoded, LOCK_EX) !== false;
    }

    private function cleanupImportStages($maxAgeSeconds = 86400)
    {
        $stage_dir = $this->getImportStageDir();
        $now = time();
        $json_files = glob($stage_dir . '*.json');

        if (!is_array($json_files)) {
            return;
        }

        foreach ($json_files as $json_file) {
            $contents = file_get_contents($json_file);
            $stage = json_decode((string) $contents, true);

            $created_at = isset($stage['created_at']) ? (int) $stage['created_at'] : (int) @filemtime($json_file);
            if ($created_at <= 0) {
                $created_at = $now;
            }

            if (($now - $created_at) <= (int) $maxAgeSeconds) {
                continue;
            }

            if (is_array($stage) && !empty($stage['csv_path']) && is_file($stage['csv_path'])) {
                @unlink($stage['csv_path']);
            }

            @unlink($json_file);
        }
    }

    private function deleteImportStage($stage)
    {
        if (!is_array($stage) || empty($stage['token'])) {
            return;
        }

        if (!empty($stage['csv_path']) && is_file($stage['csv_path'])) {
            @unlink($stage['csv_path']);
        }

        $json_path = $this->getImportStageDir() . $stage['token'] . '.json';
        if (is_file($json_path)) {
            @unlink($json_path);
        }
    }

    private function normalizeImportUrls($original_url, $new_url)
    {
        $original_url = trim((string) $original_url);
        $new_url = trim((string) $new_url);

        if (!empty($this->settings->allow_trailing_slash) && $this->settings->allow_trailing_slash == 1) {
            return array($original_url, $new_url);
        }

        $normalized_original = trim($original_url, '/');
        $normalized_new = ($new_url === '/') ? '/' : trim($new_url, '/');

        return array($normalized_original, $normalized_new);
    }

    private function parseIsoDateOrNull($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);
        $errors = \DateTime::getLastErrors();
        if (
            $date === false
            || ($errors !== false && (!empty($errors['warning_count']) || !empty($errors['error_count'])))
        ) {
            return false;
        }

        return $date->format('Y-m-d');
    }

    private function resolveImportMethod($raw_method, $fallback_method)
    {
        $method = trim((string) $raw_method);
        if ($method === '') {
            $method = trim((string) $fallback_method);
        }

        return array_key_exists($method, $this->_detour_methods) ? $method : false;
    }

    private function buildMappedRow($csv_row, $mapping, $defaults)
    {
        $errors = array();

        $raw_original_url = ltrim((string) $this->getMappedCsvValue($csv_row, $mapping, 'original_url'), "\xEF\xBB\xBF");
        $raw_new_url = $this->getMappedCsvValue($csv_row, $mapping, 'new_url');

        if ($raw_original_url === '' || $raw_new_url === '') {
            $errors[] = lang('import_error_row_missing_required');
        }

        list($original_url, $new_url) = $this->normalizeImportUrls($raw_original_url, $raw_new_url);
        if ($original_url === '' || $new_url === '') {
            $errors[] = lang('import_error_row_missing_required');
        }

        $method = $this->resolveImportMethod(
            isset($defaults['selected_method']) ? $defaults['selected_method'] : '',
            isset($defaults['fallback_method']) ? $defaults['fallback_method'] : '301'
        );

        if ($method === false) {
            $errors[] = lang('import_error_row_invalid_method');
        }

        $mapped_start = isset($mapping['start_date']) && $mapping['start_date'] !== '';
        $mapped_end = isset($mapping['end_date']) && $mapping['end_date'] !== '';

        $start_date = null;
        if ($mapped_start) {
            $start_date = $this->parseIsoDateOrNull($this->getMappedCsvValue($csv_row, $mapping, 'start_date'));
            if ($start_date === false) {
                $errors[] = lang('import_error_row_invalid_start_date');
            }
        }

        $end_date = null;
        if ($mapped_end) {
            $end_date = $this->parseIsoDateOrNull($this->getMappedCsvValue($csv_row, $mapping, 'end_date'));
            if ($end_date === false) {
                $errors[] = lang('import_error_row_invalid_end_date');
            }
        }

        return array(
            'is_valid' => empty($errors),
            'errors' => $errors,
            'data' => array(
                'original_url' => $original_url,
                'new_url' => $new_url,
                'detour_method' => $method,
                'start_date' => $start_date,
                'end_date' => $end_date,
            ),
            'mapped_optional' => array(
                'start_date' => $mapped_start,
                'end_date' => $mapped_end,
            ),
        );
    }

    private function applyMappedRow($mapped_row, $options, &$counters, &$errors)
    {
        $site_id = isset($options['site_id']) ? (int) $options['site_id'] : (int) ee()->config->item('site_id');
        $simulate = !empty($options['simulate']);
        $skip_existing = !empty($options['skip_existing']);
        ee()->db->where('site_id', $site_id);
        ee()->db->where('original_url', $mapped_row['data']['original_url']);
        $existing = ee()->db->get('detours', 1)->row_array();

        if (!empty($existing)) {
            if ($skip_existing) {
                if ($simulate) {
                    $counters['would_skip_existing']++;
                } else {
                    $counters['skipped_existing']++;
                }
                return 'skip_existing';
            }

            $update_data = array(
                'new_url' => $mapped_row['data']['new_url'],
                'detour_method' => $mapped_row['data']['detour_method'],
            );

            if (!empty($mapped_row['mapped_optional']['start_date'])) {
                $update_data['start_date'] = $mapped_row['data']['start_date'];
            }

            if (!empty($mapped_row['mapped_optional']['end_date'])) {
                $update_data['end_date'] = $mapped_row['data']['end_date'];
            }

            if (!$simulate) {
                ee()->db->update('detours', $update_data, array('detour_id' => $existing['detour_id']));
            }

            if ($simulate) {
                $counters['would_update']++;
            } else {
                $counters['updated']++;
            }
            return 'update';
        }

        if (!$simulate) {
            $insert_data = array(
                'original_url' => $mapped_row['data']['original_url'],
                'new_url' => $mapped_row['data']['new_url'],
                'detour_method' => $mapped_row['data']['detour_method'],
                'site_id' => $site_id,
                'start_date' => $mapped_row['data']['start_date'],
                'end_date' => $mapped_row['data']['end_date'],
            );

            ee()->db->insert('detours', $insert_data);
            $detour_id = ee()->db->insert_id();

            ee()->db->where('original_url', $mapped_row['data']['original_url']);
            ee()->db->where('site_id', $site_id);
            ee()->db->where('detour_id IS NULL', null, false);
            ee()->db->update('detours_not_found', array('detour_id' => $detour_id));
        }

        if ($simulate) {
            $counters['would_insert']++;
        } else {
            $counters['inserted']++;
        }
        return 'insert';
    }

    private function runImportProcess($stage, $simulate = false, $preview_limit = 20, $error_limit = 20)
    {
        $delimiter = $this->getImportDelimiterFromKey($stage['delimiter']);
        $counters = $simulate
            ? array(
                'would_insert' => 0,
                'would_update' => 0,
                'would_skip_existing' => 0,
                'would_skip_invalid' => 0,
            )
            : array(
                'inserted' => 0,
                'updated' => 0,
                'skipped_existing' => 0,
                'skipped_invalid' => 0,
            );

        $preview_rows = array();
        $invalid_rows = array();

        if ($delimiter === null || empty($stage['csv_path']) || !is_file($stage['csv_path'])) {
            $key = $simulate ? 'would_skip_invalid' : 'skipped_invalid';
            $counters[$key]++;
            $invalid_rows[] = array('line' => 1, 'reason' => lang('import_error_unreadable'));

            return array(
                'counters' => $counters,
                'preview_rows' => $preview_rows,
                'invalid_rows' => $invalid_rows,
            );
        }

        $handle = fopen($stage['csv_path'], 'r');
        if ($handle === false) {
            $key = $simulate ? 'would_skip_invalid' : 'skipped_invalid';
            $counters[$key]++;
            $invalid_rows[] = array('line' => 1, 'reason' => lang('import_error_unreadable'));

            return array(
                'counters' => $counters,
                'preview_rows' => $preview_rows,
                'invalid_rows' => $invalid_rows,
            );
        }

        fgetcsv($handle, 20000, $delimiter, '"', '\\');
        $line_number = 1;

        while (($row = fgetcsv($handle, 20000, $delimiter, '"', '\\')) !== false) {
            $line_number++;

            if (!is_array($row)) {
                continue;
            }

            $mapped_row = $this->buildMappedRow($row, $stage['mapping'], array(
                'fallback_method' => $stage['fallback_method'],
                'selected_method' => isset($stage['selected_method']) ? $stage['selected_method'] : '',
            ));

            if (!$mapped_row['is_valid']) {
                $skip_key = $simulate ? 'would_skip_invalid' : 'skipped_invalid';
                $counters[$skip_key]++;

                if (count($invalid_rows) < $error_limit) {
                    $invalid_rows[] = array(
                        'line' => $line_number,
                        'reason' => implode(' ', $mapped_row['errors']),
                    );
                }

                continue;
            }

            $action = $this->applyMappedRow(
                $mapped_row,
                array(
                    'site_id' => (int) $stage['site_id'],
                    'skip_existing' => !empty($stage['skip_existing']),
                    'simulate' => $simulate,
                ),
                $counters,
                $invalid_rows
            );

            if (count($preview_rows) < $preview_limit) {
                $preview_rows[] = array(
                    'line' => $line_number,
                    'action' => $action,
                    'original_url' => $mapped_row['data']['original_url'],
                    'new_url' => $mapped_row['data']['new_url'],
                    'detour_method' => $mapped_row['data']['detour_method'],
                    'start_date' => $mapped_row['data']['start_date'],
                    'end_date' => $mapped_row['data']['end_date'],
                );
            }
        }

        fclose($handle);

        return array(
            'counters' => $counters,
            'preview_rows' => $preview_rows,
            'invalid_rows' => $invalid_rows,
        );
    }

    private function validateImportMapping($mapping, $headers)
    {
        $mapping = is_array($mapping) ? $mapping : array();
        $field_labels = $this->getImportMappingFieldLabels();
        $required_fields = array('original_url', 'new_url');
        $normalized = array();
        $errors = array();

        foreach ($field_labels as $field_key => $field_label) {
            $raw_value = isset($mapping[$field_key]) ? trim((string) $mapping[$field_key]) : '';
            if ($raw_value === '') {
                if (in_array($field_key, $required_fields, true)) {
                    $errors[] = sprintf(lang('import_error_mapping_required_field'), $field_label);
                }
                $normalized[$field_key] = '';
                continue;
            }

            if (!ctype_digit($raw_value)) {
                $errors[] = sprintf(lang('import_error_mapping_invalid_field'), $field_label);
                $normalized[$field_key] = '';
                continue;
            }

            $index = (int) $raw_value;
            if (!array_key_exists($index, $headers)) {
                $errors[] = sprintf(lang('import_error_mapping_invalid_field'), $field_label);
                $normalized[$field_key] = '';
                continue;
            }

            $normalized[$field_key] = (string) $index;
        }

        if (
            isset($normalized['original_url'], $normalized['new_url'])
            && $normalized['original_url'] !== ''
            && $normalized['new_url'] !== ''
            && $normalized['original_url'] === $normalized['new_url']
        ) {
            $errors[] = lang('import_error_same_columns');
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'mapping' => $normalized,
        );
    }

    private function suggestImportMapping($headers)
    {
        $mapping = array(
            'original_url' => '',
            'new_url' => '',
            'start_date' => '',
            'end_date' => '',
        );

        $aliases = array(
            'original_url' => array('original_url', 'original', 'source', 'source_url', 'from', 'from_url', 'old_url', 'request_path', 'path'),
            'new_url' => array('new_url', 'new', 'destination', 'destination_url', 'redirect', 'redirect_to', 'target', 'target_url', 'to', 'to_url'),
            'start_date' => array('start_date', 'start', 'active_from', 'from_date'),
            'end_date' => array('end_date', 'end', 'active_until', 'to_date', 'expires'),
        );

        $used_indexes = array();
        foreach ($mapping as $field => $value) {
            foreach ($headers as $index => $header) {
                $normalized_header = $this->normalizeImportHeaderName($header);
                if ($normalized_header === '') {
                    continue;
                }

                if (in_array($normalized_header, $aliases[$field], true) && !in_array((string) $index, $used_indexes, true)) {
                    $mapping[$field] = (string) $index;
                    $used_indexes[] = (string) $index;
                    break;
                }
            }
        }

        if ($mapping['original_url'] === '' && array_key_exists(0, $headers)) {
            $mapping['original_url'] = '0';
        }

        if ($mapping['new_url'] === '') {
            foreach (array_keys($headers) as $index) {
                $string_index = (string) $index;
                if ($string_index !== $mapping['original_url']) {
                    $mapping['new_url'] = $string_index;
                    break;
                }
            }
        }

        return $mapping;
    }

    private function readImportSampleRows($stage, $limit = 5)
    {
        $rows = array();
        $delimiter = $this->getImportDelimiterFromKey($stage['delimiter']);
        if ($delimiter === null || empty($stage['csv_path']) || !is_file($stage['csv_path'])) {
            return $rows;
        }

        $handle = fopen($stage['csv_path'], 'r');
        if ($handle === false) {
            return $rows;
        }

        fgetcsv($handle, 20000, $delimiter, '"', '\\');

        while (count($rows) < $limit && ($row = fgetcsv($handle, 20000, $delimiter, '"', '\\')) !== false) {
            if (!is_array($row)) {
                continue;
            }

            $normalized_row = array();
            foreach ($stage['headers'] as $index => $header) {
                $normalized_row[(string) $index] = array_key_exists((string) $index, $row) ? trim((string) $row[$index]) : '';
            }

            $rows[] = $normalized_row;
        }

        fclose($handle);

        return $rows;
    }

    private function getImportStageDir()
    {
        $path = rtrim(PATH_CACHE, '/\\') . '/detour_pro_import/';

        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        return $path;
    }

    private function getImportDelimiterFromKey($delimiter_key)
    {
        $delimiter_map = array(
            'comma' => ',',
            'tab' => "\t",
            'semicolon' => ';',
        );

        return isset($delimiter_map[$delimiter_key]) ? $delimiter_map[$delimiter_key] : null;
    }

    private function getMappedCsvValue($csv_row, $mapping, $field)
    {
        if (!isset($mapping[$field]) || $mapping[$field] === '' || !ctype_digit((string) $mapping[$field])) {
            return '';
        }

        $index = (int) $mapping[$field];
        if (!array_key_exists($index, $csv_row)) {
            return '';
        }

        return trim((string) $csv_row[$index]);
    }

    private function normalizeImportHeaderName($header)
    {
        $header = strtolower(trim((string) $header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);

        return trim((string) $header, '_');
    }

    private function getImportFieldLabels()
    {
        return array(
            'original_url' => lang('label_original_url'),
            'new_url' => lang('label_new_url'),
            'detour_method' => lang('label_detour_method'),
            'start_date' => lang('label_start_date'),
            'end_date' => lang('label_end_date'),
        );
    }

    private function getImportMappingFieldLabels()
    {
        return array(
            'original_url' => lang('label_original_url'),
            'new_url' => lang('label_new_url'),
            'start_date' => lang('label_start_date'),
            'end_date' => lang('label_end_date'),
        );
    }

    public function dashboard()
    {
        ee()->lang->loadfile('detour_pro');

        $this->ensureExtensionEnabled();
        $this->skinSupport();
        $hit_counter_enabled = (isset($this->settings->hit_counter) && $this->settings->hit_counter == 'y');

        if (!defined('URL_THIRD_THEMES')) {
            define('URL_THIRD_THEMES', ee()->config->slash_item('theme_folder_url') . 'third_party/');
        }

        ee()->cp->add_to_foot('<script type="text/javascript" charset="utf-8" src="' . URL_THIRD_THEMES . 'detour_pro/js/chart.min.js?v' . $this->version . '"></script>');

        $default_range = 'month';
        $range_definitions = array(
            'day' => array(
                'days' => 1,
                'trend_type' => 'daily',
                'period_label' => lang('dashboard_period_today'),
                'not_found_title' => lang('dashboard_404s_by_day'),
                'redirect_301_title' => lang('dashboard_301s_by_day'),
                'comparison_title' => lang('dashboard_redirected_vs_404_day'),
            ),
            'week' => array(
                'days' => 7,
                'trend_type' => 'daily',
                'period_label' => lang('dashboard_period_last_7_days'),
                'not_found_title' => lang('dashboard_404s_by_day'),
                'redirect_301_title' => lang('dashboard_301s_by_day'),
                'comparison_title' => lang('dashboard_redirected_vs_404_week'),
            ),
            'month' => array(
                'days' => 30,
                'trend_type' => 'daily',
                'period_label' => lang('dashboard_period_last_30_days'),
                'not_found_title' => lang('dashboard_404s_by_day'),
                'redirect_301_title' => lang('dashboard_301s_by_day'),
                'comparison_title' => lang('dashboard_redirected_vs_404_month'),
            ),
            'year' => array(
                'days' => 365,
                'months' => 12,
                'trend_type' => 'monthly',
                'period_label' => lang('dashboard_period_last_12_months'),
                'not_found_title' => lang('dashboard_404s_by_month'),
                'redirect_301_title' => lang('dashboard_301s_by_month'),
                'comparison_title' => lang('dashboard_redirected_vs_404_year'),
            ),
        );

        $empty_chart = array('labels' => array(), 'datasets' => array());
        $range_views = array();
        $all_time_needed_redirects = $this->get_top_needed_redirects(null, 10);

        foreach ($range_definitions as $range_key => $range) {
            $days = (int) $range['days'];
            $top_needed_redirects = $this->get_top_needed_redirects($days, 10);
            $use_monthly_trends = ($range['trend_type'] === 'monthly');

            if ($use_monthly_trends) {
                $not_found_trend = $this->get_404_monthly_trend((int) $range['months']);
                $redirect_301_trend = $hit_counter_enabled
                    ? $this->get_301_monthly_trend((int) $range['months'])
                    : $empty_chart;
                $redirect_vs_404 = $hit_counter_enabled
                    ? $this->get_redirected_vs_404_monthly_comparison_data((int) $range['months'])
                    : $empty_chart;
            } else {
                $not_found_trend = $this->get_404_daily_trend($days);
                $redirect_301_trend = $hit_counter_enabled
                    ? $this->get_301_daily_trend($days)
                    : $empty_chart;
                $redirect_vs_404 = $hit_counter_enabled
                    ? $this->get_redirected_vs_404_comparison_data($days)
                    : $empty_chart;
            }

            $range_views[$range_key] = array(
                'not_found_title' => $range['not_found_title'],
                'not_found_trend' => $not_found_trend,
                'redirect_301_title' => $range['redirect_301_title'],
                'redirect_301_trend' => $redirect_301_trend,
                'comparison_title' => $range['comparison_title'],
                'redirect_vs_404' => $redirect_vs_404,
                'top_301_title' => $this->dashboard_title_with_period(
                    lang('dashboard_top_10_301_hits'),
                    $range['period_label']
                ),
                'top_301' => $hit_counter_enabled ? $this->get_top_301_hits($days, 10) : $empty_chart,
                'top_needed_title' => $this->dashboard_title_with_period(
                    lang('dashboard_top_10_needed_redirects'),
                    $range['period_label']
                ),
                'top_needed_redirects' => $this->format_top_needed_redirects_chart_data($top_needed_redirects),
            );
        }

        $chart_data = array(
            'default_range' => $default_range,
            'range_views' => $range_views,
        );

        $this->_data['ee_ver'] = substr(APP_VER, 0, 1);
        $this->_data['cp_heading'] = ee()->lang->line('nav_dashboard');
        $this->_data['add_detour_link'] = $this->flux->moduleURL('addUpdate');
        $this->_data['enable_hit_counter_url'] = $this->_form_url('enable_hit_counter');
        $this->_data['hit_counter_enabled'] = $hit_counter_enabled;
        $this->_data['top_needed_redirects'] = $all_time_needed_redirects;
        $this->_data['needed_table_title'] = lang('dashboard_needed_redirects_table_title_all_time');
        $this->_data['chart_data'] = json_encode($chart_data);

        return $this->flux->view('dashboard', $this->_data, true);
    }

    public function enable_hit_counter()
    {
        ee()->lang->loadfile('detour_pro');

        $site_id = ee()->config->item('site_id');

        ee()->db->where('site_id', $site_id);
        $exists = ee()->db->count_all_results('detour_pro_settings');

        if ($exists) {
            ee()->db->where('site_id', $site_id);
            ee()->db->update('detour_pro_settings', array('hit_counter' => 'y'));
        } else {
            ee()->db->insert('detour_pro_settings', array(
                'site_id' => $site_id,
                'url_detect' => 'ee',
                'default_method' => '301',
                'hit_counter' => 'y',
                'allow_trailing_slash' => 0,
                'allow_qs' => 0,
            ));
        }

        $this->settings = $this->flux->getSettings();
        $this->flux->flashData('message_success', ee()->lang->line('dashboard_redirect_reporting_enabled'));
        ee()->functions->redirect($this->flux->moduleURL('dashboard'));
        exit;
    }

    /**
     * ExpressionEngine suggested method for catching what would normally be a GET
     * request. Catch the POST, convert it into a query string value, and redirect.
     */
    public function search_post()
    {
        // Convert the search keywords into a query string value.
        $searchVal = urlencode((string) ee()->input->post('search'));
        $orig_method = !empty(ee()->input->get('orig_search')) ? ee()->input->get('orig_search') : 'index';
        ee()->functions->redirect($this->flux->moduleURL($orig_method, array('search' => $searchVal)));
    }

    public function delete_detours()
    {
        // Handle bulk action or direct delete
        $bulk_action = ee()->input->post('bulk_action');
        $detour_delete = ee()->input->post('detour_delete');

        // If bulk action is delete, or if detour_delete is set (legacy support)
        if (($bulk_action == 'delete' || !empty($detour_delete)) && !empty($detour_delete)) {
            foreach ($detour_delete as $detour_id) {
                ee()->db->delete('detours', array('detour_id' => $detour_id));
                ee()->db->where('detour_id', $detour_id);
                ee()->db->delete('detours_hits');
            }
        }

        // Redirect back to Detour Pro landing page
        ee()->functions->redirect($this->flux->moduleURL('detours'));
    }

    //! Advanced Add Detour View and Save
    public function addUpdate()
    {
        ee()->lang->loadfile('detour_pro');

        if (substr(APP_VER, 0, 1) > 2) {
            ee()->lang->loadfile('calendar');

            ee()->javascript->set_global('date.date_format', ee()->config->item('date_format'));
            ee()->javascript->set_global('lang.date.months.full', array(
                lang('cal_january'),
                lang('cal_february'),
                lang('cal_march'),
                lang('cal_april'),
                lang('cal_may'),
                lang('cal_june'),
                lang('cal_july'),
                lang('cal_august'),
                lang('cal_september'),
                lang('cal_october'),
                lang('cal_november'),
                lang('cal_december'),
            ));
            ee()->javascript->set_global('lang.date.months.abbreviated', array(
                lang('cal_jan'),
                lang('cal_feb'),
                lang('cal_mar'),
                lang('cal_apr'),
                lang('cal_may'),
                lang('cal_june'),
                lang('cal_july'),
                lang('cal_aug'),
                lang('cal_sep'),
                lang('cal_oct'),
                lang('cal_nov'),
                lang('cal_dec'),
            ));
            ee()->javascript->set_global('lang.date.days', array(
                lang('cal_su'),
                lang('cal_mo'),
                lang('cal_tu'),
                lang('cal_we'),
                lang('cal_th'),
                lang('cal_fr'),
                lang('cal_sa'),
            ));
            ee()->javascript->set_global('lang.date.today', lang('cal_today'));
            ee()->cp->add_js_script(array(
                'file' => array('cp/date_picker'),
            ));
        }

        ee()->cp->add_js_script(array('ui' => array('core', 'datepicker')));
        ee()->javascript->output(array('$( ".datepicker" ).datepicker();'));

        $this->_data['id'] = ee()->input->get_post('id') ? ee()->input->get_post('id') : null;
        $hit_counter_enabled = (isset($this->settings->hit_counter) && $this->settings->hit_counter == 'y');
        $this->_data['hit_counter_enabled'] = $hit_counter_enabled;
        $this->_data['enable_hit_counter_url'] = $this->_form_url('enable_hit_counter');
        $this->_data['detour_hits_chart_data'] = json_encode(array(
            'default_range' => 'month',
            'range_views' => array(),
        ));

        if (ee()->input->get_post('id')) {
            $detour = $this->get_detours(ee()->input->get_post('id'));
            ee()->db->select('COUNT(*) as total');
            $hits = ee()->db->get_where('detours_hits', array('detour_id' => $detour['detour_id']))->result_array();

            if (!empty($detour['detour_id']) && $hit_counter_enabled) {
                $detour_id = (int) $detour['detour_id'];
                $default_range = 'month';
                $range_definitions = array(
                    'day' => array(
                        'trend_type' => 'daily',
                        'days' => 1,
                        'period_label' => lang('dashboard_period_today'),
                    ),
                    'week' => array(
                        'trend_type' => 'daily',
                        'days' => 7,
                        'period_label' => lang('dashboard_period_last_7_days'),
                    ),
                    'month' => array(
                        'trend_type' => 'daily',
                        'days' => 30,
                        'period_label' => lang('dashboard_period_last_30_days'),
                    ),
                    'year' => array(
                        'trend_type' => 'monthly',
                        'months' => 12,
                        'period_label' => lang('dashboard_period_last_12_months'),
                    ),
                    'all' => array(
                        'trend_type' => 'monthly',
                        'months' => null,
                        'period_label' => lang('detour_hits_toggle_all'),
                    ),
                );
                $range_views = array();

                foreach ($range_definitions as $range_key => $range) {
                    if ($range['trend_type'] === 'monthly') {
                        $series = $this->analyticsService()->get_detour_monthly_hit_series($detour_id, $range['months']);
                    } else {
                        $series = $this->analyticsService()->get_detour_daily_hit_series($detour_id, (int) $range['days']);
                    }

                    $title = ($range_key === 'all')
                        ? lang('detour_hits_all_time')
                        : $this->dashboard_title_with_period(
                            lang('detour_hits_title_base'),
                            $range['period_label']
                        );

                    $range_views[$range_key] = array(
                        'title' => $title,
                        'chart' => $this->chartDataBuilder()->build_301_trend_chart($title, $series),
                    );
                }

                $this->_data['detour_hits_chart_data'] = json_encode(array(
                    'default_range' => $default_range,
                    'range_views' => $range_views,
                ));
            }
        }

        $phpDateFormat = str_replace('%', '', ee()->config->item('date_format'));

        $this->_data['ee_ver']               = substr(APP_VER, 0, 1);
        $this->_data['original_url']         = (!empty($detour['original_url'])) ? $detour['original_url'] : (!empty(ee()->input->get('url')) ? urldecode((string) ee()->input->get('url')) : '');
        $this->_data['new_url']              = (!empty($detour['new_url'])) ? $detour['new_url'] : '';
        $this->_data['note']                 = (!empty($detour['note'])) ? $detour['note'] : '';
        $this->_data['detour_method']        = (!empty($detour['detour_method'])) ? $detour['detour_method'] : (!empty($this->settings->default_method) ? $this->settings->default_method : '');
        $this->_data['detour_hits']          = (!empty($hits[0]['total'])) ? $hits[0]['total'] : '';
        $this->_data['start_date']           = (!empty($detour['start_date'])) ? date($phpDateFormat, strtotime((string) $detour['start_date'])) : '';
        $this->_data['end_date']             = (!empty($detour['end_date'])) ? date($phpDateFormat, strtotime((string) $detour['end_date'])) : '';
        $this->_data['detour_methods']       = $this->_detour_methods;
        $this->_data['allow_trailing_slash'] = (!empty($this->settings->allow_trailing_slash) ? $this->settings->allow_trailing_slash : false);

        $this->_data['action_url'] = $this->_form_url('saveDetour');
        $this->_data['check_url'] = $this->flux->moduleUrl('checkDetour');

        $this->skinSupport();

        if (!defined('URL_THIRD_THEMES')) {
            define('URL_THIRD_THEMES', ee()->config->slash_item('theme_folder_url') . 'third_party/');
        }

        ee()->cp->add_to_foot('<script type="text/javascript" charset="utf-8" src="' . URL_THIRD_THEMES . 'detour_pro/js/chart.min.js?v' . $this->version . '"></script>');
        ee()->cp->add_to_foot('<script type="text/javascript" charset="utf-8" src="' . URL_THIRD_THEMES . 'detour_pro/js/detour_pro.js?v' . $this->version . '"></script>');

        return $this->flux->view('addUpdate', $this->_data, true);
    }

    /**
     * Check if the URL they are entering exists as a file or is a duplicate.
     * @return json JSON string with status and error messages (if any)
     */
    public function checkDetour()
    {
        $original_url = ee()->input->post('original_url');
        $original_url_check = rtrim((string) $original_url, '/');

        $site_path = ee()->config->item('base_path');
        $site_path = rtrim((string) $site_path, '/');

        $file_path = $site_path . '/' . $original_url_check;

        // Check if the url exists as a real file.
        if (file_exists($file_path)) {
            die(json_encode(array('status' => 'error', 'message' => '<b>Real File Exists</b><br />Detour Pro can only redirect URLs where real files do not exist.')));
        } else {
            $existing_url = ee()->input->post('existing_url');

			// If there is no existing detour (i.e. new detour) or the entered detour doesn't
			// match the existing detour, make sure the entered detour doesn't already exist.
			if (empty($existing_url) || $existing_url != $original_url) {
				ee()->db->start_cache();
		    	ee()->db->where('site_id', ee()->config->item('site_id'));
		    	ee()->db->where('original_url', $original_url);
		    	ee()->db->stop_cache();

				$exists = ee()->db->count_all_results('detours');

		    	if ($exists) {
		    		$existing_items = ee()->db->get('detours');
		        	ee()->db->flush_cache();
		        	$existing_item = current($existing_items->result_array());

		        	die(json_encode(array('status'=>'error', 'message'=>'This detour already exists (<a href="'.$this->_form_url('addUpdate', array('id' => $existing_item['detour_id'])).'">edit</a>).')));
		    	}
			}	
		}	

        die(json_encode(array('status' => 'success', 'message' => 'So far so good!')));
    }

    public function saveDetour()
    {
        $posted_id = ee()->input->post('id');
        $is_edit = !empty($posted_id);
        $detour_id = null;

        if ($is_edit && !ctype_digit((string) $posted_id)) {
            ee()->functions->redirect($this->flux->moduleURL('detours'));
            exit;
        }

        if ($is_edit) {
            $posted_id = (int) $posted_id;
        }

        $posted_new_url = isset($_POST['new_url']) ? trim((string) $_POST['new_url']) : '';
        $note = $this->normalizeDetourNote(isset($_POST['note']) ? $_POST['note'] : '');

        // If the setting to allow trailing slashes is on, just trim whitespace.
        if (!empty($this->settings->allow_trailing_slash) && $this->settings->allow_trailing_slash == 1) {
            $original_url = trim((string) $_POST['original_url']);
            $new_url      = $posted_new_url;
        } else {
            $original_url = trim((string) $_POST['original_url'], '/');
            $new_url      = ($posted_new_url === '/') ? '/' : trim($posted_new_url, '/');
        }

        $existing_url = ee()->input->post('existing_url');

        // If there is no existing detour (i.e. new detour) or the entered detour doesn't
        // match the existing detour, make sure the entered detour doesn't already exist.
        if (empty($existing_url) || $existing_url != $original_url) {
            ee()->db->where('site_id', ee()->config->item('site_id'));
            ee()->db->where('original_url', $original_url);
            $exists = ee()->db->count_all_results('detours');

            if ($exists) {
                $this->flux->flashData('message_error', ee()->lang->line('detour_already_exists'));
                $redirect_params = $is_edit ? array('id' => $posted_id) : array();
                ee()->functions->redirect($this->flux->moduleURL('addUpdate', $redirect_params));
                exit;
            }
        }

        $phpDateFormat = str_replace('%', '', ee()->config->item('date_format'));

        if ($phpDateFormat == "j/n/Y") {
            /*
            "Note: Be aware of dates in the m/d/y or d-m-y formats; if the separator is a slash (/), then the American m/d/y is assumed. 
            If the separator is a dash (-) or a dot (.), then the European d-m-y format is assumed. 
            To avoid potential errors, you should YYYY-MM-DD dates or date_create_from_format() when possible." 
            -- https://www.w3schools.com/php/func_date_strtotime.asp

                We make use of PHP's goofball rule and just temperarily swap out the / for -
                This is if the setting is such that it is in the format: dd/mm/YYYY we make a temp dd-mm-YYYY so it saves in the correct European format. 
                otherwise it was swapping d and m and sending us back to 1970 since the date errors out if you put in a day greater than 12 
                since it got swapped, did not find a 13th month for example and died.

                if the core adds MM-DD-YYYY in the future that could get weird but this is fine for now and the if conditional should catch that regardless.  
                Doing it this way also lets us save it in the same format which we want, otherwise someone could have a lot of issues if they swap formats around
            */
            $modified_start = str_replace("/", "-", $_POST['start_date']);
            $modified_end = str_replace("/", "-", $_POST['end_date']);

            $start_date = (isset($modified_start) && !empty($modified_start) && !array_key_exists('clear_start_date', $_POST)) ? date('Y-m-d', strtotime($modified_start)) : null;
            $end_date   = (isset($modified_end) && !empty($modified_end) && !array_key_exists('clear_end_date', $_POST)) ? date('Y-m-d', strtotime($modified_end)) : null;

        }       
        else {
            $start_date = (isset($_POST['start_date']) && !empty($_POST['start_date']) && !array_key_exists('clear_start_date', $_POST)) ? date('Y-m-d', strtotime((string) $_POST['start_date'])) : null;
            $end_date   = (isset($_POST['end_date']) && !empty($_POST['end_date']) && !array_key_exists('clear_end_date', $_POST)) ? date('Y-m-d', strtotime((string) $_POST['end_date'])) : null;
        }

        $data = array(
            'original_url'  => $original_url,
            'new_url'       => $new_url,
            'note'          => $note,
            'detour_method' => isset($_POST['detour_method']) ? $_POST['detour_method'] : '301',
            'site_id'       => ee()->config->item('site_id'),
            'start_date'    => $start_date,
            'end_date'      => $end_date,
        );

        if (isset($_POST['original_url']) && !empty($_POST['original_url'])) {
            if (!array_key_exists('id', $_POST)) {
                ee()->db->insert('detours', $data);
                $detour_id = ee()->db->insert_id();
            } elseif ($is_edit) {
                ee()->db->where('detour_id', $posted_id);
                ee()->db->update('detours', $data);
                $detour_id = $posted_id;
            }

            //update record for not founds
            ee()->db->where('original_url', $original_url);
            ee()->db->where('site_id', ee()->config->item('site_id'));
            ee()->db->where('detour_id IS NULL', null, false);
            ee()->db->update('detours_not_found', array('detour_id' => $detour_id));

        }

        if ($is_edit && !empty($detour_id)) {
            $this->flux->flashData('message_success', ee()->lang->line('detour_saved'));
            ee()->functions->redirect($this->flux->moduleURL('addUpdate', array('id' => $detour_id)));
            return;
        }

        // Redirect back to Detour Pro landing page
        ee()->functions->redirect($this->flux->moduleURL('detours'));
    }

    private function normalizeDetourNote($raw_note)
    {
        $note = trim((string) $raw_note);

        if ($note === '') {
            return null;
        }

        $max_characters = 255;

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($note, 'UTF-8') > $max_characters) {
                $note = mb_substr($note, 0, $max_characters, 'UTF-8');
            }

            return $note;
        }

        if (preg_match('//u', $note) === 1) {
            $chars = preg_split('//u', $note, -1, PREG_SPLIT_NO_EMPTY);

            if ($chars !== false && count($chars) > $max_characters) {
                $note = implode('', array_slice($chars, 0, $max_characters));
            }

            return $note;
        }

        if (strlen($note) > $max_characters) {
            $note = substr($note, 0, $max_characters);
        }

        return $note;
    }

    /**
     * URLs that are not found (404)
     *
     * @return  void
     */
    public function missing_pages()
    {
        // Load language file
        ee()->lang->loadfile('detour_pro');

        if (!defined('URL_THIRD_THEMES')) {
            define('URL_THIRD_THEMES', ee()->config->slash_item('theme_folder_url') . 'third_party/');
        }

        ee()->cp->add_to_foot('<script type="text/javascript" charset="utf-8" src="' . URL_THIRD_THEMES . 'detour_pro/js/chart.min.js?v' . $this->version . '"></script>');

        // Get search query from request
        if (!empty(ee()->input->get('search'))) {
            $this->search = urldecode((string) ee()->input->get('search'));
        }

        // Create table using CP/Table service with fromGlobals for automatic parameter handling
        $base_url = ee('CP/URL')->make('addons/settings/detour_pro/missing_pages');

        $table = ee('CP/Table', array(
            'sort_col_qs_var' => 'sort_col',
            'sort_dir_qs_var' => 'sort_dir',
            'limit' => 100,
            'search' => $this->search
        ));

        // Map column labels to database field names for sorting
        $sort_map = array(
            'title_url' => 'original_url',
            'title_hits' => 'total_hits',
            'title_hit_date' => 'last_hit_date',
            'title_detour' => 'detour_status'
        );

        // Define columns
        $columns = array(
            'title_url' => array(
                'sort' => true
            ),
            'title_hits' => array(
                'sort' => true
            ),
            'title_hit_date' => array(
                'sort' => true
            ),
            'title_detour' => array(
                'sort' => true,
                'encode' => false
            )
        );

        $table->setColumns($columns);

        // Set no results text
        $table->setNoResultsText(
            ee()->lang->line('dir_no_404s'),
            '',
            ''
        );

        // Get sort column and direction from table
        $sort_col_label = $table->sort_col;
        $sort_dir = $table->sort_dir;

        // Default Missing Page Tracker sort to Hits descending.
        if (!ee()->input->get('sort_col')) {
            $sort_col_label = 'title_hits';
            $sort_dir = 'desc';
        }

        // Map label to database field
        $this->sort = isset($sort_map[$sort_col_label]) ? $sort_map[$sort_col_label] : 'original_url';
        $this->sort_dir = $sort_dir;

        // Use resolved CP/Table paging values from globals (native EE pattern).
        $per_page = isset($table->config['limit']) ? (int) $table->config['limit'] : 100;
        if ($per_page < 1) {
            $per_page = 100;
        }
        $page = isset($table->config['page']) ? (int) $table->config['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $per_page;

        // Get total count for pagination + current page rows.
        $total_not_found = $this->count_not_found();
        $current_rows = $this->get_not_found(null, $offset, $per_page);

        // Format data for table
        $data = array();
        foreach ($current_rows as $row) {
            $detour_link = !empty($row['detour_id']) 
                ? '<a href="' . $row['edit_detour_link'] . '">' . ee()->lang->line('label_edit_detour') . '</a>'
                : '<a href="' . $row['add_detour_link'] . '">' . ee()->lang->line('label_add_detour') . '</a>';

            $data[] = array(
                'title_url' => $row['original_url'],
                'title_hits' => $row['hits'],
                'title_hit_date' => $row['hit_date'],
                'title_detour' => $detour_link
            );
        }

        $table->setData($data);

        // Render table and pagination from resolved base URL.
        $this->_data['table'] = $table->viewData($base_url);
        $pagination_base_url = $this->_data['table']['base_url'];
        $this->_data['pagination'] = ee('CP/Pagination', $total_not_found)
            ->perPage($per_page)
            ->currentPage($page)
            ->render($pagination_base_url);

        // If we're not on page 1 and there are no results, redirect to page 1.
        if ($page > 1 && count($current_rows) == 0) {
            ee()->functions->redirect($pagination_base_url);
        }

        $this->_data['ee_ver'] = substr(APP_VER, 0, 1);
        $this->_data['search_query'] = $this->search;
        $this->_data['base_url'] = $base_url;
        $this->_data['search_url'] = $this->_form_url('search_post', array('orig_search' => 'missing_pages'));
        $this->_data['cp_heading'] = ee()->lang->line('nav_missing_page_tracker');

        $default_range = 'month';
        $range_definitions = array(
            'day' => array(
                'days' => 1,
                'trend_type' => 'daily',
                'period_label' => lang('dashboard_period_today'),
                'not_found_title' => lang('dashboard_404s_by_day'),
            ),
            'week' => array(
                'days' => 7,
                'trend_type' => 'daily',
                'period_label' => lang('dashboard_period_last_7_days'),
                'not_found_title' => lang('dashboard_404s_by_day'),
            ),
            'month' => array(
                'days' => 30,
                'trend_type' => 'daily',
                'period_label' => lang('dashboard_period_last_30_days'),
                'not_found_title' => lang('dashboard_404s_by_day'),
            ),
            'year' => array(
                'days' => 365,
                'months' => 12,
                'trend_type' => 'monthly',
                'period_label' => lang('dashboard_period_last_12_months'),
                'not_found_title' => lang('dashboard_404s_by_month'),
            ),
        );

        $missing_page_chart_ranges = array();
        foreach ($range_definitions as $range_key => $range) {
            $days = (int) $range['days'];
            $top_needed_redirects = $this->get_top_needed_redirects($days, 10);
            $not_found_chart = null;

            if ($range['trend_type'] === 'monthly') {
                $not_found_chart = $this->get_404_monthly_trend((int) $range['months']);
            } else {
                $not_found_chart = $this->get_404_daily_trend($days);
            }

            $missing_page_chart_ranges[$range_key] = array(
                'not_found_daily' => $not_found_chart,
                'not_found_title' => $range['not_found_title'],
                'top_needed_title' => $this->dashboard_title_with_period(
                    lang('dashboard_top_10_needed_redirects'),
                    $range['period_label']
                ),
                'top_needed_redirects' => $this->format_top_needed_redirects_chart_data($top_needed_redirects),
            );
        }

        $this->_data['missing_page_chart_data'] = json_encode(array(
            'ranges' => $missing_page_chart_ranges,
            'default_range' => $default_range,
        ));

        $this->skinSupport();
        return $this->flux->view('missing_pages', $this->_data, true);
    }

    /**
     * License page - override parent to add CSS support
     *
     * @return  void
     */
    public function license()
    {
        $this->skinSupport();
        return parent::license();
    }

    public function purge_hits()
    {
        $this->_data['ee_ver']            = substr(APP_VER, 0, 1);
        $this->_data['total_detour_hits'] = ee()->db->count_all_results('detours_hits');
        $this->_data['action_url']        = $this->_form_url('do_purge_hits');

        $this->skinSupport();
        return $this->flux->view('purge_hits', $this->_data, true);
    }

    public function do_purge_hits()
    {
        ee()->lang->loadfile('detour_pro');

        $purged_rows = (int) ee()->db->count_all_results('detours_hits');
        ee()->db->empty_table('detours_hits');
        $this->flux->flashData(
            'message_success',
            sprintf(ee()->lang->line('purge_hits_success'), $purged_rows)
        );
        ee()->functions->redirect($this->flux->moduleURL('purge_hits'));
    }

    // Settings View and Save

    public function settings()
    {
        $this->_data['ee_ver']     = substr(APP_VER, 0, 1);
        $this->_data['action_url'] = $this->_form_url('save_settings');
        $this->_data['settings']   = $this->settings;

        if (!isset($this->_data['settings']->url_detect)) {
            $this->_data['settings']->url_detect = '';
        }
        if (!isset($this->_data['settings']->default_method)) {
            $this->_data['settings']->default_method = '';
        }
        if (!isset($this->_data['settings']->hit_counter)) {
            $this->_data['settings']->hit_counter = '';
        }
        if (!isset($this->_data['settings']->allow_trailing_slash)) {
            $this->_data['settings']->allow_trailing_slash = '';
        }
        if (!isset($this->_data['settings']->allow_qs)) {
            $this->_data['settings']->allow_qs = '';
        }

        ee()->javascript->output(array("$('input[name=allow_trailing_slash]').on('click', function() { if($(this).is(':checked')) { $('select[name=url_detect]').val('php'); } });"));
        ee()->javascript->output(array("$('select[name=url_detect]').on('change', function() { if($(this).val() == 'ee') { $('input[name=allow_trailing_slash]').attr('checked', false); } });"));

        $this->skinSupport();
        return $this->flux->view('settings', $this->_data, true);
    }

    public function save_settings()
    {
        $data = array();

        $data['site_id']              = ee()->config->item('site_id');
        $data['url_detect']           = ee()->input->post('url_detect', true);
        $data['default_method']       = ee()->input->post('default_method', true);
        $data['hit_counter']          = ee()->input->post('hit_counter', true);
        $data['allow_trailing_slash'] = ee()->input->post('allow_trailing_slash', true);
        $data['allow_qs'] = ee()->input->post('allow_qs', true);

        // Find out if the settings exist, if not, insert them.
        ee()->db->where('site_id', ee()->config->item('site_id'));
        $exists = ee()->db->count_all_results('detour_pro_settings');

        if ($exists) {
            ee()->db->where('site_id', ee()->config->item('site_id'));
            ee()->db->update('detour_pro_settings', $data);
        } else {
            ee()->db->insert('detour_pro_settings', $data);
        }

        // ----------------------------------
        //  Redirect to Settings page with Message
        // ----------------------------------
        $this->flux->flashData('message_success', ee()->lang->line('settings_updated'));
        ee()->functions->redirect($this->flux->moduleURL('settings'));
        exit;
    }

    public function stuff_detours()
    {
        for ($i = 1; $i < 100; $i++) {
            $data = array(
                'original_url'  => 'start' . $i,
                'new_url'       => 'redirect' . $i,
                'detour_method' => 301,
                'site_id'       => 1,
            );

            ee()->db->insert('detours', $data);
        }
    }

    private function count_detours($id = '')
    {
        ee()->db->where('site_id', ee()->config->item('site_id'));

        if ($id) {
            ee()->db->where('detour_id', $id);
        }

        if (!$id) {
            $this->applyDetourSearchFilter('original_url', 'new_url');
        }

        return ee()->db->count_all_results('detours');
    }

    private function applyDetourSearchFilter($original_field, $new_field)
    {
        if ($this->search === '') {
            return;
        }

        ee()->db->start_like_group();
        ee()->db->like($original_field, $this->search);
        ee()->db->or_like($new_field, $this->search);
        ee()->db->end_like_group();
    }

    private function get_detours($id = '', $start = 0, $per_page = 0, $displayHits = false)
    {
        $results = array();
        $vars = array(
            'site_id' => ee()->config->item('site_id'),
        );

        if ($id) {
            $vars['detour_id'] = $id;
        }

        if (!array_key_exists('detour_id', $vars)) {
            $phpDateFormat =  ee()->config->item('date_format');

            $phpDateFormat = str_replace('j', 'd', $phpDateFormat);
            $phpDateFormat = str_replace('n', 'm', $phpDateFormat);

            $phpDateFormat = "'" . $phpDateFormat . "'";
            ee()->db->select('detours.detour_id');
            ee()->db->select('detours.original_url');
            ee()->db->select('detours.new_url');
            ee()->db->select('detours.note');
            ee()->db->select('detours.detour_method');
            ee()->db->select('detours.start_date AS sort_start_date', false);
            ee()->db->select('detours.end_date AS sort_end_date', false);
            ee()->db->select('DATE_FORMAT(start_date,' . $phpDateFormat .') AS start_date_display', false);
            ee()->db->select('DATE_FORMAT(end_date,' . $phpDateFormat .') AS end_date_display', false);

            ee()->db->from('detours');
            ee()->db->where('detours.site_id', $vars['site_id']);

            if ($displayHits) {
                ee()->db->select('COUNT(dh.detour_id) AS sort_hits', false);
                ee()->db->join('detours_hits dh', 'dh.detour_id = detours.detour_id', 'left');
            }

            $this->applyDetourSearchFilter('detours.original_url', 'detours.new_url');

            if ($displayHits) {
                ee()->db->group_by(array(
                    'detours.detour_id',
                    'detours.original_url',
                    'detours.new_url',
                    'detours.note',
                    'detours.detour_method',
                    'detours.start_date',
                    'detours.end_date',
                ));
            }

            if ($this->sort) {
                ee()->db->order_by($this->sort, $this->sort_dir);
            }

            if ($start > 0 || $per_page > 0) {
                ee()->db->limit($per_page, $start);
            }
            $current_detours = ee()->db->get()->result_array();

            foreach ($current_detours as $value) {
                $hits = ($displayHits && isset($value['sort_hits'])) ? (int) $value['sort_hits'] : 0;
                $start_date_display = isset($value['start_date_display']) ? $value['start_date_display'] : (isset($value['start_date']) ? $value['start_date'] : '');
                $end_date_display = isset($value['end_date_display']) ? $value['end_date_display'] : (isset($value['end_date']) ? $value['end_date'] : '');

                $results[] = array(
                    'original_url'  => $value['original_url'],
                    'new_url'       => $value['new_url'],
                    'note'          => isset($value['note']) ? $value['note'] : null,
                    'start_date'    => $start_date_display,
                    'end_date'      => $end_date_display,
                    'detour_id'     => $value['detour_id'],
                    'detour_method' => $value['detour_method'],
                    'hits'          => $hits,
                    'update_link' => $this->flux->moduleURL('addUpdate', array('id' => $value['detour_id'])),
                );
            }
        } else {
            if ($start > 0 || $per_page > 0) {
                ee()->db->limit($per_page, $start);
            }

            $results = ee()->db->get_where('detours', $vars)->row_array();
        }

        return $results;
    }

    private function count_not_found($id = '')
    {
        $vars = array(
            'site_id' => ee()->config->item('site_id'),
        );

        if ($id) {
            $vars['notfound_id'] = $id;
        }

        if (!array_key_exists('notfound_id', $vars)) {
            ee()->db->select('COUNT(DISTINCT original_url) AS total', false);
            ee()->db->from('detours_not_found');
            ee()->db->where('site_id', $vars['site_id']);

            if ($this->search) {
                ee()->db->like('original_url', $this->search);
            }

            $query = ee()->db->get();
            $detour_count = (int) $query->row('total');
        } else {
            ee()->db->where('site_id', $vars['site_id']);
            $detour_count = ee()->db->count_all_results('detours_not_found');
        }

        return $detour_count;
    }

    private function get_not_found($id = '', $start = 0, $per_page = 0)
    {
        $results = array();
        $vars = array(
            'site_id' => ee()->config->item('site_id'),
        );

        if ($id) {
            $vars['notfound_id'] = $id;
        }

        if (!array_key_exists('notfound_id', $vars)) {
            ee()->db->select('original_url');
            ee()->db->select('SUM(hits) AS total_hits', false);
            ee()->db->select('MAX(hit_date) AS last_hit_date', false);
            ee()->db->select('MAX(detour_id) AS detour_status', false);

            ee()->db->where('site_id', $vars['site_id']);
            if ($this->search) {
                ee()->db->like('original_url', $this->search);
            }

            ee()->db->group_by('original_url');

            if ($this->sort) {
                ee()->db->order_by($this->sort, $this->sort_dir);
            } else {
                ee()->db->order_by('total_hits', 'desc');
            }

            if ($start > 0 || $per_page > 0) {
                ee()->db->limit($per_page, $start);
            }
            $current_not_found = ee()->db->get_where('detours_not_found', $vars)->result_array();

            foreach ($current_not_found as $value) {
                $detour_id = !empty($value['detour_status']) ? (int) $value['detour_status'] : null;

                $results[] = array(
                    'original_url' => $value['original_url'],
                    'hits' => (int) $value['total_hits'],
                    'hit_date' => !empty($value['last_hit_date']) ? date('m/d/Y', strtotime((string) $value['last_hit_date'])) : '',
                    'detour_id' => $detour_id,
                    'edit_detour_link' => $this->flux->moduleURL('addUpdate', array('id' => $detour_id)),
                    'add_detour_link' => $this->flux->moduleURL('addUpdate', array('url' => urlencode((string) $value['original_url']))),
                );
            }
        } else {
            if ($start > 0 || $per_page > 0) {
                ee()->db->limit($per_page, $start);
            }

            $results = ee()->db->get_where('detours_not_found', $vars)->row_array();
        }

        return $results;
    }

    private function get_404_weekly_trend($weeks = 12)
    {
        $series = $this->analyticsService()->get_404_weekly_series($weeks);

        return $this->chartDataBuilder()->build_404_trend_chart(
            lang('dashboard_404s_by_week'),
            $series
        );
    }

    private function get_404_daily_trend($days = 30)
    {
        $series = $this->analyticsService()->get_404_daily_series($days);

        return $this->chartDataBuilder()->build_404_trend_chart(
            lang('dashboard_404s_by_day'),
            $series
        );
    }

    private function get_404_monthly_trend($months = 12)
    {
        $series = $this->analyticsService()->get_404_monthly_series($months);

        return $this->chartDataBuilder()->build_404_trend_chart(
            lang('dashboard_404s_by_month'),
            $series
        );
    }

    private function get_301_daily_trend($days = 30)
    {
        $series = $this->analyticsService()->get_301_daily_series($days);

        return $this->chartDataBuilder()->build_301_trend_chart(
            lang('dashboard_301s_by_day'),
            $series
        );
    }

    private function get_301_monthly_trend($months = 12)
    {
        $series = $this->analyticsService()->get_301_monthly_series($months);

        return $this->chartDataBuilder()->build_301_trend_chart(
            lang('dashboard_301s_by_month'),
            $series
        );
    }

    private function get_top_301_hits($days = 30, $limit = 10)
    {
        $rows = $this->analyticsService()->get_top_301_hit_rows($days, $limit);
        $chart_rows = array();

        foreach ($rows as $row) {
            $detour_id = isset($row['detour_id']) ? (int) $row['detour_id'] : 0;
            $chart_rows[] = array(
                'label' => isset($row['label']) ? $row['label'] : '',
                'hits' => isset($row['hits']) ? (int) $row['hits'] : 0,
                'link' => $detour_id > 0 ? $this->flux->moduleURL('addUpdate', array('id' => $detour_id)) : '',
            );
        }

        return $this->chartDataBuilder()->build_top_301_chart(
            lang('dashboard_top_10_301_hits'),
            $chart_rows
        );
    }

    private function get_top_needed_redirects($days = null, $limit = 10)
    {
        $results = array();
        $rows = $this->analyticsService()->get_top_needed_redirect_rows($days, $limit);
        foreach ($rows as $row) {
            $original_url = $row['label'];
            $results[] = array(
                'label' => $original_url,
                'hits' => (int) $row['hits'],
                'add_detour_link' => $this->flux->moduleURL('addUpdate', array('url' => urlencode((string) $original_url))),
            );
        }

        return $results;
    }

    private function format_top_needed_redirects_chart_data($top_needed_redirects)
    {
        return $this->chartDataBuilder()->build_top_needed_redirects_chart(
            lang('dashboard_top_10_needed_redirects'),
            $top_needed_redirects
        );
    }

    private function get_redirected_vs_404_comparison_data($days = 1, $redirect_first = false)
    {
        $series = $this->analyticsService()->get_redirected_vs_404_series($days);
        if ($days === 1) {
            $series['labels'] = array(lang('dashboard_period_today'));
        }

        return $this->chartDataBuilder()->build_redirect_vs_404_chart(
            $series,
            lang('dashboard_not_found_label'),
            lang('dashboard_redirected_label'),
            $redirect_first
        );
    }

    private function get_redirected_vs_404_monthly_comparison_data($months = 12, $redirect_first = false)
    {
        $series = $this->analyticsService()->get_redirected_vs_404_monthly_series($months);

        return $this->chartDataBuilder()->build_redirect_vs_404_chart(
            $series,
            lang('dashboard_not_found_label'),
            lang('dashboard_redirected_label'),
            $redirect_first
        );
    }

    private function dashboard_title_with_period($title, $period_label)
    {
        return trim((string) $title);
    }

    private function get_dashboard_palette()
    {
        static $palette = null;
        if ($palette !== null) {
            return $palette;
        }

        $palette_variant = (string) ee()->input->get('palette');
        if ($palette_variant === '') {
            $palette_variant = '10';
        }

        // Option 2: warmer warning tone for 404, deeper green for 301.
        if ($palette_variant === '2') {
            $palette = array(
                'not_found' => array(
                    'line' => 'rgba(245, 158, 11, 1)',
                    'fill' => 'rgba(245, 158, 11, 0.18)',
                    'bar' => 'rgba(245, 158, 11, 0.84)',
                    'bar_border' => 'rgba(180, 83, 9, 1)',
                ),
                'redirect_301' => array(
                    'line' => 'rgba(5, 150, 105, 1)',
                    'fill' => 'rgba(5, 150, 105, 0.18)',
                    'bar' => 'rgba(5, 150, 105, 0.84)',
                    'bar_border' => 'rgba(4, 120, 87, 1)',
                ),
            );

            return $palette;
        }

        // Option 3: crisp danger red + vivid emerald.
        if ($palette_variant === '3') {
            $palette = array(
                'not_found' => array(
                    'line' => 'rgba(220, 38, 38, 1)',
                    'fill' => 'rgba(220, 38, 38, 0.24)',
                    'bar' => 'rgba(220, 38, 38, 0.9)',
                    'bar_border' => 'rgba(153, 27, 27, 1)',
                ),
                'redirect_301' => array(
                    'line' => 'rgba(5, 150, 105, 1)',
                    'fill' => 'rgba(5, 150, 105, 0.24)',
                    'bar' => 'rgba(5, 150, 105, 0.9)',
                    'bar_border' => 'rgba(6, 95, 70, 1)',
                ),
            );

            return $palette;
        }

        // Option 4: premium coral vs evergreen, tuned for light CP backgrounds.
        if ($palette_variant === '4') {
            $palette = array(
                'not_found' => array(
                    'line' => 'rgba(239, 68, 68, 1)',
                    'fill' => 'rgba(239, 68, 68, 0.22)',
                    'bar' => 'rgba(239, 68, 68, 0.88)',
                    'bar_border' => 'rgba(185, 28, 28, 1)',
                ),
                'redirect_301' => array(
                    'line' => 'rgba(22, 163, 74, 1)',
                    'fill' => 'rgba(22, 163, 74, 0.22)',
                    'bar' => 'rgba(22, 163, 74, 0.88)',
                    'bar_border' => 'rgba(21, 128, 61, 1)',
                ),
            );

            return $palette;
        }

        // Option 5: high-clarity amber-red + bright green for strongest separation.
        if ($palette_variant === '5') {
            $palette = array(
                'not_found' => array(
                    'line' => 'rgba(234, 88, 12, 1)',
                    'fill' => 'rgba(234, 88, 12, 0.24)',
                    'bar' => 'rgba(234, 88, 12, 0.9)',
                    'bar_border' => 'rgba(194, 65, 12, 1)',
                ),
                'redirect_301' => array(
                    'line' => 'rgba(22, 163, 74, 1)',
                    'fill' => 'rgba(22, 163, 74, 0.24)',
                    'bar' => 'rgba(22, 163, 74, 0.9)',
                    'bar_border' => 'rgba(22, 101, 52, 1)',
                ),
            );

            return $palette;
        }

        // Option 6: jewel-tone contrast (ruby vs turquoise).
        if ($palette_variant === '6') {
            $palette = array(
                'not_found' => array(
                    'line' => 'rgba(190, 24, 93, 1)',
                    'fill' => 'rgba(190, 24, 93, 0.24)',
                    'bar' => 'rgba(190, 24, 93, 0.9)',
                    'bar_border' => 'rgba(131, 24, 67, 1)',
                ),
                'redirect_301' => array(
                    'line' => 'rgba(13, 148, 136, 1)',
                    'fill' => 'rgba(13, 148, 136, 0.24)',
                    'bar' => 'rgba(13, 148, 136, 0.9)',
                    'bar_border' => 'rgba(15, 118, 110, 1)',
                ),
            );

            return $palette;
        }

        // Option 7: fire red + lime for high separation.
        if ($palette_variant === '7') {
            $palette = array(
                'not_found' => array(
                    'line' => 'rgba(185, 28, 28, 1)',
                    'fill' => 'rgba(185, 28, 28, 0.28)',
                    'bar' => 'rgba(185, 28, 28, 0.92)',
                    'bar_border' => 'rgba(127, 29, 29, 1)',
                ),
                'redirect_301' => array(
                    'line' => 'rgba(132, 204, 22, 1)',
                    'fill' => 'rgba(132, 204, 22, 0.26)',
                    'bar' => 'rgba(132, 204, 22, 0.9)',
                    'bar_border' => 'rgba(77, 124, 15, 1)',
                ),
            );

            return $palette;
        }

        // Option 8: high-contrast modern (scarlet vs electric cyan-green).
        if ($palette_variant === '8') {
            $palette = array(
                'not_found' => array(
                    'line' => 'rgba(225, 29, 72, 1)',
                    'fill' => 'rgba(225, 29, 72, 0.28)',
                    'bar' => 'rgba(225, 29, 72, 0.92)',
                    'bar_border' => 'rgba(159, 18, 57, 1)',
                ),
                'redirect_301' => array(
                    'line' => 'rgba(14, 165, 233, 1)',
                    'fill' => 'rgba(14, 165, 233, 0.24)',
                    'bar' => 'rgba(14, 165, 233, 0.9)',
                    'bar_border' => 'rgba(2, 132, 199, 1)',
                ),
            );

            return $palette;
        }

        // Option 9: neutral slate (404) + saturated emerald (301).
        if ($palette_variant === '9') {
            $palette = array(
                'not_found' => array(
                    'line' => 'rgba(71, 85, 105, 1)',
                    'fill' => 'rgba(71, 85, 105, 0.24)',
                    'bar' => 'rgba(71, 85, 105, 0.9)',
                    'bar_border' => 'rgba(51, 65, 85, 1)',
                ),
                'redirect_301' => array(
                    'line' => 'rgba(16, 185, 129, 1)',
                    'fill' => 'rgba(16, 185, 129, 0.24)',
                    'bar' => 'rgba(16, 185, 129, 0.9)',
                    'bar_border' => 'rgba(5, 150, 105, 1)',
                ),
            );

            return $palette;
        }

        // Option 10: neon magenta (404) + electric cyan (301).
        if ($palette_variant === '10') {
            $palette = array(
                'not_found' => array(
                    'line' => 'rgba(217, 70, 239, 1)',
                    'fill' => 'rgba(217, 70, 239, 0.26)',
                    'bar' => 'rgba(217, 70, 239, 0.92)',
                    'bar_border' => 'rgba(162, 28, 175, 1)',
                ),
                'redirect_301' => array(
                    'line' => 'rgba(6, 182, 212, 1)',
                    'fill' => 'rgba(6, 182, 212, 0.24)',
                    'bar' => 'rgba(6, 182, 212, 0.9)',
                    'bar_border' => 'rgba(8, 145, 178, 1)',
                ),
            );

            return $palette;
        }

        // Option 1 (default): refined red vs emerald.
        $palette = array(
            'not_found' => array(
                'line' => 'rgba(244, 63, 94, 1)',
                'fill' => 'rgba(244, 63, 94, 0.16)',
                'bar' => 'rgba(244, 63, 94, 0.82)',
                'bar_border' => 'rgba(190, 18, 60, 1)',
            ),
            'redirect_301' => array(
                'line' => 'rgba(16, 185, 129, 1)',
                'fill' => 'rgba(16, 185, 129, 0.16)',
                'bar' => 'rgba(16, 185, 129, 0.82)',
                'bar_border' => 'rgba(5, 150, 105, 1)',
            ),
        );

        return $palette;
    }

    private function ensureExtensionEnabled()
    {
        $ext = ee()->db->get_where('extensions', array('class' => 'Detour_pro_ext'))->row_array();

        if (!empty($ext) && $ext['enabled'] == 'n') {
            ee()->db->where('class', 'Detour_pro_ext');
            ee()->db->update('extensions', array('enabled' => 'y'));
        }
    }

    //! Linking Methods

    private function _form_url($method = 'index', $variables = array())
    {
        if (substr(APP_VER, 0, 1) > 2) {
            $url = ee('CP/URL')->make('addons/settings/' . $this->_module . '/' . $method, $variables);
        } else {
            $url = 'C=addons_modules' . AMP . 'M=show_module_cp' . AMP . 'module=' . $this->_module . AMP . 'method=' . $method;

            foreach ($variables as $variable => $value) {
                $url .= AMP . $variable . '=' . $value;
            }
        }

        return $url;
    }

    private function _member_link($member_id)
    {
        // if they are anonymous, they don't have a member link
        if (strpos((string) $member_id, 'anon') !== false) {
            return false;
        }

        $url = BASE . AMP . 'D=cp' . AMP . 'C=myaccount' . AMP . 'id=' . $member_id;

        return $url;
    }

    private function skinSupport()
    {
        // To the theme skin... get it?

        if (!defined('URL_THIRD_THEMES')) {
            define('URL_THIRD_THEMES', ee()->config->slash_item('theme_folder_url') . 'third_party/');
        }

        ee()->cp->add_to_head("<link rel='stylesheet' href='" . URL_THIRD_THEMES . "detour_pro/css/detour.css?v" . $this->version . "'>");
    }
}
/* End of file mcp.detour_pro.php */
/* Location: /system/expressionengine/third_party/detour_pro/mcp.detour_pro.php */
