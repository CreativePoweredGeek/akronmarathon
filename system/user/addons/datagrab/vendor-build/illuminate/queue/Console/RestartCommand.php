<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Queue\Console;

use BoldMinded\DataGrab\Dependency\Illuminate\Console\Command;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Cache\Repository as Cache;
use BoldMinded\DataGrab\Dependency\Illuminate\Support\InteractsWithTime;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Attribute\AsCommand;
#[\Symfony\Component\Console\Attribute\AsCommand(name: 'queue:restart')]
class RestartCommand extends Command
{
    use InteractsWithTime;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'queue:restart';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restart queue worker daemons after their current job';
    /**
     * The cache store implementation.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;
    /**
     * Create a new queue restart command.
     *
     * @param  \Illuminate\Contracts\Cache\Repository  $cache
     * @return void
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
        $this->cache->forever('illuminate:queue:restart', $this->currentTime());
        $this->components->info('Broadcasting queue restart signal.');
    }
}
