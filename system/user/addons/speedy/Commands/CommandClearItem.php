<?php

namespace BoldMinded\Speedy\Commands;

use BoldMinded\Speedy\Service\CacheBreaker;
use Error;
use Exception;

class CommandClearItem extends AbstractCommand {

    /**
     * name of command
     * @var string
     */
    public $name = 'Clear Item';

    /**
     * Public description of command
     * @var string
     */
    public $description = 'Clear a specific item from the cache by key.';

    /**
     * Summary of command functionality
     * @var [type]
     */
    public $summary = '';

    /**
     * How to use command
     * @var string
     */
    public $usage = 'php system/ee/eecli.php speedy:clear-item';

    /**
     * options available for use in command
     * @var array
     */
    public $commandOptions = [
        'key:' => 'The cache key you\'re trying to clear. To clear multiple items separate them with a comma.',
        'refresh:' => 'Optionally refresh the key(s).'
    ];

    /**
     * Run the command
     * @return string
     */
    public function handle(): string
    {
        try {
            $this->checkPath();

            $keys = array_filter(
                explode(',', $this->option('--key') ?? '')
            );

            if (count($keys) === 0) {
                $this->output->outln('<<red>>Please provide a key<<reset>>');

                return '';
            }

            /** @var CacheBreaker $breaker */
            $breaker = ee('speedy:CacheBreaker');
            $breaker->clearItems($keys);

            if (get_bool_from_string($this->option('--refresh'))) {
                $breaker->refreshItems($keys);
            }

            $this->output->outln('<<green>>Cache Cleared<<reset>>');
        } catch (Error $error) { // Catch EE Core exceptions
            $this->output->outln('<<red>>' . $error->getMessage() . '<<reset>>');
        } catch (Exception $exception) { // Catch general exceptions
            $this->output->outln('<<red>>' . $exception->getMessage() . '<<reset>>');
        }

        return '';
    }
}
