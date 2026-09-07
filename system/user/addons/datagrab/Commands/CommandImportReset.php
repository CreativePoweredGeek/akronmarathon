<?php

namespace BoldMinded\DataGrab\Commands;

use ExpressionEngine\Cli\Cli;
use Error;
use Exception;

class CommandImportReset extends Cli {

    /**
     * name of command
     * @var string
     */
    public $name = 'Reset Import';

    /**
     * Public description of command
     * @var string
     */
    public $description = 'Resets a DataGrab import';

    /**
     * Summary of command functionality
     * @var [type]
     */
    public $summary = 'Resets a DataGrab import to a "New" status, as if it has never been run before.';

    /**
     * How to use command
     * @var string
     */
    public $usage = 'php system/ee/eecli.php import:reset --id=123';

    /**
     * options available for use in command
     * @var array
     */
    public $commandOptions = [
        'import_id,id:' => 'Import ID',
        'pass_key,key:' => 'Import Pass Key',
    ];

    /**
     * Run the command
     * @return mixed
     */
    public function handle()
    {
        $importId = $this->option('--import_id');
        $passKey = $this->option('--pass_key');

        // Fetch import settings
        $query = ee()->db->where('id', $importId)->get('exp_datagrab');

        if ($query->num_rows() == 0) {
            $this->output->outln('<<redbg>>Could not find the requested import ID.<<reset>>');
            exit;
        }

        $row = $query->row_array();
        $this->settings = json_decode($row["settings"], true);
        $importName = $row['name'];

        if ($row["passkey"] != '' && $row["passkey"] != $passKey) {
            $this->output->outln('<<redbg>>Import aborted. Passkey required, but none provided.<<reset>>');
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
        $this->settings['import']['cli'] = $this->output;

        // Do import
        try {
            $this->output->outln('<<dim>>Resetting: ' . $importName .'... <<reset>>');

            ee('datagrab:Importer')->resetImport($importId);

            $this->output->outln('<<green>>Import Reset<<reset>>');
        } catch (Error $error) { // Catch EE Core exceptions
            $this->output->outln('<<red>>' . $error->getMessage() . '<<reset>>');
        } catch (Exception $exception) { // Catch general exceptions
            $this->output->outln('<<red>>' . $exception->getMessage() . '<<reset>>');
        }

        return '';
    }
}
