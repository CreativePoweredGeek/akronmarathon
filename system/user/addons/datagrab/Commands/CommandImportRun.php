<?php

namespace BoldMinded\DataGrab\Commands;

use ExpressionEngine\Cli\Cli;
use Error;
use Exception;

class CommandImportRun extends Cli {

    /**
     * name of command
     * @var string
     */
    public $name = 'Run Import';

    /**
     * Public description of command
     * @var string
     */
    public $description = 'Runs a saved DataGrab import';

    /**
     * Summary of command functionality
     * @var [type]
     */
    public $summary = 'Runs a saved DataGrab import';

    /**
     * How to use command
     * @var string
     */
    public $usage = 'php system/ee/eecli.php import:run --id=123';

    /**
     * options available for use in command
     * @var array
     */
    public $commandOptions = [
        'import_id,id:' => 'Import ID',
        'pass_key,key:' => 'Import Pass Key',
        'limit,limit:' => 'Total number of entries to limit to each worker process',
        'producer' => 'Run producer only',
        'consumer' => 'Run consumer only',
        'params,params:' => 'Pass additional parameters to the import URL, e.g. --params="productId=123&size=large".',
        'force_update,force:' => 'Override "Update" setting and force entries to be updated based on matching criteria.',
        'filename:' => 'Change the filename or path to a file to import at runtime.'
    ];

    /**
     * @var array
     */
    private $settings = [];

    /**
     * Run the command
     * @return mixed
     */
    public function handle()
    {
        $importId = $this->option('--import_id');
        $passKey = $this->option('--pass_key');
        $producer = $this->option('--producer');
        $consumer = $this->option('--consumer');
        $limit = $this->option('--limit');
        $params = $this->option('--params');
        $forceUpdate = $this->option('--force_update');
        $fileName = $this->option('--filename');

        // Fetch import settings
        $query = ee()->db->where('id', $importId)->get('datagrab');

        if ($query->num_rows() == 0) {
            $this->output->outln('<<redbg>>Could not find the requested import ID.<<reset>>');
            exit;
        }

        $row = $query->row_array();
        $this->settings = json_decode($row["settings"], true);
        $importName = $row['name'];

        if ($row['passkey'] != '' && $row['passkey'] != $passKey) {
            $this->output->outln('<<redbg>>Import aborted. Passkey required, but none provided.<<reset>>');
            exit;
        }

        if (!extension_loaded('pcntl')) {
            $this->output->outln('<<red>>PCNTL extension is NOT installed.<<reset>>');
            exit;
        }

        set_time_limit(0);
        ini_set('memory_limit', '1G');

        // Initialise
        ee()->load->library('session');
        ee()->load->add_package_path(PATH_THIRD . 'datagrab');
        ee()->lang->loadfile('datagrab');

        $this->settings['import']['id'] = $importId;
        $this->settings['import']['passkey'] = $passKey;
        $this->settings['import']['site_id'] = $row['site_id'];

        if ($limit !== null) {
            $this->settings['import']['limit'] = (int) $limit;
        }

        if ($fileName) {
            $this->settings['datatype']['filename'] = $fileName;
        }

        if ($params !== null) {
            $fn = $this->settings['datatype']['filename'];
            $this->settings['datatype']['filename'] = $fn . (str_contains($fn, '?') ? '&' . $params : '?' . $params);
        }

        if ($forceUpdate !== null) {
            $this->settings['config']['update'] = 'y';
        }

        try {
            $this->output->outln('<<dim>>Starting: ' . $importName .'... <<reset>>');

            $shouldProduce = true;
            $shouldConsume = true;

            if ($consumer && !$producer) {
                $shouldProduce = false;
            }

            if ($producer && !$consumer) {
                $shouldConsume = false;
            }

            $importer = ee('datagrab:Importer');
            $importer->setup(
                    $importer->datatypes[$this->settings['import']['type']],
                    $this->settings,
                    $shouldProduce
                );

            if ($shouldProduce) {
                $this->output->outln('<<dim>>Queueing...<<reset>>');
                $importer->resetImport()->produceJobs();
            }

            if ($shouldConsume) {
                $this->output->outln('<<dim>>Consuming...<<reset>>');
                $importer->consumeJobs();
            }

        } catch (Error $error) { // Catch EE Core exceptions
            $this->output->outln('<<red>>' . $error->getMessage() . '<<reset>>');
        } catch (Exception $exception) { // Catch general exceptions
            $this->output->outln('<<red>>' . $exception->getMessage() . '<<reset>>');
        }

        return '';
    }
}
