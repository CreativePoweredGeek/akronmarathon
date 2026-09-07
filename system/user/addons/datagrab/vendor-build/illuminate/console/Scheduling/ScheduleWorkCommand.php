<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Console\Scheduling;

use BoldMinded\DataGrab\Dependency\Illuminate\Console\Application;
use BoldMinded\DataGrab\Dependency\Illuminate\Console\Command;
use BoldMinded\DataGrab\Dependency\Illuminate\Support\Carbon;
use BoldMinded\DataGrab\Dependency\Illuminate\Support\ProcessUtils;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Attribute\AsCommand;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Output\OutputInterface;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Process\Process;
#[\Symfony\Component\Console\Attribute\AsCommand(name: 'schedule:work')]
class ScheduleWorkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:work
        {--run-output-file= : The file to direct <info>schedule:run</info> output to}
        {--whisper : Do not output message indicating that no jobs were ready to run}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the schedule worker';
    /**
     * Execute the console command.
     *
     * @return never
     */
    public function handle()
    {
        $this->components->info('Running scheduled tasks.', $this->getLaravel()->environment('local') ? OutputInterface::VERBOSITY_NORMAL : OutputInterface::VERBOSITY_VERBOSE);
        [$lastExecutionStartedAt, $executions] = [Carbon::now()->subMinutes(10), []];
        $command = Application::formatCommandString('schedule:run');
        if ($this->option('whisper')) {
            $command .= ' --whisper';
        }
        if ($this->option('run-output-file')) {
            $command .= ' >> ' . ProcessUtils::escapeArgument($this->option('run-output-file')) . ' 2>&1';
        }
        while (\true) {
            \usleep(100 * 1000);
            if (Carbon::now()->second === 0 && !Carbon::now()->startOfMinute()->equalTo($lastExecutionStartedAt)) {
                $executions[] = $execution = Process::fromShellCommandline($command, base_path());
                $execution->start();
                $lastExecutionStartedAt = Carbon::now()->startOfMinute();
            }
            foreach ($executions as $key => $execution) {
                $output = $execution->getIncrementalOutput() . $execution->getIncrementalErrorOutput();
                $this->output->write(\ltrim($output, "\n"));
                if (!$execution->isRunning()) {
                    unset($executions[$key]);
                }
            }
        }
    }
}
