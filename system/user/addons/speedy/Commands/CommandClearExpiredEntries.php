<?php

namespace BoldMinded\Speedy\Commands;

use BoldMinded\Speedy\Service\CacheBreaker;
use Error;
use Exception;
use ExpressionEngine\Service\Model\Collection;

class CommandClearExpiredEntries extends AbstractCommand
{
    const DEFAULT_TIME = 60;

    /**
     * name of command
     * @var string
     */
    public $name = 'Clear Expired';

    /**
     * Public description of command
     * @var string
     */
    public $description = 'Find and clear cache for entries with expired publish dates.';

    /**
     * Summary of command functionality
     * @var [type]
     */
    public $summary = '';

    /**
     * How to use command
     * @var string
     */
    public $usage = 'php system/ee/eecli.php speedy:clear-expired';

    /**
     * options available for use in command
     */
    public $commandOptions = [
        'time,time:' => 'The amount of time in seconds to look for expiration dates.',
    ];

    /**
     * Run the command
     * @return string
     */
    public function handle(): string
    {
        try {
            $this->checkPath();

            $entries = $this->findEntries();

            if (count($entries) === 0) {
                $this->output->outln('<<yellow>>No entries found<<reset>>');

                return '';
            }

            /** @var CacheBreaker $breaker */
            $breaker = ee('speedy:CacheBreaker');
            $breaker->_breakEntryCache($entries);

            $this->output->outln('<<green>>Cache Cleared<<reset>>');

            $titles = $entries->pluck('title');

            foreach ($titles as $title) {
                $this->output->outln(sprintf('<<green>>    %s<<reset>>', $title));
            }
        } catch (Error $error) { // Catch EE Core exceptions
            $this->output->outln('<<red>>' . $error->getMessage() . '<<reset>>');
        } catch (Exception $exception) { // Catch general exceptions
            $this->output->outln('<<red>>' . $exception->getMessage() . '<<reset>>');
        }

        return '';
    }

    /**
     * Only look for entries that expired within the last minute (or --time seconds).
     * Without the lower bound, every historically expired entry would be returned
     * and re-processed on every run when this command is executed on a cron.
     */
    private function findEntries(): Collection
    {
        $now = ee()->localize->now ?? time();
        $time = $this->option('--time') ?? self::DEFAULT_TIME;
        $past = $now - $time;

        $entries = ee('Model')->get('ChannelEntry')
            ->filter('expiration_date', '!=', 0)
            ->filter('expiration_date', '<=', $now)
            ->filter('expiration_date', '>=', $past)
            ->all();

        return $entries;
    }
}
