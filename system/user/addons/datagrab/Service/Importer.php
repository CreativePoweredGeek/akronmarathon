<?php

namespace BoldMinded\DataGrab\Service;

use BoldMinded\DataGrab\DataTypes\AbstractDataType;
use BoldMinded\DataGrab\DataTypes\AbstractDataTypeLegacy;
use BoldMinded\DataGrab\Dependency\Illuminate\Queue\WorkerOptions;
use BoldMinded\DataGrab\Dependency\Illuminate\Queue\Worker;
use BoldMinded\DataGrab\Dependency\Illuminate\Queue\QueueManager;
use BoldMinded\DataGrab\fieldtypes\ImportField;
use BoldMinded\DataGrab\Model\ImportStatus;
use BoldMinded\DataGrab\Service\ContentType\ContentTypeFactory;
use BoldMinded\DataGrab\Service\ContentType\ContentTypeInterface;
use BoldMinded\DataGrab\Traits\FileUploadDestinations;
use EE_Addons;
use EE_Config;
use EE_Extensions;
use EE_Functions;
use EE_Loader;
use EE_Session;
use EE_Template;
use ExpressionEngine\Model\Channel\ChannelEntry;
use ExpressionEngine\Service\Database\Query;

/**
 * DataGrab Importer Service
 *
 * Handles the DataGrab import process
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Importer
{
    public $datatypes = [];
    public $settings = [];
    public $dataType;
    public $channelDefaults;
    public $entries = [];
    public $entryData = [];
    public $importedFileVars = [];
    public $importedFilePaths = [];
    public $validStatuses;
    public $checkMemory = false;
    public $memoryUsage = 0;
    public $currentUser = 0;
    public $currentImportId = false;

    use FileUploadDestinations;

    public function __construct(
        private EE_Addons $addons,
        private EE_Config $config,
        private EE_Extensions $extensions,
        private EE_Functions $functions,
        private EE_Session $session,
        private EE_Loader $load,
        private EE_Template $template,
        private Query $db,
        private Logger $logger,
        private DataGrabLoader $loader,
        private ImportStatus $importStatus,
        // Do not type hint to preserve cross functionality with the Queue addon
        private $queueManager,
        private $queueWorker,
        private $queueWorkerOptions,
    ) {
        // Used to store a list of which entries have been imported/updated
        $this->entryData = [];
        $this->entries = [];
    }

    /**
     * Resolves the strategy describing how this import binds to EE's content
     * models (entry, file or member).
     */
    public function getContentType(): ContentTypeInterface
    {
        return ContentTypeFactory::fromSettings($this->settings);
    }

    /**
     * I'm not 100% sure why, but without this the imports do not run as expected.
     * It doubles up the logs and weird things happen. This method is a remnant of when
     * this used to be called as an old style EE model, e.g. ee()->datagrab->something().
     * I'm not sure if since this is now a singleton it's acting funky with EE's
     * services or what, but until I figure it out, this needs to stay.
     */
    public function __get($key)
    {
        return $this->$key;
    }

    /**
     * @return array
     */
    public function fetch_datatype_names(): array
    {
        $this->initialise_types();

        $types = [];
        foreach ($this->datatypes as $type_name => $type) {
            if (str_contains($type_name, '_legacy')) {
                continue;
            }

            $types[$type_name] = $type->display_name();
        }

        return $types;
    }

    /**
     * @return void
     */
    public function initialise_types()
    {
        // Load native
        $this->loadTypesFromPath(PATH_THIRD . 'datagrab/datatypes/');
        // Try to load any 3rd party types
        $this->loadTypesFromPath(PATH_THIRD . 'datagrab_datatypes/');

        ksort($this->datatypes);
    }

    private function loadTypesFromPath(string $path = '')
    {
        if (!is_dir($path)) {
            return;
        }

        $dir = opendir($path);

        while (($folder = readdir($dir)) !== false) {
            if (
                is_dir($path . $folder) &&
                $folder != "." && $folder != ".." &&
                substr($folder, 0, 1) != "_"
            ) {
                $filename = sprintf('/dt.datagrab_%s.php', $folder);
                $className = 'Datagrab_' . $folder;
                $fileExists = file_exists($path . $folder . $filename);

                if (!$fileExists) {
                    continue;
                }

                if (!class_exists($className)) {
                    include($path . $folder . $filename);
                }

                if (class_exists($className)) {
                    $this->datatypes[$folder] = new $className();
                }
            }
        }

        closedir($dir);
    }

    /**
     * @throws \Exception
     */
    public function setup(
        AbstractDataType|AbstractDataTypeLegacy $dataType,
        array            $settings = [],
        bool             $shouldProduce = false
    ): Importer
    {
        $this->tick('import');
        $this->tick('import_init');

        // @todo Remove when support for 7.4 is dropped, and Laravel and Carbon dependencies are updated.
        // https://boldminded.com/support/ticket/2715
        error_reporting(E_ALL & ~E_USER_DEPRECATED);

        $this->dataType = $dataType;
        $this->settings = $settings;

        $this->currentImportId = $this->settings['import']['id'] ?? 0;

        // Don't save queries
        $this->db->save_queries = false;

        $this->load->library('addons');
        $this->load->helper('date');
        $this->load->language('content');

        date_default_timezone_set('UTC');

        // Set up the data source
        $this->dataType->initialise($this->settings);

        if ($shouldProduce) {
            $this->dataType->fetch();
        }

        // Did the datatype bubble up any errors?
        if (!empty($this->dataType->getErrors())) {
            foreach ($this->dataType->getErrors() as $dataTypeErrorMessage) {
                $this->logger->log($dataTypeErrorMessage);
            }
        }

        // Can the current member use the Channel API (the import might not be running from the Control Panel)?
        $this->checkMemberStatus();

        // Get channel details for default values. Only entry imports target a
        // channel, so there is nothing to look up for file/member imports.
        if ($this->getContentType()->getName() === 'entry') {
            $this->channelDefaults = $this->fetchChannelDefaults($this->settings["import"]["channel"]);
        }

        $this->tock('import_init');

        return $this;
    }

    private function prependDebugToLogger()
    {
        $this->logger->log('======== ENV ========');
        $this->logger->log('max_execution_time: ' . ini_get('max_execution_time'));
        $this->logger->log('max_input_time: ' . ini_get('max_input_time'));
        $this->logger->log('max_input_vars: ' . ini_get('max_input_vars'));
        $this->logger->log('memory_limit: ' . ini_get('memory_limit'));

        $this->logger->log(sprintf('Import settings: %s', json_encode($this->settings, JSON_PRETTY_PRINT)));

        $this->logger->log('====== END ENV ======');
    }

    public function produceJobs(): Importer
    {
        // Loop over items
        $totalRows = $this->dataType->total_rows_real();
        $iterator = 1;

        $this->logger->reset();
        $this->prependDebugToLogger();

        $this->db
            ->where('id', $this->currentImportId)
            ->update('datagrab', [
                'total_records' => $totalRows,
                'last_record' => 0,
                'last_started' => time(),
            ]);

        $queueName = $this->getImportQueueName();

        // If the current queue size matches the total number of rows to import, assume we don't need to push again.
        // @todo create a config option to ignore this check?
        if ($this->queueManager->size($queueName) === $totalRows) {
            return $this;
        }

        $this->updateStatus(ImportStatus::QUEUING);

        $importJobClass = $this->getContentType()->getImportJobClass();

        while ($item = $this->dataType->next()) {
            if (is_array($item)) {
                $this->queueManager->push($importJobClass, $item, $queueName);
            }

            if ($iterator > $totalRows) {
                break;
            }

            $iterator++;
        }

        $this->updateStatus(ImportStatus::WAITING);

        return $this;
    }

    public function produceEndpointJobs(array $items): Importer
    {
        // Loop over items
        $totalRows = count($items);
        $iterator = 1;

        $this->logger->reset();
        $this->prependDebugToLogger();

        $this->db
            ->where('id', $this->currentImportId)
            ->update('datagrab', [
                'total_records' => $totalRows,
                'last_record' => 0,
                'last_started' => time(),
            ]);

        $queueName = $this->getImportQueueName();

        $this->updateStatus(ImportStatus::QUEUING);

        $importJobClass = $this->getContentType()->getImportJobClass();

        foreach ($items as $item) {
            if (is_array($item)) {
                $this->queueManager->push($importJobClass, $item, $queueName);
            }

            if ($iterator > $totalRows) {
                break;
            }

            $iterator++;
        }

        $this->updateStatus(ImportStatus::WAITING);

        return $this;
    }

    public function consumeJobs(): Importer
    {
        $importQueueSize = $this->getImportQueueSize();
        $deleteQueueSize = $this->getDeleteQueueSize();

        if ($importQueueSize <= 0 && $deleteQueueSize <= 0) {
            // Write to log only once. Don't kill the worker though.
            // If something else is added to the queue we want the worker to pick it up.
            if ($this->getStatus($this->currentImportId) !== ImportStatus::COMPLETED) {
                $this->logger->log('Queues are empty. Aborting. #' . $this->currentImportId);
                $this->updateStatus(ImportStatus::COMPLETED);
            }

            return $this;
        }

        $this->db
            ->where('id', $this->currentImportId)
            ->update('datagrab', [
                'last_run' => time(),
            ]);

        // -------------------------------------------
        // 'datagrab_pre_import' hook.
        //  - Perform actions before the import is run
        //
        if ($this->extensions->active_hook('datagrab_pre_import') === true) {
            $this->logger->log('Calling datagrab_pre_import() hook.');
            $this->extensions->call('datagrab_pre_import', $this);

            if ($this->extensions->end_script === true) {
                return $this;
            }
        }
        //
        // -------------------------------------------

        // 0 works just fine, it's basically means the worker will handle as many jobs as it
        // can based on the amount of time it is allowed to run and how much memory it can consume
        // before it self terminates and starts a new worker.
        $this->queueWorkerOptions->maxJobs = (int) $this->settings['import']['limit'] ?? 0;

        try {
            if ($importQueueSize) {
                $this->logger->log('Starting new import consumer.');
                $this->queueWorker->daemon('default', $this->getImportQueueName(), $this->queueWorkerOptions);
            }

            if ($deleteQueueSize) {
                $this->logger->log('Starting new delete consumer.');
                $this->queueWorker->daemon('default', $this->getDeleteQueueName(), $this->queueWorkerOptions);
            }
        } catch (\Exception $e) {
            $this->logger->log('Consumer error: ' . $e->getMessage());
        }

        // -------------------------------------------
        // 'datagrab_post_import' hook.
        //  - Perform actions after the import is run
        //
        if ($this->extensions->active_hook('datagrab_post_import') === true) {
            $this->logger->log('Calling datagrab_post_import() hook.');
            $this->extensions->call('datagrab_post_import', $this);
            if ($this->extensions->end_script === true) {
                return $this;
            }
        }
        //
        // -------------------------------------------

        if ($this->shouldDeleteOldRecords()) {
            $this->trackImportEntries($this->entries);
        }

        if (!$this->currentUser) {
            $this->session->destroy();
        }

        $errorRecords = $this->importStatus->getError();

        if ($errorRecords > 0) {
            $this->updateErrorRecords($errorRecords);
        }

        if ($this->isImportComplete()) {
            $entryIdsToDelete = $this->getOldEntriesToDelete($this->getImportEntries());

            // Wait for the import queue to be finished, then compare what was imported vs what should be deleted.
            if (
                $this->shouldDeleteOldRecords() &&
                count($entryIdsToDelete) &&
                !$this->isDeleteComplete()
            ) {
                $this->deleteOldEntries($entryIdsToDelete);

                // Not too fast, we now have things in the delete queue.
                return $this;
            }

            $this->updateStatus(ImportStatus::COMPLETED);

            // Report and update caches
            if ($this->importStatus->getAdded() > 0) {
                $this->logger->log(sprintf('Added %d entries', $this->importStatus->getAdded()));

                if ($this->extensions->active_hook('datagrab_entries_added_count') === true) {
                    $this->logger->log('Calling datagrab_entries_added_count() hook.');
                    $this->extensions->call('datagrab_entries_added_count', $this->importStatus->getAdded());
                }

                if (bool_config_item('new_posts_clear_caches')) {
                    $this->logger->log('Clearing all cache');
                    $this->functions->clear_caching('all');
                } else {
                    $this->logger->log('Clearing sql cache');
                    $this->functions->clear_caching('sql_cache');
                }
            }

            if ($this->importStatus->getUpdated() > 0) {
                $this->logger->log(sprintf('Updated %d entries', $this->importStatus->getUpdated()));

                if ($this->extensions->active_hook('datagrab_entries_updated_count') === true) {
                    $this->logger->log('Calling datagrab_entries_updated_count() hook.');
                    $this->extensions->call('datagrab_entries_updated_count', $this->importStatus->getUpdated());
                }
            }

            try {
                $this->createImageManipulations();
            } catch (\Exception $e) {
                $this->logger->log('Error creating image manipulations for a file. This could be due to the file not being
                an actual image, or another issue with ExpressionEngine\'s syncFiles() command.');
                $this->logger->log($e->getMessage());
            }
        }

        return $this;
    }

    private function createImageManipulations(): void
    {
        $filePaths = array_values($this->importedFilePaths);
        $fileVars = $this->importedFileVars;

        if (count($fileVars) === 0) {
            return;
        }

        foreach ($fileVars as $fileVar => $isNew) {
            $uploadLocation = File::getFileUploadLocationFromFileVar($fileVar);

            $fileDimensions = ee('Model')
                ->get('FileDimension')
                ->filter('upload_location_id', $uploadLocation->id)
                ->all();

            if (count($fileDimensions) === 0) {
                continue;
            }

            $allSizes = [];
            foreach ($fileDimensions as $size) {
                $allSizes[$size->upload_location_id][$size->id] = [
                    'short_name' => $size->short_name,
                    'resize_type' => $size->resize_type,
                    'width' => $size->width,
                    'height' => $size->height,
                    'quality' => $size->quality,
                    'watermark_id' => $size->watermark_id
                ];
            }
        }

        $uploadLocation->syncFiles($filePaths, $allSizes, $fileDimensions->pluck('id'));
    }

    /**
     * Delete any old entries. Pushes them into a queue which will be picked up
     * by a worker when the import is finished.
     *
     * @param array $toDeleteEntryIds
     * @return void
     */
    private function deleteOldEntries(array $toDeleteEntryIds = [])
    {
        if (!empty($toDeleteEntryIds)) {
            $jobClassName = $this->getContentType()->getDeleteJobClass();

            $this->logger->log(sprintf('Deleting %d old entries', count($toDeleteEntryIds)));
            $deleteEntryIds = $this->getDeleteEntries();

            foreach ($toDeleteEntryIds as $deleteId) {
                // Make sure we don't re-add entries. It'll put us into a loop.
                if (!in_array($deleteId, $deleteEntryIds)) {
                    $this->queueManager->push($jobClassName, ['entryId' => $deleteId], $this->getDeleteQueueName());
                    $this->trackDeleteEntries([$deleteId]);
                }
            }
        }
    }

    /**
     * Find any entries to potentially delete which were not just imported.
     *
     * @param array $importedEntryIds
     * @return array
     */
    private function getOldEntriesToDelete(array $importedEntryIds = []): array
    {
        if (empty($importedEntryIds)) {
            return [];
        }

        $settings = $this->settings;

        // The channel strategy needs a channel_id, which is resolved during setup()
        // rather than stored directly on the import settings.
        if (isset($this->channelDefaults['channel_id'])) {
            $settings['import']['channel'] = $this->channelDefaults['channel_id'];
        }

        $toDeleteEntryIds = $this->getContentType()->getRecordsToDelete($importedEntryIds, $settings);

        $this->updateDeleteRecords(count($toDeleteEntryIds));

        return $toDeleteEntryIds;
    }

    private function getImportEntries(): array
    {
        $existingResult = $this->db
            ->where('id', $this->currentImportId)
            ->get('datagrab')
            ->row('import_entryids');

        $existingEntryIds = [];

        if ($existingResult) {
            $existingEntryIds = json_decode($existingResult, true);
        }

        return $existingEntryIds;
    }

    private function getDeleteEntries(): array
    {
        $existingResult = $this->db
            ->where('id', $this->currentImportId)
            ->get('datagrab')
            ->row('delete_entryids');

        $existingEntryIds = [];

        if ($existingResult) {
            $existingEntryIds = json_decode($existingResult, true);
        }

        return $existingEntryIds;
    }

    /**
     * Keep tabs on what has been imported thus far to be used to help determine what should be deleted.
     *
     * @param array $entryIds
     * @return void
     */
    private function trackImportEntries(array $entryIds = []): array
    {
        $importEntryIds = $this->getImportEntries();
        $update = array_values(array_unique(array_merge($importEntryIds, array_filter($entryIds))));

        $this->db
            ->where('id', $this->currentImportId)
            ->update('datagrab', [
                'import_entryids' => json_encode($update)
            ]);

        return $update;
    }

    private function trackDeleteEntries(array $entryIds = []): array
    {
        $deleteEntryIds = $this->getDeleteEntries();
        $update = array_unique(array_merge($deleteEntryIds, $entryIds));

        $this->db
            ->where('id', $this->currentImportId)
            ->update('datagrab', [
                'delete_entryids' => json_encode($update)
            ]);

        return $update;
    }

    private function shouldDeleteOldRecords(): bool
    {
        return isset($this->settings['config']['delete_old'])
            && get_bool_from_string($this->settings['config']['delete_old']);
    }

    private function updateDeleteRecords(int $count)
    {
        $this->db
            ->where('id', $this->currentImportId)
            ->update('datagrab', [
                'total_delete_records' => $count
            ]);
    }

    private function getDeleteRecords(): int
    {
        return $this->db
            ->where('id', $this->currentImportId)
            ->get('datagrab')
            ->row('total_delete_records');
    }

    private function updateErrorRecords(int $count)
    {
        $this->db
            ->where('id', $this->currentImportId)
            ->set('error_records', 'error_records + ' . $count, false)
            ->update('datagrab');
    }

    public function recordImportedEntryIds()
    {
        /** @var \CI_DB_result $query */
        $query = $this->db->get_where('datagrab', ['id' => $this->currentImportId]);

        $lastRecord = $query->row('last_record') ?: 0;

        $this->db
            ->where('id', $this->currentImportId)
            ->update('datagrab', [
                'last_record' => $lastRecord + 1,
            ]);
    }

    /**
     * Called from the DeleteEntry job
     */
    public function recordDeletedEntryIds()
    {
        /** @var \CI_DB_result $query */
        $query = $this->db->get_where('datagrab', ['id' => $this->currentImportId]);

        $lastRecord = $query->row('last_delete_record') ?: 0;

        $this->db
            ->where('id', $this->currentImportId)
            ->update('datagrab', [
                'last_delete_record' => $lastRecord + 1,
            ]);
    }

    /**
     * @return Importer
     */
    public function purgeQueue(int $importId = 0): Importer
    {
        try {
            $this->queueManager->connection('default')->clear($this->getImportQueueName($importId));
            $this->queueManager->connection('default')->clear($this->getDeleteQueueName($importId));
        } catch (\Exception $exception) {
            $this->logger->log($exception->getMessage());
        }

        return $this;
    }

    /**
     * @param int $importId
     * @return string
     */
    public function getImportQueueName(int $importId = 0): string
    {
        if ($importId !== 0) {
            return 'import_' . $importId . $this->getQueueNameSuffix();
        }

        return 'import_' . $this->currentImportId . $this->getQueueNameSuffix();
    }

    /**
     * @return string
     */
    public function getDeleteQueueName($importId = 0): string
    {
        if ($importId !== 0) {
            return 'delete_' . $importId . $this->getQueueNameSuffix();
        }

        return 'delete_' . $this->currentImportId . $this->getQueueNameSuffix();
    }

    /**
     * @return string
     */
    private function getQueueNameSuffix(): string
    {
        $driver = ee()->config->item('datagrab')['driver'] ?? '';

        if ($driver === 'sqs') {
            return '.fifo';
        }

        return '';
    }

    /**
     * @return bool
     */
    public function isImportComplete(): bool
    {
        $result = $this->db
            ->where('id', $this->currentImportId)
            ->get('datagrab');

        if (
            $result->row('last_record') + $result->row('error_records') >= $result->row('total_records')
            && $this->getImportQueueSize() === 0
        ) {
            return true;
        }

        return false;
    }

    public function isDeleteComplete(): bool
    {
        if (!$this->shouldDeleteOldRecords()) {
            return true;
        }

        $result = $this->db
            ->where('id', $this->currentImportId)
            ->get('datagrab');

        if (
            $result->row('total_delete_records') > 0
            && $result->row('last_delete_record') + $result->row('error_records') >= $result->row('total_delete_records')
            && $this->getDeleteQueueSize() === 0
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param int $importId
     * @return int
     */
    public function getImportQueueSize(int $importId = 0): int
    {
        return $this->queueManager->size($this->getImportQueueName($importId));
    }

    /**
     * @param int $importId
     * @return int
     */
    public function getDeleteQueueSize(int $importId = 0): int
    {
        return $this->queueManager->size($this->getDeleteQueueName($importId));
    }

    public function getStatus(int $importId = 0): string
    {
        return $this->db
            ->where('id', $importId ?: $this->currentImportId)
            ->get('datagrab')
            ->row('status');
    }

    /**
     * @param string $status
     * @return void
     */
    public function updateStatus(string $status, int $importId = 0)
    {
        $update = [
            'status' => $status,
        ];

        if ($status === ImportStatus::COMPLETED) {
            $update['import_entryids'] = null;
            $update['delete_entryids'] = null;
        }

        $this->db
            ->where('id', $importId ?: $this->currentImportId)
            ->update('datagrab', $update);

        $this->logger->log($status);
    }

    /**
     * @return Importer
     */
    public function resetImport(int $importId = 0, bool $terminateWorker = false): Importer
    {
        $importId = $importId ?: $this->currentImportId;

        $this->db
            ->where('id', $importId)
            ->update('exp_datagrab', [
                'status' => ImportStatus::NEW,
                'last_record' => 0,
                'total_records' => 0,
                'total_delete_records' => 0,
                'last_delete_record' => 0,
                'error_records' => 0,
                'import_entryids' => null,
                'delete_entryids' => null,
            ]);

        if ($terminateWorker) {
            $this->queueWorker->shouldQuit = true;
        }

        $this->purgeQueue($importId);

        return $this;
    }

    public function importFile(array $item = [])
    {
        // Reset time out
        set_time_limit(60);

        $this->tick('import_file');

        if ($this->checkMemory) {
            $this->checkMemoryUsage("Start");
        }

        // -------------------------------------------
        // 'datagrab_modify_data' hook.
        //  - Perform actions before the import is run
        //
        if ($this->extensions->active_hook('datagrab_modify_data') === true) {
            $this->logger->log('Calling datagrab_modify_data() hook.');
            $item = $this->extensions->call('datagrab_modify_data', $item);

            if ($this->extensions->end_script === true) {
                return false;
            }
        }
        //
        // -------------------------------------------

        // Initialise array to store entry data
        $data = [];

        // Get title
        $data['title'] = $this->dataType->get_item($item, $this->settings['config']['title']);
        $titleSuffix = $this->settings['config']['title_suffix'] ?? '';

        if ($titleSuffix) {
            $data['title'] .= ' ' . $this->dataType->get_item($item, $titleSuffix);
        }

        $this->logger->log(sprintf('--- Begin Importing [%s]', $data['title']));

        // Get date
        $date = $this->settings['config']['date'] ?? '';
        $data['upload_date'] = $this->parseDate($this->dataType->get_item($item, $date));

        $handler = $this->loader->loadFieldTypeHandler('file');
        $fieldValue = $this->settings['cf']['import_file']['value'] ?? '';

        $data['field_id_1'] = $handler->preparePostData(new ImportField(
            importer: $this,
            importItem: $item,
            propertyName: $fieldValue, // complex types will not have a value
            propertyValue: $this->dataType->get_item($item, $fieldValue),
            fieldImportConfig: $this->settings['cf']['import_file'],
            fieldSettings: $this->getCustomFieldSettings('import_file'),
            entryId: 0,
            fieldName: 'import_file',
            fieldId: 1,
        ));

        // No of seconds to offset dates/times
        $timeOffset = $this->settings['config']['offset'] ?? 0;
        $data['upload_date'] += $timeOffset;
        $fileVar = $data['field_id_1'];

        // Check for duplicate entry
        if (!isset($this->settings['config']['unique'])) {
            $this->settings['config']['unique'] = '';
        }

        $fileId = File::getFileIdFromFileVar($fileVar);

        // -------------------------------------------
        // 'datagrab_modify_data_end' hook.
        //  - Let other 3rd party developers modify the data array prior to the Entry API handling it.
        //
        if ($this->extensions->active_hook('datagrab_modify_data_end') === true) {
            $this->logger->log('Calling datagrab_modify_data_end() hook.');
            $data = $this->extensions->call('datagrab_modify_data_end', $this, $data, $item);
        }
        //
        // -------------------------------------------

        $data['cp_call'] = true;

        // Disable notices while running this. Too many 3rd party fieldtypes giving notices
        $errorLevel = error_reporting();
        error_reporting($errorLevel & ~E_NOTICE);

        $importedFileIsNew = $this->importedFileVars[$fileVar] ?? false;

        // At this point we should always have a file id because the File handler has already done the upload
        if ($fileId) {
            $fileData = [
                'file_id' => $fileId,
            ];

            // Find and create categories
            $fileCategories = $this->setupFileCategories($item, $fileId);
            $fileData['categories'] = $fileCategories;

            /** @var \ExpressionEngine\Model\File\File $file */
            $file = ee('Model')->get('File')
                ->filter('file_id', $fileId)
                ->first();

            $file->set($fileData);
            $file->setCategoriesFromPost($fileData['categories']);

            if ($importedFileIsNew) {
                $file->uploaded_by_member_id = $this->fetchAuthor($item);

                $timeOffset = $this->settings['config']['offset'] ?? 0;
                $file->upload_date = ee()->localize->now + $timeOffset;
            }

            $result = $file->save();

            if ($result) {
                if ($importedFileIsNew) {
                    $this->logger->log('File added');
                    $this->importStatus->incrementAdded();
                } else {
                    $this->logger->log('File updated');
                    $this->importStatus->incrementUpdated();
                }
            } else {
                $this->importStatus->incrementError();
                $this->logger->log('Unable to save file_model data: ' . json_encode($fileData));
            }

            // Not entry_ids, but doesn't matter an ID is an ID in this case.
            $this->entryData[] = $data;
            $this->entries[] = (int) $fileId;

            $this->recordImportedEntryIds();
        }

        $this->tock('import_file');

        return true;
    }

    /**
     * Import a single member row. Mirrors importItem(), but targets EE's Member
     * model. Members have no title/url_title/categories/comments, and are keyed
     * on username or email rather than a title.
     *
     * @param array $item
     * @return bool
     */
    public function importMember(array $item = [])
    {
        // Reset time out
        set_time_limit(60);

        $contentType = $this->getContentType();
        $customFields = $contentType->getCustomFields($this->settings);

        $this->tick('import_member');

        if ($this->checkMemory) {
            $this->checkMemoryUsage("Start");
        }

        // -------------------------------------------
        // 'datagrab_modify_data' hook.
        //  - Perform actions before the import is run
        //
        if ($this->extensions->active_hook('datagrab_modify_data') === true) {
            $this->logger->log('Calling datagrab_modify_data() hook.');
            $item = $this->extensions->call('datagrab_modify_data', $item);

            if ($this->extensions->end_script === true) {
                return false;
            }
        }
        //
        // -------------------------------------------

        $data = [];

        foreach (['username', 'email', 'screen_name'] as $property) {
            $mapping = $this->settings['config'][$property] ?? '';

            if ($mapping !== '') {
                $data[$property] = $this->dataType->get_item($item, $mapping);
            }
        }

        // Screen name is optional in the import config, but EE displays it
        // everywhere, so fall back to the username.
        if (empty($data['screen_name']) && !empty($data['username'])) {
            $data['screen_name'] = $data['username'];
        }

        $identifier = !empty($data['username']) ? $data['username'] : ($data['email'] ?? '');

        $this->logger->log(sprintf('--- Begin Importing Member [%s]', $identifier));

        if (empty($data['username']) && empty($data['email'])) {
            $this->logger->log('Skipping row: both username and email are empty.');
            $this->importStatus->incrementError();

            return true;
        }

        // Only assign a role from the import data. The configured default role is
        // applied to new members further down, so that re-running an import never
        // silently changes an existing member's role.
        $roleField = $this->settings['config']['member_role_field'] ?? '';

        if ($roleField !== '') {
            $resolvedRoleId = $this->resolveRoleId($this->dataType->get_item($item, $roleField));

            if ($resolvedRoleId) {
                $data['role_id'] = $resolvedRoleId;
            }
        }

        // The publish date offset is an entry-only option and is not shown for
        // member imports, so join dates are taken from the feed as-is.
        $joinDateField = $this->settings['config']['date'] ?? '';

        if ($joinDateField !== '') {
            $data['join_date'] = (int) $this->parseDate($this->dataType->get_item($item, $joinDateField));
        }

        // Check whether this member already exists
        $memberId = $this->isMemberUnique($data);

        $this->applyCustomFields($data, $item, $customFields, $memberId);

        // -------------------------------------------
        // 'datagrab_modify_data_end' hook.
        //  - Let other 3rd party developers modify the data array prior to saving.
        //
        if ($this->extensions->active_hook('datagrab_modify_data_end') === true) {
            $this->logger->log('Calling datagrab_modify_data_end() hook.');
            $data = $this->extensions->call('datagrab_modify_data_end', $this, $data, $item);
        }
        //
        // -------------------------------------------

        $this->applyFinalCustomFields($data, $item, $customFields, $memberId);

        // Disable notices while running this. Too many 3rd party fieldtypes giving notices
        $errorLevel = error_reporting();
        error_reporting($errorLevel & ~E_NOTICE);

        if (!$memberId) {
            $member = ee('Model')->make('Member');

            if (!isset($data['join_date'])) {
                $data['join_date'] = ee()->localize->now;
            }

            // Members require a password. Imports never carry one, so generate a
            // strong random value the member can reset via "forgot password".
            // The Member model validates the plaintext but does NOT hash it, so
            // hash before save() and never let the plaintext reach the database.
            $plaintextPassword = $this->generateMemberPassword();

            // exp_members.language has no database default, and EE's Member model
            // does not populate it, so an insert fails without this.
            if (!isset($data['language'])) {
                $data['language'] = ee()->config->item('deft_lang') ?: 'english';
            }

            if (!isset($data['ip_address'])) {
                $data['ip_address'] = '127.0.0.1';
            }

            if (!isset($data['role_id'])) {
                $defaultRole = $this->settings['config']['member_role']
                    ?? ee()->config->item('default_primary_role')
                    ?? 5;

                $data['role_id'] = (int) $defaultRole;
            }

            // Validate against the plaintext so EE's password rules apply...
            $data['password'] = $plaintextPassword;
            $member->set($data);

            $result = $member->validate();

            if ($result->isValid()) {
                // ...then replace it with a bcrypt hash so the plaintext never
                // reaches the database or the query log.
                ee()->load->library('auth');
                $hashed = ee()->auth->hash_password($plaintextPassword);

                $member->setProperty('password', $hashed['password']);
                $member->setProperty('salt', $hashed['salt']);

                $member->save();
                $memberId = $member->member_id;

                $this->logger->log('Member added');
                $this->importStatus->incrementAdded();
            } else {
                $this->logMemberErrors($result, $identifier);
                $this->importStatus->incrementError();
            }
        } else {
            if (get_bool_from_string($this->settings['config']['update'] ?? 'no')) {
                $this->logger->log(sprintf('[%s] already exists. Updating.', $identifier));

                $member = ee('Model')->get('Member', $memberId)->first();

                // Never overwrite an existing member's password or join date.
                unset($data['password'], $data['join_date']);

                $member->set($data);

                $result = $member->validate();

                if ($result->isValid()) {
                    $member->save();

                    $this->logger->log('Member updated');
                    $this->importStatus->incrementUpdated();
                } else {
                    $this->logMemberErrors($result, $identifier);
                    $this->importStatus->incrementError();
                }
            } else {
                $this->logger->log(sprintf('[%s] already exists. Skipping.', $identifier));
            }
        }

        error_reporting($errorLevel);

        if ($memberId) {
            // Never retain the generated plaintext password in memory that gets
            // passed to hooks or written to the log.
            unset($data['password']);

            $this->entryData[] = $data;
            $this->entries[] = (int) $memberId;

            $this->recordImportedEntryIds();
        }

        $this->tock('import_member');

        return true;
    }

    /**
     * Generate a random password that satisfies EE's configured minimum length.
     * Uses random_string(), which is backed by random_int()/random_bytes().
     */
    private function generateMemberPassword(): string
    {
        $minLength = (int) ee()->config->item('pw_min_len');
        $length = max($minLength, 24);

        return random_string('alnum', $length);
    }

    /**
     * Resolve a role name or id from the import feed into a role id.
     */
    private function resolveRoleId(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            $role = ee('Model')->get('Role', (int) $value)->first();

            return $role ? (int) $role->role_id : 0;
        }

        // Import feeds may carry either the display name or the short name.
        foreach (['name', 'short_name'] as $property) {
            $role = ee('Model')->get('Role')
                ->filter($property, $value)
                ->first();

            if ($role) {
                return (int) $role->role_id;
            }
        }

        return 0;
    }

    /**
     * Find an existing member using the configured unique fields. Members are
     * naturally keyed on username and email, so only those are considered.
     */
    public function isMemberUnique(array $data): int
    {
        $unique = $this->settings['config']['unique'] ?? '';

        if (is_array($unique)) {
            $unique = array_values(array_diff($unique, ['']));
        } elseif ($unique !== '') {
            $unique = [$unique];
        } else {
            $unique = [];
        }

        // Default to matching on whichever identifier was mapped.
        if (empty($unique)) {
            $unique = array_values(array_filter(['username', 'email'], function ($field) use ($data) {
                return !empty($data[$field]);
            }));
        }

        if (empty($unique)) {
            return 0;
        }

        $builder = ee('Model')->get('Member');

        foreach ($unique as $field) {
            if (!in_array($field, ['username', 'email', 'screen_name'], true) || empty($data[$field])) {
                continue;
            }

            $builder->filter($field, $data[$field]);
        }

        $member = $builder->first();

        return $member->member_id ?? 0;
    }

    private function logMemberErrors($result, string $identifier)
    {
        foreach ($result->getAllErrors() as $errorField => $error) {
            foreach ($error as $errorMessage) {
                $this->logger->log(sprintf(
                    'Error with member [%s] with field [%s]: %s',
                    $identifier,
                    $errorField,
                    $errorMessage
                ));
            }
        }
    }

    /**
     * @param array $item
     * @param array $customFields
     * @return bool
     */
    public function importItem(array $item = [])
    {
        // Reset time out
        set_time_limit(60);

        // Get custom fields from database
        $customFields = $this->fetchCustomFieldsFromChannel($this->settings['import']['channel']);

        $this->tick('import_item');

        if ($this->checkMemory) {
            $this->checkMemoryUsage("Start");
        }

        // -------------------------------------------
        // 'datagrab_modify_data' hook.
        //  - Perform actions before the import is run
        //
        if ($this->extensions->active_hook('datagrab_modify_data') === true) {
            $this->logger->log('Calling datagrab_modify_data() hook.');
            $item = $this->extensions->call('datagrab_modify_data', $item);

            if ($this->extensions->end_script === true) {
                return false;
            }
        }
        //
        // -------------------------------------------

        // Save a reference to the site_id value when this import executes. If it's an MSM site
        // and we're executing an import from another site, the config value will report incorrectly,
        // causing cascading issues where EE's internals reference ee()->config->item('site_id'), such
        // as system/ee/ExpressionEngine/Model/Channel/ChannelEntry.php->populateChannels()
        /** @var EE_Config $eeConfig */
        $eeConfig = ee()->config;
        $__siteId = $eeConfig->item('site_id');
        $siteId = $this->channelDefaults['site_id'] ?? ee()->config->item('site_id');
        $eeConfig->set_item('site_id', $siteId);

        // Initialise array to store entry data
        $data = [];

        // Get title
        $data["title"] = $this->dataType->get_item($item, $this->settings["config"]["title"]);
        $titleSuffix = $this->settings["config"]["title_suffix"] ?? '';

        if ($titleSuffix) {
            $data["title"] .= ' ' . $this->dataType->get_item($item, $titleSuffix);
        }

        $this->logger->log(sprintf('--- Begin Importing [%s]', $data['title']));

        // Get date
        $date = $this->settings['config']['date'] ?? '';
        $data['entry_date'] = $this->parseDate($this->dataType->get_item($item, $date));

        // Get date
        $expirationDate = $this->settings['config']['expiry_date'] ?? '';
        if ($this->dataType->get_item($item, $expirationDate) != '') {
            $data['expiration_date'] = $this->parseDate($this->dataType->get_item($item, $expirationDate));
        }

        // Get URL title
        $data['url_title'] = $data['title'];
        $urlTitleSuffix = $this->settings['config']['url_title_suffix'] ?? '';
        $urlTitlePrefix = $this->channelDefaults['url_title_prefix'] ?? '';

        if ($urlTitleSuffix) {
            $data['url_title'] .= ' ' . $this->dataType->get_item($item, $urlTitleSuffix);
        }

        if (isset($this->settings['config']['url_title'])) {
            // Get URL title from data source
            $urlTitle = $this->dataType->get_item($item, $this->settings['config']['url_title']);

            if ($urlTitle !== '') {
                $data['url_title'] = $urlTitle;
            }
        }

        $data['url_title'] = $this->prepareUrlTitle($data['url_title']);

        if ($urlTitlePrefix) {
            $data['url_title'] = $urlTitlePrefix . $data['url_title'];
        }

        // Possible fix for https://boldminded.com/support/ticket/2689
        if (!array_key_exists('ip_address', $data)) {
            $data['ip_address'] = '127.0.0.1';
        }

        // Check for duplicate entry
        if (!isset($this->settings['config']['unique'])) {
            $this->settings['config']['unique'] = '';
        }

        // Check whether this entry is a duplicate
        $entryId = $this->isEntryUnique($data, $item, $this->settings['config']['unique']);

        // Do entry_id field check here
        if (isset($this->settings['config']['ajw_entry_id']) && $this->settings['config']['ajw_entry_id'] != '') {
            // Check whether entry_id exists
            $itemEntryId = $this->dataType->get_item($item, $this->settings['config']['ajw_entry_id']);
            $this->db->select('entry_id');
            $this->db->where('entry_id', $itemEntryId);
            $this->db->where('channel_id', $this->channelDefaults['channel_id']);
            $query = $this->db->get('exp_channel_titles');
            if ($query->num_rows() > 0) {
                $entryId = $itemEntryId;
            }
        }

        // Loop over all custom fields in this channel
        $this->applyCustomFields($data, $item, $customFields, $entryId);

        // Set timestamp field if one is set
        if (isset($this->settings['config']['timestamp']) && $this->settings['config']['timestamp'] != '') {
            $fieldId = $customFields[$this->settings['config']['timestamp']]['id'];
            $data[$this->getContentType()->getCustomFieldPrefix() . $fieldId] = time();
        }

        // No of seconds to offset dates/times
        $timeOffset = $this->settings['config']['offset'] ?? 0;
        $data['entry_date'] += $timeOffset;

        // Fetch author id
        $authorId = $this->fetchAuthor($item);

        // -------------------------------------------
        // 'datagrab_modify_data_end' hook.
        //  - Let other 3rd party developers modify the data array prior to the Entry API handling it.
        //
        if ($this->extensions->active_hook('datagrab_modify_data_end') === true) {
            $this->logger->log('Calling datagrab_modify_data_end() hook.');
            $data = $this->extensions->call('datagrab_modify_data_end', $this, $data, $item);
        }
        //
        // -------------------------------------------

        // Do extra processing now we know if it is an update or not
        $this->applyFinalCustomFields($data, $item, $customFields, $entryId);

        $data['cp_call'] = true;

        // Disable notices while running this. Too many 3rd party fieldtypes giving notices
        $errorLevel = error_reporting();
        error_reporting($errorLevel & ~E_NOTICE);

        // If it is a new entry...
        if ($entryId === 0) {
            $this->handleThirdPartyAddons($data, $item, $customFields, 'create');

            $this->logger->log('[' . $data['title'] . '] is a new entry.');

            $data['url_title'] = $this->uniqueUrlTitle($data['url_title'], 0);
            $data['channel_id'] = $this->channelDefaults['channel_id'];
            $data['site_id'] = $siteId;
            $data['status'] = $this->fetchStatus($item, $data, 'create');
            $data['author_id'] = $authorId;
            $data['ping_servers'] = []; // API bug (http://expressionengine.com/bug_tracker/bug/14008/)
            $data['allow_comments'] = $this->channelDefaults['deft_comments'];

            // Find and create categories
            $entryCategories = $this->setupEntryCategories($item);
            $data['categories'] = $entryCategories;

            /** @var ChannelEntry $entry */
            $entry = ee('Model')->make('ChannelEntry');
            $entry->entry_id = 0;
            $entry->author_id = $authorId;
            $entry->sticky = "n";

            $channel = ee('Model')
                ->get('Channel', $this->channelDefaults['channel_id'])
                ->first();

            $entry->Channel = $channel;
            $entry->set($data);

            $result = $entry->validate();

            if ($result->isValid()) {
                $entry->save();

                $entryId = $entry->entry_id;

                $this->logger->log('Entry added');
                $this->importStatus->incrementAdded();

                if (
                    isset($this->settings["config"]["import_comments"]) &&
                    $this->settings["config"]["import_comments"] == "y"
                ) {
                    $totalComments = $this->importComments($entryId, $item);
                    if ($totalComments > 0) {
                        $this->logger->log('Added ' . $totalComments . ' comments.');
                    }
                }

                $data['entry_id'] = $entryId;
                $data['site_id'] = (!isset($data['site_id']) || !$data['site_id']) ? 1 : $data['site_id'];

                $metaData = $this->prepareMetaData($data);

                // -------------------------------------------
                // 'after_channel_entry_save' hook.
                //
                if ($this->extensions->active_hook('after_channel_entry_save') === true) {
                    $this->logger->log('Calling after_channel_entry_save() hook.');
                    $this->extensions->call('after_channel_entry_save', $entry, $metaData);
                }
                //
                // -------------------------------------------
            } else {
                $errors = $result->getAllErrors();
                foreach ($errors as $errorField => $error) {
                    foreach ($error as $errorMessage) {
                        $this->logger->log(sprintf(
                            'Error with entry [%s] with field [%s]: %s',
                            $data['title'],
                            $errorField,
                            $errorMessage
                        ));

                        if (bool_config_item('datagrab_detailed_log')) {
                            $this->logger->log(sprintf(
                                'Backtrace: %s',
                                $this->logger->getBacktrace(),
                            ));
                        }
                    }
                }

                $detailedErrors = $this->findErrorValues($entry->getValues());
                if (count($detailedErrors) > 0) {
                    $this->logger->log(sprintf(
                        'Error detail: %s',
                        implode(', ', $detailedErrors)
                    ));
                }

                $this->importStatus->incrementError();
            }
        } else {
            if (get_bool_from_string($this->settings['config']['update'])) {
                $this->logger->log(sprintf('[%s] already exists. Updating.', $data['title']));

                $this->handleThirdPartyAddons($data, $item, $customFields, 'update');
                $this->prepareUpdateData($entryId, $data, $customFields);

                $data['channel_id'] = (string)$this->channelDefaults['channel_id'];
                $data['status'] = $this->fetchStatus($item, $data, 'update');
                $data['author_id'] = $authorId;
                $data['ping_servers'] = [];
                $data['allow_comments'] = $this->channelDefaults['deft_comments'];

                if (
                    isset($this->settings['config']['update_edit_date']) &&
                    get_bool_from_string($this->settings['config']['update_edit_date'])
                ) {
                    $data['edit_date'] = ee()->localize->now;
                }

                // Find and create categories
                $entryCategories = $this->setupEntryCategories($item, $entryId);

                // Add entry to categories
                $data['categories'] = $entryCategories;

                if (
                    array_key_exists('transcribe', $this->addons->get_installed('modules')) &&
                    isset($data['transcribe__transcribe_related_entries'])
                ) {
                    // $data['transcribe__transcribe_related_entries'] = 'none';
                    $_POST['transcribe__transcribe_related_entries'] = 'none';
                }

                $entry = ee('Model')->get('ChannelEntry', $entryId)
                    ->with('Channel')
                    ->first();

                $entry->author_id = (int)$authorId;
                $entry->set($data);

                $result = $entry->validate();

                if ($result->isValid()) {
                    $entry->save();

                    $this->logger->log('Entry updated.');
                    $this->importStatus->incrementUpdated();

                    $data['entry_id'] = $entryId;
                    $data['site_id'] = (!isset($data['site_id']) || !$data['site_id']) ? 1 : $data['site_id'];

                    // $data = $this->prepareMetaData($data);

                    // -------------------------------------------
                    // 'after_channel_entry_save' hook.
                    //
                    if ($this->extensions->active_hook('after_channel_entry_save') === true) {
                        $this->logger->log('Calling after_channel_entry_save() hook.');
                        $entryData = array_merge($entry->getValues(), $data);
                        $this->extensions->call('after_channel_entry_save', $entry, $entryData);
                    }
                    //
                    // -------------------------------------------
                } else {
                    $errors = $result->getAllErrors();
                    foreach ($errors as $errorField => $error) {
                        foreach ($error as $errorMessage) {
                            $this->logger->log(sprintf(
                                'Error with entry [%s] with field [%s]: %s',
                                $data['title'],
                                $errorField,
                                $errorMessage
                            ));

                            if (bool_config_item('datagrab_detailed_log')) {
                                $this->logger->log(sprintf(
                                    'Backtrace: %s',
                                    $this->logger->getBacktrace(),
                                ));
                            }
                        }
                    }
                    $detailedErrors = $this->findErrorValues($entry->getValues());
                    if (count($detailedErrors) > 0) {
                        $this->logger->log(sprintf(
                            'Error detail: %s',
                            implode(', ', $detailedErrors)
                        ));
                    }

                    $this->importStatus->incrementError();
                }
            } else {
                $this->logger->log(sprintf('[%s] already exists. Skipping entry per import setting.', $data['title']));
            }
        }

        error_reporting($errorLevel);

        $this->entryData[] = $data;
        $this->entries[] = (int) $entryId;

        // Do extra processing now we know if it is an update or not
        foreach ($customFields as $field => $field_data) {
            $fieldValue = $this->settings['cf'][$field]['value'] ?? '';
            $handler = $this->loader->loadFieldTypeHandler($field_data['type']);

            if ($handler && method_exists($handler, 'hasFieldValue')) {
                $fieldValue = $handler->hasFieldValue($this->settings['cf'][$field] ?? []);
            }

            // Should we import anything into this field?
            if (
                $fieldValue !== '' &&
                $handler &&
                method_exists($handler, 'postProcessEntry')
            ) {
                $data[sprintf('field_id_%s', $field_data['id'])] = $handler->postProcessEntry(new ImportField(
                    importer: $this,
                    importItem: $item,
                    propertyName: $fieldValue, // complex types will not have a value
                    propertyValue: $this->dataType->get_item($item, $fieldValue),
                    fieldImportConfig: $this->settings['cf'][$field],
                    fieldSettings: $this->getCustomFieldSettings($field),
                    entryId: $entryId,
                    fieldName: $field,
                    fieldId: $field_data['id'],
                ), $data);
            }
        }

        // Add the imported entries to the log so we have a running history of all entries imported
        // during a batch or for the entire import.
        $this->recordImportedEntryIds();

        // Set the site_id back to the original value.
        $eeConfig->set_item('site_id', $__siteId);

        $executionTime = $this->tock('import_item');

        $this->logger->log(sprintf('--- Import of [%s] took %s seconds.', $data['title'], round($executionTime, 2)));

        return true;
    }

    public function getCustomFieldSettings(string $fieldName): array
    {
        return $this->getContentType()->getCustomFieldSettings($fieldName);
    }

    /**
     * Maps import values into the custom field columns for the current content
     * type. Shared by the entry and member import paths — the only difference
     * between them is the column prefix (field_id_ vs m_field_id_).
     *
     * @param array $data Passed by reference, mutated in place.
     */
    private function applyCustomFields(array &$data, array $item, array $customFields, int $recordId = 0)
    {
        $prefix = $this->getContentType()->getCustomFieldPrefix();

        foreach ($customFields as $field => $field_data) {
            $fieldValue = $this->settings['cf'][$field]['value'] ?? '';
            $handler = $this->loader->loadFieldTypeHandler($field_data['type']);

            if ($handler && method_exists($handler, 'hasFieldValue')) {
                $fieldValue = $handler->hasFieldValue($this->settings['cf'][$field] ?? []);
            }

            // Should we import anything into this field?
            if ($fieldValue !== '') {
                if ($handler && method_exists($handler, 'preparePostData')) {
                    $data[$prefix . $field_data['id']] = $handler->preparePostData(new ImportField(
                        importer: $this,
                        importItem: $item,
                        propertyName: $fieldValue, // complex types will not have a value
                        propertyValue: $this->dataType->get_item($item, $fieldValue),
                        fieldImportConfig: $this->settings['cf'][$field],
                        fieldSettings: $this->getCustomFieldSettings($field),
                        entryId: $recordId,
                        fieldName: $field,
                        fieldId: $field_data['id'],
                    ));
                } else {
                    // If no handler exists, just use the value straight from the import feed
                    $data[$prefix . $field_data['id']] = $this->dataType->get_item(
                        $item,
                        $fieldValue
                    );
                }

                $data['field_ft_' . $field_data['id']] = $field_data['format'];
            }
        }
    }

    /**
     * Second pass over the custom fields, once we know whether the record is
     * new or an update.
     *
     * @param array $data Passed by reference, mutated in place.
     */
    private function applyFinalCustomFields(array &$data, array $item, array $customFields, int $recordId = 0)
    {
        $prefix = $this->getContentType()->getCustomFieldPrefix();

        foreach ($customFields as $field => $field_data) {
            $fieldValue = $this->settings['cf'][$field]['value'] ?? '';
            $handler = $this->loader->loadFieldTypeHandler($field_data['type']);

            if ($handler && method_exists($handler, 'hasFieldValue')) {
                $fieldValue = $handler->hasFieldValue($this->settings['cf'][$field] ?? []);
            }

            // Should we import anything into this field?
            if (
                $fieldValue !== '' &&
                $handler &&
                method_exists($handler, 'finalPostData')
            ) {
                $data[$prefix . $field_data['id']] = $handler->finalPostData(new ImportField(
                    importer: $this,
                    importItem: $item,
                    propertyName: $fieldValue, // complex types such as Bloqs and Fluid do not use $fieldValue
                    propertyValue: $this->dataType->get_item($item, $fieldValue),
                    fieldImportConfig: $this->settings['cf'][$field],
                    fieldSettings: $this->getCustomFieldSettings($field),
                    entryId: $recordId,
                    fieldName: $field,
                    fieldId: $field_data['id'],
                ), $data);
            }
        }
    }

    private function findErrorValues(array $array): array {
        $errors = [];

        array_walk_recursive($array, function ($value, $key) use (&$errors) {
            if (str_ends_with($key, '_error')) {
                $errors[] = $value;
            }
        });

        return array_unique($errors);
    }

    /**
     * @param array $data
     * @param array $item
     * @param array $customFields
     */
    private function handleThirdPartyAddons(array &$data, array $item, array $customFields = [], string $action = '')
    {
        $handlers = $this->loader->fetchModuleHandlers();

        foreach ($handlers as $handler) {
            $handler
                ->setSettings($this->settings)
                ->handle($this, $data, $item, $customFields, $action);
        }
    }

    /**
     * Borrowed from AlphaDashPeriodEmoji.php validator and modified.
     * Allow emojis, strip # and prevent repeated dashes and underscores.
     *
     * @param string $urlTitle
     * @return string
     */
    private function prepareUrlTitle(string $urlTitle): string
    {
        $urlTitle = ee('Format')->make('Text', $urlTitle)->urlSlug()->compile();

        // Strip emojis
        $regex = '/(?:' . ee('Emoji')->emojiRegex . ')/u';
        $emojiless = preg_replace($regex, '', $urlTitle);

        // If the only value we were given were emoji(s) then it's valid
        if (strlen($urlTitle) > 0 && strlen($emojiless) < 1) {
            return $urlTitle;
        }

        // $urlTitle = preg_replace("/^([-a-z0-9_.-])+$/i", '', $emojiless);
        $urlTitle = str_replace('#', '', $urlTitle);
        $urlTitle = preg_replace('/-{1,}/', '-', $urlTitle);
        $urlTitle = preg_replace('/_{1,}/', '_', $urlTitle);

        return $urlTitle;
    }

    /**
     * @param array $entryData
     * @return array
     */
    private function prepareMetaData(array $entryData = []): array
    {
        $metaData = [];

        if (isset($entryData['author_id'])) {
            $metaData['author_id'] = $entryData['author_id'];
        }

        if (isset($entryData['site_id'])) {
            $metaData['site_id'] = $entryData['site_id'];
        } else {
            $metaData['site_id'] = 1;
        }

        return $metaData;
    }

    /**
     * Get an array of custom field data for a selected channel
     *
     * @param string $channelId
     * @return array $customFields array containing custom field data for selected channel
     */
    public function fetchCustomFieldsFromChannel($channelId): array
    {
        $channel = ee('Model')->get('Channel', (int) $channelId)->first();

        $customFields = [];
        foreach ($channel->getAllCustomFields() as $row) {
            $customFields[$row->field_name]['id'] = $row->field_id;
            $customFields[$row->field_name]['format'] = $row->field_fmt;
            $customFields[$row->field_name]['type'] = $row->field_type;
            $customFields[$row->field_name]['name'] = $row->field_name;
        }

        return $customFields;
    }

    /**
     * Get default settings for a channel
     *
     * @param int $channelId
     * @return array $channel_default array of channel default settings
     */
    public function fetchChannelDefaults($channelId): array
    {
        $siteId = $this->settings['import']['site_id'] ?? ee()->config->item('site_id');

        $channel = ee('Model')
            ->get('Channel', (int) $channelId)
            ->filter('site_id', $siteId)
            ->first();

        return [
            'channel_id' => $channel->channel_id,
            'site_id' => $channel->site_id,
            'channel_title' => $channel->channel_title,
            'deft_comments' => $channel->deft_comments,
            'deft_status' => $channel->deft_status,
            'url_title_prefix' => $channel->url_title_prefix
        ];
    }

    /**
     * Check to see if the current user is logged in with the privileges to perform import,
     * if not create a fake user (used when import is not run from the Control Panel)
     *
     */
    public function checkMemberStatus()
    {
        // If not currently logged in, create a fake session
        $this->currentUser = $this->session->userdata['member_id'] ?? 0;
        $defaultAuthorId = $this->settings["config"]["author"] ?? null;

        if (!$defaultAuthorId) {
            $this->logger->log('Attempting to create a new session but no Default Author is assigned.');
        }

        if (!$this->currentUser && $defaultAuthorId) {
            $this->session->create_new_session($defaultAuthorId, true);
            $this->session->userdata['group_id'] = 1;
            $this->session->userdata['can_edit_other_entries'] = 'y';
            $this->session->userdata['can_delete_self_entries'] = 'y';
            $this->session->userdata['can_delete_all_entries'] = 'y';
        }
    }

    /**
     * Find which author to assign to this entry
     *
     * @param array $item current row of data from data source
     * @return integer $author_id the id of the author to assign to this entry
     */
    public function fetchAuthor($item): int
    {
        // Default author
        $author_id = $this->settings['config']['author'];

        // Data field that contains author information
        $author_field = $this->settings['config']['author_field'] ?? '';

        // Which field to check: screen name, username, email?
        $author_check = $this->settings['config']['author_check'] ?? '';

        if ($author_check == 'member_id') {
            $author_check = 'exp_members.member_id';
        }

        // Get author id from data if specified
        if ($author_field != '' && $author_check != '') {
            $this->db->select('exp_members.member_id');
            $this->db->from('exp_members');
            $this->db->join('exp_member_data', 'exp_members.member_id = exp_member_data.member_id');
            $this->db->where($author_check, $this->dataType->get_item($item, $author_field));
            $query = $this->db->get();
            if ($query->num_rows() > 0) {
                $row = $query->row_array();
                $author_id = $row['member_id'];
            }
        }

        return (int) $author_id;
    }

    /**
     * Find status to assign to this entry
     *
     * @param array $item current row of data from data source
     * @param array $data
     * @param string $action
     * @return string $status status to assign to entry
     */
    public function fetchStatus(array $item, array $data, string $action): string
    {
        // -------------------------------------------
        // 'datagrab_fetch_status' hook.
        //  - Perform actions after the import is run
        //
        if ($this->extensions->active_hook('datagrab_fetch_status') === true) {
            $this->logger->log('Calling datagrab_fetch_status() hook.');
            $status = $this->extensions->call('datagrab_fetch_status', $this, $item);

            if ($status) {
                return $status;
            }
        }
        //
        // -------------------------------------------

        $updateStatus = $this->settings['config']['update_status'] ?? '';
        $status = $this->channelDefaults["deft_status"];

        // https://boldminded.com/support/ticket/2526
        // https://boldminded.com/support/ticket/2560
        // If we're updating an existing entry but the status setting should only set the status when creating a
        // new entry, use the status that is already assigned to the entry, or use the default status as a fallback.
        if ($action === 'update' && $updateStatus === 'create') {
            return $data['status'] ?? $status;
        }

        $entryStatusSetting = $this->settings['config']['entry_status'] ??
            $this->settings['config']['status'] ?? '';

        if ($entryStatusSetting) {
            switch ($entryStatusSetting) {
                case 'default':
                    // We set our default status above
                    break;
                case 'open':
                case 'closed':
                    $status = $this->settings['config']['status'];
                    break;
                default:
                    $status = $this->dataType->get_item($item, $entryStatusSetting);
            }

            // If someone used a custom field with the capitalized versions, correct it.
            // Internally these statuses should always be lowercase.
            if (in_array(strtolower($status), ['open', 'closed'])) {
                $status = strtolower($status);
            }

            $channel = ee('Model')->get('Channel', $this->settings['import']['channel'])->first();

            // fetch valid settings from db
            if (!is_array($this->validStatuses)) {
                $this->validStatuses = [];
                foreach ($channel->Statuses as $row) {
                    $this->validStatuses[$row->status] = $row->status;
                }
            }

            // check id setting is a valid custom status for this channel
            if (in_array($entryStatusSetting, $this->validStatuses)) {
                $status = $entryStatusSetting;
            }
        }

        return $status;
    }

    /**
     * Pipe delimited list of category groups assigned to the current import
     *
     * @return string
     */
    private function getCategoryGroups(): string
    {
        return $this->settings['config']['c_groups'] ?? '';
    }

    /**
     * Find a list of categories to assign to this entry and create any that don't exist
     *
     * @param array $item current row of data from data source
     * @return array $entry_categories list of category ids
     */
    private function setupEntryCategories(array $item, $entry_id = false): array
    {
        $c_groups = $this->getCategoryGroups();
        $entry_categories = [];

        if (!$c_groups) {
            return $entry_categories;
        }

        // Loop over all category groups for this channel
        $used_groups = [];
        foreach (explode("|", $c_groups) as $cat_group_id) {
            // Find categories from custom field and create if necessary
            $cat_field = '';
            if (isset($this->settings['config']['cat_field_' . $cat_group_id]) && $this->settings['config']['cat_field_' . $cat_group_id] != '') {

                // todo: handle repeating elements
                $cat_field = $this->dataType->get_item($item, $this->settings['config']['cat_field_' . $cat_group_id]);

                $used_groups[] = $cat_group_id; // Make note of which category groups are used
            }

            // Find existing category id's and create new categories if necessary
            $new = $this->findAndCreateCategories(
                $this->settings['config']['cat_default_' . $cat_group_id] ?? '',
                $cat_field,
                $cat_group_id,
                $this->channelDefaults['site_id']
            );

            if (count($entry_categories) || count($new)) {
                if (isset($entry_categories['cat_group_id_' . $cat_group_id])) {
                    $entry_categories['cat_group_id_' . $cat_group_id] = array_merge($entry_categories['cat_group_id_' . $cat_group_id], $new);
                } else {
                    $entry_categories['cat_group_id_' . $cat_group_id] = $new;
                }
            }
        }

        if ($entry_id !== false) {
            // Doing an update, so find existing categories
            $this->db->select('exp_category_posts.cat_id, exp_categories.group_id');
            $this->db->where('entry_id', $entry_id);
            if (!empty($used_groups)) {
                $this->db->where_not_in('group_id', $used_groups);
            }
            $this->db->join('exp_categories', 'exp_category_posts.cat_id = exp_categories.cat_id');
            $query = $this->db->get('exp_category_posts');
            foreach ($query->result_array() as $row) {
                $entry_categories['cat_group_id_' . $row['group_id']][] = $row['cat_id'];
            }

        }

        return $entry_categories;
    }

    /**
     * Find a list of categories to assign to this file and create any that don't exist
     *
     * @param array $item current row of data from data source
     * @return array $entry_categories list of category ids
     */
    private function setupFileCategories(array $item, $fileId = false): array
    {
        $c_groups = $this->getCategoryGroups();
        $fileCategories = [];

        if (!$c_groups) {
            return $fileCategories;
        }

        // Loop over all category groups for this channel
        $used_groups = [];
        foreach (explode("|", $c_groups) as $cat_group_id) {
            // Find categories from custom field and create if necessary
            $cat_field = '';
            if (isset($this->settings['config']['cat_field_' . $cat_group_id]) && $this->settings['config']['cat_field_' . $cat_group_id] != '') {

                // todo: handle repeating elements
                $cat_field = $this->dataType->get_item($item, $this->settings['config']['cat_field_' . $cat_group_id]);

                $used_groups[] = $cat_group_id; // Make note of which category groups are used
            }

            // Find existing category id's and create new categories if necessary
            $new = $this->findAndCreateCategories(
                $this->settings['config']['cat_default_' . $cat_group_id] ?? '',
                $cat_field,
                $cat_group_id,
                $this->channelDefaults['site_id']
            );

            if (count($fileCategories) || count($new)) {
                if (isset($fileCategories['cat_group_id_' . $cat_group_id])) {
                    $fileCategories['cat_group_id_' . $cat_group_id] = array_merge($fileCategories['cat_group_id_' . $cat_group_id], $new);
                } else {
                    $fileCategories['cat_group_id_' . $cat_group_id] = $new;
                }
            }
        }

        if ($fileId !== false) {
            // Doing an update, so find existing categories
            $this->db->select('exp_file_categories.cat_id, exp_categories.group_id');
            $this->db->where('file_id', $fileId);
            if (!empty($used_groups)) {
                $this->db->where_not_in('group_id', $used_groups);
            }
            $this->db->join('exp_categories', 'exp_file_categories.cat_id = exp_categories.cat_id');
            $query = $this->db->get('exp_file_categories');
            foreach ($query->result_array() as $row) {
                $fileCategories['cat_group_id_' . $row['group_id']][] = $row['cat_id'];
            }
        }

        return $fileCategories;
    }

    /**
     * Import comments
     *
     * @param integer $entry_id id of the new entry
     * @param array   $item     current row of data from data source
     * @return integer the number of comments added
     */
    private function importComments($entry_id, $item): int
    {
        // @note: xml only at the moment?
        // @todo: add more error checking here, eg, missing/empty fields
        // @todo: only works for new imports, not updates at the moment

        // Are there any comments for this entry?
        $numberComments = $this->dataType->get_item($item, $this->settings["config"]["comment_body"] . "#");

        if ($numberComments > 0) {
            // If so, loop over the XML and insert as new comments
            for ($i = 0; $i < $numberComments; $i++) {
                if ($i > 0) {
                    $suffix = '#' . ($i + 1);
                } else {
                    $suffix = '';
                }

                $name = $this->dataType->get_item($item, $this->settings["config"]["comment_author"] . $suffix);
                if (is_array($name)) {
                    $name = '';
                }

                $data = array(
                    "site_id" => $this->channelDefaults["site_id"],
                    "entry_id" => $entry_id,
                    "channel_id" => $this->channelDefaults["channel_id"],
                    "author_id" => 0,
                    "status" => "o",
                    "name" => $name,
                    "email" => $this->dataType->get_item($item, $this->settings["config"]["comment_email"] . $suffix),
                    "url" => $this->dataType->get_item($item, $this->settings["config"]["comment_url"] . $suffix),
                    "location" => "",
                    "ip_address" => "127.0.0.1",
                    "comment_date" => $this->parseDate(
                        $this->dataType->get_item($item, $this->settings["config"]["comment_date"] . $suffix)
                    ),
                    "comment" => $this->dataType->get_item($item, $this->settings["config"]["comment_body"] . $suffix)
                );
                $sql = $this->db->insert_string('exp_comments', $data);
                $this->db->query($sql);
            }

            // Do stats
            $this->db->select("COUNT(comment_id) as count");
            $this->db->where("status", "o");
            $this->db->where("entry_id", $entry_id);
            $channel_comments_count = $this->db->get("exp_comments");

            $this->db->select("MAX(comment_date) as date");
            $this->db->where("status", "o");
            $this->db->where("entry_id", $entry_id);
            $this->db->order_by("comment_date", "desc");
            $channel_comments_recent = $this->db->get("exp_comments");

            $data = [];

            if ($channel_comments_count->num_rows() > 0) {
                $row = $channel_comments_count->row_array();
                $data["comment_total"] = $row["count"];
            }

            if ($channel_comments_recent->num_rows() > 0) {
                $row = $channel_comments_recent->row_array();
                $data["recent_comment_date"] = $row["date"];
            }

            if (!empty($data)) {
                $this->db->where('entry_id', $entry_id);
                $this->db->update('exp_channel_titles', $data);
            }

        }

        return $numberComments;
    }

    /**
     * Delete entries with a timestamp older than specified
     *
     * @param string $timestamp
     * @param string $duration
     * @return void
     * @author BoldMinded, LLC
     */
    public function deleteByTimestamp($timestamp, $field, int $duration = 86400): int
    {
        // Currently disabled
        return 0;
    }

    /**
     * Try to read the date and return as timestamp
     *
     * @param string $dateString
     * @return int the date
     */
    public function parseDate(string $dateString = '')
    {
        if (is_numeric($dateString)) {
            return $dateString;
        }

        $date = strtotime($dateString);

        if ($date == -1) {
            $date = parse_w3cdtf($dateString);
        }

        if ($date == -1 || $date == '') {
            $date = time();
        }

        return ($date);
    }

    /**
     * Test whether an entry is unique
     * If no unique field is provided, always create a new entry
     *
     * @param array $data
     * @param array $unique
     * @return int entry_id or 0 if there is no match
     */
    public function isEntryUnique(array $data, array $item, array $unique = []): int
    {
        if (is_array($unique)) {
            // Remove empty elements
            $unique = array_diff($unique, ['']);
            if (empty($unique)) {
                return 0;
            }
        }

        if ($unique == '') {
            return 0;
        }

        $unique = array_values($unique);
        $defaultFields = ['title', 'url_title'];
        $containsDefaults = array_intersect($defaultFields, $unique);
        $fieldMatches = [];

        foreach ($containsDefaults as $defaultField) {
            $fieldMatches[$defaultField] = $data[$defaultField];
        }

        $customFields = array_diff($unique, $containsDefaults);

        foreach ($customFields as $customField) {
            $fieldValue = $this->settings['cf'][$customField]['value'] ?? '';

            $uniqueValue = $this->dataType->get_item(
                $item,
                $fieldValue
            );

            $field = ee('Model')->get('ChannelField')
                ->search('field_name', $customField)
                ->first();

            $fieldName = 'field_id_' . $field->field_id;

            $fieldMatches[$fieldName] = $uniqueValue;
        }

        $builder = ee('Model')
            ->get('ChannelEntry')
            ->filter('channel_id', $this->channelDefaults['channel_id']);

        foreach ($fieldMatches as $fieldName => $fieldValue) {
            $builder->filter($fieldName, $fieldValue);
        }

        $entry = $builder->first();

        return $entry->entry_id ?? 0;
    }

    /**
     * Taken from legacy Api class and cleaned up
     *
     * @param string $urlTitle
     * @param int $selfEntryId
     * @return false|string
     */
    protected function uniqueUrlTitle(string $urlTitle, int $selfEntryId)
    {
        // Field is limited to 75 characters, so trim url_title before querying
        $urlTitle = substr($urlTitle, 0, 200);
        $channelId = $this->channelDefaults['channel_id'];

        $count = ee()->db
            ->where([
                'entry_id !=' => $selfEntryId,
                'url_title' => $urlTitle,
                'channel_id' => $channelId
            ])
            ->count_all_results('channel_titles');

        if ($count == 0) {
            return $urlTitle;
        }

        // We may need some room to add our numbers - trim url_title to 195 characters
        if (strlen($urlTitle) > 195) {
            $urlTitle = substr($urlTitle, 0, 195);

            // Check again
            if ($selfEntryId != '') {
                ee()->db->where(['entry_id' . ' !=' => $selfEntryId]);
            }

            ee()->db->where(['url_title' => $urlTitle, 'channel_id' => $channelId]);
            $count = ee()->db->count_all_results('channel_titles');
        }

        while ($count > 0) {
            if ($selfEntryId != '') {
                ee()->db->where(['entry_id' . ' !=' => $selfEntryId]);
            }

            ee()->db->select("url_title, MID(url_title, " . (strlen($urlTitle) + 1) . ") + 1 AS next_suffix", false);
            ee()->db->where("url_title LIKE '" . preg_quote(ee()->db->escape_str($urlTitle)) . "%'");
            ee()->db->where("url_title REGEXP('^" . preg_quote(ee()->db->escape_str($urlTitle)) . "[0-9]*$')");
            ee()->db->where(['channel_id' => $channelId]);
            ee()->db->order_by('next_suffix', 'DESC');
            ee()->db->limit(1);

            $query = ee()->db->get('channel_titles');

            // If no records found, we likely had to shorten the URL title (below)
            // to give more space for numbers; so, we'll start the counting back
            // at 1 since we don't necessarily know where we left off with the old
            // URL title
            $urlTitleSuffix = (!is_array($query->row('next_suffix'))) ? (int) $query->row('next_suffix') : 1;

            // Is the appended number going to kick us over the 200 character limit?
            // If so, shorten it by one more character and try again
            if (strlen($urlTitle . $urlTitleSuffix) > 200) {
                $urlTitle = substr($urlTitle, 0, strlen($urlTitle) - 1);

                continue;
            }

            $urlTitle = $urlTitle . $urlTitleSuffix;

            // little double check for safety
            if ($selfEntryId != '') {
                ee()->db->where(['entry_id !=' => $selfEntryId]);
            }

            ee()->db->where([
                'url_title' => $urlTitle,
                'channel_id' => $channelId
            ]);

            $count = ee()->db->count_all_results('channel_titles');

            if ($count > 0) {
                return false;
            }
        }

        return $urlTitle;
    }

    /**
     * Create a list of categories for an entry
     *
     * @param string $category          Contains default category name or id
     * @param string $categoryField     Contains category values or ids
     * @param string $categoryGroupId   Category group id
     * @param string $siteId            Site id
     * @return array                    category ids to add to entry
     * @author BoldMinded, LLC
     */
    public function findAndCreateCategories($category, $categoryField, $categoryGroupId, $siteId): array
    {
        $entryCategories = [];
        $allowNumericNames = get_bool_from_string($this->settings['config']['cat_allow_numeric_names_' . $categoryGroupId] ?? 0);

        if ($category != '') {
            if (is_numeric($category) && !$allowNumericNames) {
                $entryCategories[] = $category;
            } else {
                $entryCategories[] = $this->createCategory($category, $categoryGroupId, $siteId);
            }
        }

        // Split category field by delimiter to find multiple categories
        $catDelimiter = '';
        $catSubDelimiter = '/';

        if (
            isset($this->settings['config']['cat_delimiter_' . $categoryGroupId]) &&
            $this->settings['config']['cat_delimiter_' . $categoryGroupId] != ''
        ) {
            $catDelimiter = $this->settings['config']['cat_delimiter_' . $categoryGroupId];
        }

        if (
            isset($this->settings['config']['cat_sub_delimiter_' . $categoryGroupId]) &&
            $this->settings['config']['cat_sub_delimiter_' . $categoryGroupId] != ''
        ) {
            $catSubDelimiter = $this->settings['config']['cat_sub_delimiter_' . $categoryGroupId];
        }

        if ($categoryField != '') {
            if ($catDelimiter != "") {
                $cats = explode($catDelimiter, $categoryField);
            } else {
                $cats = [$categoryField];
            }

            // Remove duplicates (after trimming whitespace)
            foreach ($cats as $idx => $cat) {
                $cats[$idx] = trim($cat);
            }
            $cats = array_unique($cats);

            // Add category to database
            foreach ($cats as $cat) {
                if (is_numeric($cat) && !$allowNumericNames) {
                    $entryCategories[] = $cat;
                } else {
                    $deliminatedCats = explode($catSubDelimiter, $cat);
                    $parentId = 0;

                    if (count($deliminatedCats) == 1) {
                        $entryCategories[] = $this->createCategory($cat, $categoryGroupId, $siteId);
                    }

                    foreach ($deliminatedCats as $dcat) {
                        $categoryId = $this->createCategory($dcat, $categoryGroupId, $siteId, $parentId);
                        $entryCategories[] = $categoryId;
                        $parentId = $categoryId;
                    }
                }
            }
        }

        return array_unique($entryCategories);
    }

    /**
     * @param string $category_name
     * @param string $category_group
     * @param int    $site_id
     * @param int    $parent_id
     * @return int
     */
    public function createCategory(string $category_name, string $category_group = '', int $site_id = 1, int $parent_id = 0): int
    {
        // Does this category already exist?
        $category_name = trim($category_name);

        $this->db->select("*");
        $this->db->where("cat_name", $category_name);
        $this->db->where("group_id", $category_group);
        if ($parent_id != 0) {
            $this->db->where("parent_id", $parent_id);
        }
        $query = $this->db->get("exp_categories");

        if ($query->num_rows == 0) {
            // Category does not exist so create it
            // todo: use Category model here

            $insert_array = array(
                'group_id' => $category_group,
                'site_id' => $site_id,
                'cat_name' => $category_name,
                'cat_url_title' => $this->prepareUrlTitle($category_name),
                'cat_image' => '',
                'parent_id' => $parent_id,
                'cat_order' => 1
            );

            $this->db->query($this->db->insert_string('exp_categories', $insert_array));
            $category_id = $this->db->insert_id();

            $insert_array = array(
                'cat_id' => $category_id,
                'site_id' => $site_id,
                'group_id' => $category_group
            );
            $this->db->query($this->db->insert_string('exp_category_field_data', $insert_array));

            $this->logger->log(sprintf('Created category %s in group %s', $category_name, $category_group));

            return $category_id;

        }

        // Category already exists, so return its id
        $row = $query->row();

        return $row->cat_id;
    }

    /**
     * Add categories to an entry
     *
     * @param string $entry_id
     * @param string $cat_id
     * @return void
     */
    public function addEntryToCategory($entry_id, $cat_id)
    {
        $this->db->query("INSERT IGNORE INTO exp_category_posts (entry_id, cat_id) VALUES ('" . $entry_id . "', '" . $cat_id . "')");
        if (bool_config_item('auto_assign_cat_parents')) {
            $query = $this->db->query("SELECT parent_id FROM exp_categories WHERE cat_id = " . $cat_id);
            $row = $query->row();
            if ($row->parent_id != 0) {
                $this->addEntryToCategory($entry_id, $row->parent_id);
            }
        }
    }

    public function prepareUpdateData($entry_id, &$data, $customFields)
    {
        $entry = ee('Model')->get('ChannelEntry', $entry_id)
            ->with('Channel')
            ->first();

        $entryData = $entry->getValues();

        if (!isset($data['title']) || $data['title'] == '') {
            $data['title'] = $entryData['title'];
        }

        if (!isset($this->settings['config']['date']) || $this->settings['config']['date'] === '') {
            $data['entry_date'] = $entryData['entry_date'];
        }

        // If URL Title is not defined in the import config, pull it from the existing entry
        // Otherwise data['url_title'] should have been set near the beginning of the process
        if (!isset($this->settings['config']['url_title']) || $this->settings['config']['url_title'] === '') {
            $data['url_title'] = $entryData['url_title'];
        } else {
            $data['url_title'] = $this->uniqueUrlTitle($data['url_title'], $entry_id);
        }

        $status = $this->settings['config']['status'] ?? 'default';
        if ($status === 'default') {
            $data['status'] = $entryData['status'];
        }

        if (!isset($this->settings['config']['author_field']) || $this->settings['config']['author_field'] === '') {
            $data['author_id'] = $entryData['author_id'];
        }

        foreach ($customFields as $field) {
            if (
                !isset($data['field_id_' . $field['id']])
                // finalPostData can return null to trigger this. Since we don't update
                // $data by reference anymore, we can't fully unset a field from the array.
                || $data['field_id_' . $field['id']] === null
            ) {
                $fieldValue = '';
                $handler = $this->loader->loadFieldTypeHandler($field['type']);

                if ($handler && method_exists($handler, 'hasFieldValue')) {
                    $fieldValue = $handler->hasFieldValue($this->settings['cf'][$field['name']] ?? []);
                }

                // If no import setting value is defined, skip this field.
                // This will allow existing data to remain unchanged.
                if ($fieldValue == '') {
                    continue;
                }

                if (
                    $handler
                    && method_exists($handler, 'rebuildPostData')
                ) {
                    $data['field_id_' . $field['id']] = $handler->rebuildPostData(
                        $this,
                        $field['id'],
                        $data,
                        $entryData,
                    );
                } elseif (isset($entryData['field_id_' . $field['id']])) {
                    // If no handler exists, just use the value
                    $data['field_id_' . $field['id']] = $entryData['field_id_' . $field['id']];
                }
            }
        }
    }

    /**
     * @param string    $filename
     * @param int|string|float $fileDir
     * @param bool      $fetchUrl
     * @param bool      $createSubDirs
     * @return string
     */
    public function getFile(
        string $filename,
        int|float|string $fileDir = 1,
        bool $fetchUrl = false,
        bool $createSubDirs = false,
        bool $replaceExisting = true,
        FileMeta|null $fileMeta = null
    ): string {
        if (!$fileDir || !$filename) {
            if ($filename && !$fileDir) {
                $this->logger->log(sprintf('Could not import %s, no import directory defined.', $filename));
            } elseif (!$filename && $fileDir) {
                $this->logger->log('Could not import file, no filename provided.');
            }

            return '';
        }

        $file = new File(
            $filename,
            $fileDir,
            $fetchUrl,
            $createSubDirs,
            $this->logger,
            $replaceExisting,
            $fileMeta
        );

        $fileResult = $file->fetch();

        if ($fileResult->fileVar) {
            $this->importedFileVars[$fileResult->fileVar] = $fileResult->isNew;
            $this->importedFilePaths[$fileResult->fileVar] = $fileResult->filePath;
        }

        return $fileResult->fileVar;
    }

    /**
     * @param string $label
     * @param bool   $display
     * @return void
     */
    public function checkMemoryUsage(string $label, bool $display = true)
    {
        $mem_usage = memory_get_usage();
        if ($display) {
            $this->template->log_item('DataGrab: ' . $label . ": " . $mem_usage . " (" . number_format($mem_usage - $this->memoryUsage, 0, '.', ',') . ")");
        }
        $this->memoryUsage = $mem_usage;
    }

    /**
     * @param string $timer
     * @return void
     */
    public function tick(string $timer = "simple")
    {
        global $TIC;
        $TIC[$timer] = microtime(true);
    }

    /**
     * @param string $timer
     * @return float|int|mixed
     */
    public function tock(string $timer = "simple")
    {
        global $TIC;
        if (isset($TIC[$timer])) {
            return microtime(true) - $TIC[$timer];
        }
        return -1;
    }

    public function getLoader(): DataGrabLoader
    {
        return $this->loader;
    }
}
