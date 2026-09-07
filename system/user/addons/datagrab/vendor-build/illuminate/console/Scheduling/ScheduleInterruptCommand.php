<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Console\Scheduling;

use BoldMinded\DataGrab\Dependency\Illuminate\Console\Command;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Cache\Repository as Cache;
use BoldMinded\DataGrab\Dependency\Illuminate\Support\Facades\Date;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Attribute\AsCommand;
#[\Symfony\Component\Console\Attribute\AsCommand(name: 'schedule:interrupt')]
class ScheduleInterruptCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'schedule:interrupt';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interrupt the current schedule run';
    /**
     * The cache store implementation.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;
    /**
     * Create a new schedule interrupt command.
     *
     * @param  \Illuminate\Contracts\Cache\Repository  $cache
     */
    public function __construct(Cache $cache)
    {
        parent::__construct();
        $this->cache = $cache;
    }
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->cache->put('illuminate:schedule:interrupt', \true, Date::now()->endOfMinute());
        $this->components->info('Broadcasting schedule interrupt signal.');
    }
}
