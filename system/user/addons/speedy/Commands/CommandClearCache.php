<?php

namespace BoldMinded\Speedy\Commands;

use BoldMinded\Speedy\Service\CacheBreaker;
use Error;
use Exception;

class CommandClearCache extends AbstractCommand {

    /**
     * name of command
     * @var string
     */
    public $name = 'Clear Cache';

    /**
     * Public description of command
     * @var string
     */
    public $description = 'Clear all driver caches.';

    /**
     * Summary of command functionality
     * @var [type]
     */
    public $summary = '';

    /**
     * How to use command
     * @var string
     */
    public $usage = 'php system/ee/eecli.php speedy:clear';

    /**
     * options available for use in command
     * @var array
     */
    public $commandOptions = [];

    /**
     * Run the command
     * @return string
     */
    public function handle(): string
    {
        try {
            $this->checkPath();

            /** @var CacheBreaker $breaker */
            $breaker = ee('speedy:CacheBreaker');
            $breaker->_breakCache();

            $this->output->outln('<<green>>Cache Cleared<<reset>>');
        } catch (Error $error) { // Catch EE Core exceptions
            $this->output->outln('<<red>>' . $error->getMessage() . '<<reset>>');
        } catch (Exception $exception) { // Catch general exceptions
            $this->output->outln('<<red>>' . $exception->getMessage() . '<<reset>>');
        }

        return '';
    }
}
