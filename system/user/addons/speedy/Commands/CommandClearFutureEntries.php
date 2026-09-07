<?php

namespace BoldMinded\Speedy\Commands;

use BoldMinded\Speedy\Service\CacheBreaker;
use Error;
use Exception;
use ExpressionEngine\Service\Model\Collection;

class CommandClearFutureEntries extends AbstractCommand
{
    const DEFAULT_TIME = 60;

    /**
     * name of command
     * @var string
     */
    public $name = 'Clear Future Publish Dates';

    /**
     * Public description of command
     * @var string
     */
    public $description = 'Find and clear cache for entries with future publish dates.';

    /**
     * Summary of command functionality
     * @var [type]
     */
    public $summary = '';

    /**
     * How to use command
     * @var string
     */
    public $usage = 'php system/ee/eecli.php speedy:clear-future';

    /**
     * options available for use in command
     */
    public $commandOptions = [
        'time,time:' => 'The amount of time in seconds to look for entry dates.',
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
     * This command name and what this function is doing is a little contradictory.
     * Look 1 minute into the past to see if the entry date has passed before clearing
     * an entry, otherwise if we look forward to dates in the next minute, there is a
     * chance we'll clear the cache just to have someone hit the page again which
     * would re-create the cache item we just cleared, thus nullifying what this command does.
     */
    private function findEntries(): Collection
    {
        $now = ee()->localize->now ?? time();
        $time = $this->option('--time') ?? self::DEFAULT_TIME;
        $past = $now - $time;

        $entries = ee('Model')->get('ChannelEntry')
            ->filter('entry_date', '<=', $now)
            ->filter('entry_date', '>=', $past)
            ->all();

        return $entries;
    }
}
