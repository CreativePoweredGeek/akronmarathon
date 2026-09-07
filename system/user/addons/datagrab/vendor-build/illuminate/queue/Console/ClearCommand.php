<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Queue\Console;

use BoldMinded\DataGrab\Dependency\Illuminate\Console\Command;
use BoldMinded\DataGrab\Dependency\Illuminate\Console\ConfirmableTrait;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Queue\ClearableQueue;
use BoldMinded\DataGrab\Dependency\Illuminate\Support\Str;
use ReflectionClass;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Attribute\AsCommand;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Input\InputArgument;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Input\InputOption;
#[\Symfony\Component\Console\Attribute\AsCommand(name: 'queue:clear')]
class ClearCommand extends Command
{
    use ConfirmableTrait;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'queue:clear';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all of the jobs from the specified queue';
    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        if (!$this->confirmToProceed()) {
            return 1;
        }
        $connection = $this->argument('connection') ?: $this->laravel['config']['queue.default'];
        // We need to get the right queue for the connection which is set in the queue
        // configuration file for the application. We will pull it based on the set
        // connection being run for the queue operation currently being executed.
        $queueName = $this->getQueue($connection);
        $queue = $this->laravel['queue']->connection($connection);
        if ($queue instanceof ClearableQueue) {
            $count = $queue->clear($queueName);
            $this->components->info('Cleared ' . $count . ' ' . Str::plural('job', $count) . ' from the [' . $queueName . '] queue');
        } else {
            $this->components->error('Clearing queues is not supported on [' . (new ReflectionClass($queue))->getShortName() . ']');
            return 1;
        }
        return 0;
    }
    /**
     * Get the queue name to clear.
     *
     * @param  string  $connection
     * @return string
     */
    protected function getQueue($connection)
    {
        return $this->option('queue') ?: $this->laravel['config']->get("queue.connections.{$connection}.queue", 'default');
    }
    /**
     *  Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [['connection', InputArgument::OPTIONAL, 'The name of the queue connection to clear']];
    }
    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [['queue', null, InputOption::VALUE_OPTIONAL, 'The name of the queue to clear'], ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production']];
    }
}
