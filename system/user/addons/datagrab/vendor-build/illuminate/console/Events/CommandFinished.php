<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Console\Events;

use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Input\InputInterface;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Output\OutputInterface;
class CommandFinished
{
    /**
     * Create a new event instance.
     *
     * @param  string  $command  The command name.
     * @param  \Symfony\Component\Console\Input\InputInterface  $input  The console input implementation.
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output  The command output implementation.
     * @param  int  $exitCode  The command exit code.
     */
    public function __construct(public string $command, public InputInterface $input, public OutputInterface $output, public int $exitCode)
    {
    }
}
