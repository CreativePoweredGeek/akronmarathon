<?php

use BoldMinded\DataGrab\Model\Endpoint;
use BoldMinded\DataGrab\Service\Importer;
use BoldMinded\DataGrab\Service\QueueStatus;
use BoldMinded\DataGrab\Service\UrlGuard;
use ExpressionEngine\Model\Addon\Action;

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
class Datagrab
{
    /**
     * @var string
     */
    public $return_data = '';

    /**
     * @var array
     */
    private $settings = [];

    /**
     * @var QueueStatus
     */
    private $queueStatus;

    /**
     * @var Importer
     */
    private $importer;

    function __construct()
    {
        $this->queueStatus = ee('datagrab:QueueStatus');
        $this->importer = ee('datagrab:Importer');
    }

    private function loadImportSettings(int|null $importId = null, string $passKey = '', bool $requirePasskey = false): void
    {
        // Fetch import settings
        $query = ee()->db
            ->where('id', $importId)
            ->get('datagrab');

        if ($query->num_rows() == 0) {
            $this->importer->logger->log('Import aborted. Requested Import ID not found.');
            exit;
        }

        $row = $query->row_array();
        $this->settings = json_decode($row['settings'], true);

        // Imports executed from a public request must be gated by a non-empty passkey,
        // but only for imports configured under the versioned (post-6.1.0) format.
        // Legacy imports have version 0 and keep their original behavior.
        $isVersionedImport = (float) ($row['version'] ?? 0) > 0;

        if ($requirePasskey && $isVersionedImport && $row['passkey'] === '') {
            $this->importer->logger->log('Import aborted. This import has no passkey and cannot be run from a public request.');
            exit;
        }

        if ($row['passkey'] !== '' && !hash_equals((string) $row['passkey'], (string) $passKey)) {
            $this->importer->logger->log('Import aborted. Passkey required, but none provided.');
            exit;
        }

        ini_set('memory_limit', '1G');

        $this->settings['import']['id'] = $importId;
        $this->settings['import']['passkey'] = $passKey;
        $this->settings['import']['site_id'] = $row['site_id'];
    }

    /**
     * Run an import via an action
     *
     * @param null $importId
     * @param string $passKey
     * @param string $fileName
     * @return void
     * @author BoldMinded, LLC
     */
    public function run_action(int|null $importId = null, string $passKey = '', string $fileName = '')
    {
        if (ee()->input->get('id') != '') {
            $importId = ee()->input->get('id');
        }
        // Prefer the passkey from a request header so it stays out of URLs/logs;
        // fall back to the query string for backward compatibility.
        $headerPassKey = ee()->input->get_request_header('X-Datagrab-Passkey');
        if ($headerPassKey) {
            $passKey = $headerPassKey;
        } elseif (ee()->input->get('passkey') != '') {
            $passKey = ee()->input->get('passkey');
        }
        if (!$importId) {
            $this->importer->logger->log('Import aborted. No Import ID provided.');
            exit;
        }

        ee()->load->helper('url');
        ee()->load->library('javascript');
        ee()->load->model('template_model');

        $this->loadImportSettings($importId, $passKey, true);

        // A request-supplied filename may only override the stored one when it is a
        // safe http/https URL. Local paths and non-http schemes are rejected to
        // prevent SSRF / local file disclosure via the public ACT endpoint.
        // https://boldminded.com/support/ticket/2514
        if ($fileName) {
            if (!UrlGuard::isSafeRequestFilename($fileName)) {
                $this->importer->logger->log('Import aborted. The supplied filename is not an allowed http/https URL.');
                exit;
            }

            $_GET['filename'] = $fileName;
            $this->settings['datatype']['filename'] = $fileName;
        } elseif (ee()->input->get('filename') !== false) {
            if (
                ee()->input->get('filename') == 'POST' &&
                ee()->input->post('data') !== false
            ) {
                // write to cache file
                // set filename to cache file
                // clean up cache
                print tempnam("/tmp", '');
                exit;
            }

            if (!UrlGuard::isSafeRequestFilename(ee()->input->get('filename'))) {
                $this->importer->logger->log('Import aborted. The supplied filename is not an allowed http/https URL.');
                exit;
            }

            $this->settings['datatype']['filename'] = ee()->input->get('filename');
        }

        // Kick it over to the action url
        if (!ee()->input->get('ACT')) {
            /** @var Action $actionModel */
            $action = ee('Model')->get('Action')
                ->filter('class', 'Datagrab')
                ->filter('method', 'run_action')
                ->first();

            // Set these the first time, subsequent requests will already have these once we're on the ?ACT url
            $_GET['ACT'] = $action->action_id;
            $_GET['passkey'] = $passKey;
        }

        if (ee()->input->get('author_id') !== false) {
            $this->settings["config"]["author"] = ee()->input->get('author_id');
        }

        try {
            ee()->output->enable_profiler(false);

            $shouldProduce = true;
            $shouldConsume = true;

            if (ee()->input->get('consume') === 'yes' && !ee()->input->get('produce')) {
                $shouldProduce = false;
            }

            if (ee()->input->get('produce') === 'yes' && !ee()->input->get('consume')) {
                $shouldConsume = false;
            }

            // Produce and immediately consume, similar to how importer used to operate,
            // unless one or the other is explicitly defined.

            $this->importer->setup(
                $this->importer->datatypes[$this->settings['import']['type']],
                    $this->settings,
                    $shouldProduce
                );

            if ($shouldProduce) {
                if (ee()->input->get('restart') === 'yes') {
                    $this->importer->resetImport();
                }

                $this->importer->produceJobs();
            }

            if ($shouldConsume) {
                $this->importer->consumeJobs();
            }

            $shouldWork = !$this->importer->isImportComplete() || !$this->importer->isDeleteComplete();

            // If executing via the ACT URL outside the control panel keep refreshing
            // to keep consuming and avoid server timeouts.
            if (ee()->input->get('iframe') !== 'yes') {
                $workerOptions = ee('datagrab:QueueWorkerOptions');
                $url = $this->getRefreshUrl($importId, [
                    'passkey' => $passKey,
                    'filename' => $fileName,
                    'iframe' => 'no'
                ]);

                if (!AJAX_REQUEST) {
                    $this->importer->logger->log('Displaying import response as HTML. Not an Ajax request.');

                    // Necessary to call head_link()
                    ee()->load->library('view');

                    $logFile = '';

                    if (file_exists(PATH_CACHE . 'DataGrab-import.log')) {
                        $logFile = file_get_contents(PATH_CACHE . 'DataGrab-import.log');
                    }

                    $viewVars = array_merge(
                        $this->queueStatus->fetch($importId)[$importId],
                        [
                            'refreshTimeout' => 0,
                            'refreshUrl' => $url,
                            'styleTag' => ee()->view->head_link('css/common.min.css'),
                            'importName' => $row['name'] ?? '',
                            'logFile' => $logFile,
                        ]
                    );

                    // Wait for the worker to time out before creating a new one. Not 100% necessary, but keep
                    // resources in check. User can optionally "Continue Now"
                    if ($shouldWork) {
                        $viewVars['refreshTimeout'] = $workerOptions->timeout + 3;
                    }

                    // Nothing to import, but it's trying to consume, so break out of an infinite loop of redirects.
                    if (!$shouldWork && $shouldConsume) {
                        $viewVars['refreshTimeout'] = null;
                    }

                    ee()->load->view('response-html', $viewVars);
                }
            }

            if (ee()->input->get('iframe') === 'yes' && $shouldWork) {
                $url = $this->getRefreshUrl($importId, [
                    'passkey' => $passKey,
                    'filename' => $fileName,
                ]);

                // Each worker has a max execution time set via it's WorkerOptions when that is reached
                // it will go into "WAITING" when it stops. So we'll immediately refresh this URL and
                // create a new worker which will pick up where the previous one left off. This is all
                // to avoid any PHP or server based timeouts.
                echo sprintf('<meta http-equiv="refresh" content="%d;url=%s">', 0, $url);
            }

        } catch (Error $exception) { // Catch EE Core exceptions
            $this->importer->logger->log($exception->getMessage());
            $this->importer->logger->log($exception->getTraceAsString());
        } catch (Exception $exception) { // Catch general exceptions
            $this->importer->logger->log($exception->getMessage());
            $this->importer->logger->log($exception->getTraceAsString());
        }
    }

    public function run_endpoint()
    {
        $endpointName = ee()->input->get('endpoint');

        $this->importer->logger->log(sprintf(
            'Executing endpoint "%s".',
            $endpointName
        ));

        $endpoint = ee('Model')->get('datagrab:Endpoint')
            ->filter('name', $endpointName)
            ->first();

        if (!$endpoint) {
            $this->importer->logger->log(sprintf(
                'The requested endpoint "%s" was not found.',
                $endpointName
            ));

            exit;
        }

        if (!$this->isEndpointAuthenticated($endpoint)) {
            $this->importer->logger->log(sprintf(
                'The requested endpoint "%s" was not successfully authenticated.',
                $endpointName
            ));

            exit;
        }

        $this->loadImportSettings($endpoint->import_id);

        $type = $this->settings['import']['type'];

        $this->importer->setup(
            $this->importer->datatypes[$this->settings['import']['type']],
            $this->settings,
        );

        $postData = file_get_contents("php://input");

        if (!$postData) {
            $this->importer->logger->log(sprintf('%s can\'t find post body content from sender', $endpointName));
            exit;
        }

        $result = $this->importer->datatypes[$type]->fetch($postData);

        if ($result === -1) {
            $this->importer->logger->log($this->importer->datatypes[$type]->getErrors());
            exit;
        }

        $data = $this->importer->datatypes[$type]->getItems();

        try {
            ee()->output->enable_profiler(false);
            $this->importer->produceEndpointJobs($data);

            ee()->output->send_ajax_response([
                'response' => 'success'
            ]);
        } catch (Error $exception) { // Catch EE Core exceptions
            $this->importer->logger->log($exception->getMessage());
            $this->importer->logger->log($exception->getTraceAsString());

            ee()->output->send_ajax_response([
                'response' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (Exception $exception) { // Catch general exceptions
            $this->importer->logger->log($exception->getMessage());
            $this->importer->logger->log($exception->getTraceAsString());

            ee()->output->send_ajax_response([
                'response' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function isEndpointAuthenticated(Endpoint $endpoint): bool
    {
        $authSettings = json_decode($endpoint->settings, true);
        $authParams = $authSettings['auth_grid']['rows'] ?? [];

        // Secrets are only accepted from request headers. Query-string ('get') auth
        // leaks secrets via logs, history, and Referer, so it is no longer honored.
        $requestParams = match($endpoint->auth_type) {
            'headers' => getallheaders(),
            default => null,
        };

        if ($requestParams === null) {
            return false;
        }

        if (empty($authParams)) {
            return false;
        }

        $matchedParams = 0;

        foreach ($authParams as $pair) {
            if (
                isset($requestParams[$pair['auth_name']]) &&
                hash_equals((string) $pair['auth_value'], (string) $requestParams[$pair['auth_name']])
            ) {
                $matchedParams++;
            }
        }

        return $matchedParams === count($authParams);
    }

    /**
     * @param int $importId
     * @param string $passKey
     * @param string $fileName
     * @return string
     */
    private function getRefreshUrl(int $importId, array $params = [])
    {
        $queryParams = array_merge([
            'ACT' => ee()->input->get('ACT'),
            'id' => $importId,
            'consume' => 'yes',
        ], array_filter($params));

        return ee()->functions->fetch_site_index(0, 0) . '?' . http_build_query($queryParams);
    }

    /**
     * Run an import from a front end template
     *
     * @return void
     * @author BoldMinded, LLC
     */
    public function run_saved_import()
    {
        $this->run_action(
            ee()->TMPL->fetch_param('id'),
            ee()->TMPL->fetch_param('passkey'),
            ee()->TMPL->fetch_param('filename')
        );
    }

    /**
     * These ACT endpoints are only used by the Control Panel listing UI, so they
     * require an authenticated CP member with add-on access.
     */
    private function requireCpAccess(): void
    {
        if (!ee('Permission')->isSuperAdmin() && !ee('Permission')->can('access_addons')) {
            ee()->output->set_status_header(403);
            ee()->output->send_ajax_response(['response' => 'unauthorized']);
            exit;
        }
    }

    /**
     * Confirms the import id exists and belongs to the current site.
     */
    private function importBelongsToCurrentSite(int $id): bool
    {
        if (!$id) {
            return false;
        }

        $count = ee()->db
            ->where('id', $id)
            ->where('site_id', (int) ee()->config->item('site_id'))
            ->count_all_results('datagrab');

        return $count > 0;
    }

    /**
     * Public ACT request endpoint
     *
     * @return void
     */
    public function fetch_queue_status()
    {
        $this->requireCpAccess();

        $id = (int) ee()->input->get('id');

        if (!$this->importBelongsToCurrentSite($id)) {
            ee()->output->send_ajax_response(['response' => []]);
            exit;
        }

        ee()->output->send_ajax_response([
            'response' => $this->queueStatus->fetch($id)
        ]);

        exit;
    }

    /**
     * @param int $id
     * @return int
     */
    private function purgeQueue(int $id = 0): int
    {
        if (!$id) {
            $id = ee()->input->get('id');
        }

        $output = 0;

        if ($id) {
            $this->importer->updateStatus('ABORTED', $id);

            $deleteQueue = $this->queueStatus->clear($this->importer->getDeleteQueueName($id));
            $importQueue = $this->queueStatus->clear($this->importer->getImportQueueName($id));

            return $deleteQueue + $importQueue;
        }

        return $output;
    }

    /**
     * Public ACT request endpoint
     *
     * @return void
     */
    public function purge_queue()
    {
        $this->requireCpAccess();
        $this->verifyCsrf();

        $id = (int) ee()->input->post('id');

        if (!$this->importBelongsToCurrentSite($id)) {
            ee()->output->set_status_header(404);
            ee()->output->send_ajax_response(['response' => 0]);
            exit;
        }

        ee()->output->send_ajax_response([
            'response' => $this->purgeQueue($id)
        ]);

        exit;
    }

    public function sort_imports()
    {
        $this->requireCpAccess();
        $this->verifyCsrf();

        $order = ee()->input->post('order', true);

        if (!is_array($order)) {
            exit;
        }

        $siteId = (int) ee()->config->item('site_id');

        foreach ($order as $index => $id) {
            ee()->db
                ->where('id', (int) $id)
                ->where('site_id', $siteId)
                ->update('datagrab', [
                    'order' => $index
                ]);
        }
    }

    /**
     * Reject state-changing requests that lack a valid CSRF token.
     */
    private function verifyCsrf(): void
    {
        $token = ee()->input->post('csrf_token') ?: ee()->input->get_request_header('X-CSRF-TOKEN');

        if (!$token || !hash_equals((string) CSRF_TOKEN, (string) $token)) {
            ee()->output->set_status_header(403);
            ee()->output->send_ajax_response(['response' => 'invalid_csrf']);
            exit;
        }
    }
}
