<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Queue\Console;

use BoldMinded\DataGrab\Dependency\Illuminate\Bus\BatchRepository;
use BoldMinded\DataGrab\Dependency\Illuminate\Bus\DatabaseBatchRepository;
use BoldMinded\DataGrab\Dependency\Illuminate\Bus\PrunableBatchRepository;
use BoldMinded\DataGrab\Dependency\Illuminate\Console\Command;
use BoldMinded\DataGrab\Dependency\Illuminate\Support\Carbon;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Attribute\AsCommand;
#[\Symfony\Component\Console\Attribute\AsCommand(name: 'queue:prune-batches')]
class PruneBatchesCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'queue:prune-batches
                {--hours=24 : The number of hours to retain batch data}
                {--unfinished= : The number of hours to retain unfinished batch data }
                {--cancelled= : The number of hours to retain cancelled batch data }';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune stale entries from the batches database';
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $repository = $this->laravel[BatchRepository::class];
        $count = 0;
        if ($repository instanceof PrunableBatchRepository) {
            $count = $repository->prune(Carbon::now()->subHours($this->option('hours')));
        }
        $this->components->info("{$count} entries deleted.");
        if ($this->option('unfinished') !== null) {
            $count = 0;
            if ($repository instanceof DatabaseBatchRepository) {
                $count = $repository->pruneUnfinished(Carbon::now()->subHours($this->option('unfinished')));
            }
            $this->components->info("{$count} unfinished entries deleted.");
        }
        if ($this->option('cancelled') !== null) {
            $count = 0;
            if ($repository instanceof DatabaseBatchRepository) {
                $count = $repository->pruneCancelled(Carbon::now()->subHours($this->option('cancelled')));
            }
            $this->components->info("{$count} cancelled entries deleted.");
        }
    }
}
