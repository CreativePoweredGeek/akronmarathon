<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace BoldMinded\DataGrab\Dependency\Symfony\Component\Process\Messenger;

use BoldMinded\DataGrab\Dependency\Symfony\Component\Process\Exception\ProcessFailedException;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Process\Exception\RunProcessFailedException;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Process\Process;
/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class RunProcessMessageHandler
{
    public function __invoke(RunProcessMessage $message) : RunProcessContext
    {
        $process = match ($message->commandLine) {
            null => new Process($message->command, $message->cwd, $message->env, $message->input, $message->timeout),
            default => Process::fromShellCommandline($message->commandLine, $message->cwd, $message->env, $message->input, $message->timeout),
        };
        try {
            return new RunProcessContext($message, $process->mustRun());
        } catch (ProcessFailedException $e) {
            throw new RunProcessFailedException($e, new RunProcessContext($message, $e->getProcess()));
        }
    }
}
