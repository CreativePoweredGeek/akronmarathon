<?php

namespace BoldMinded\DataGrab\Dependency\Laravel\Prompts\Concerns;

use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Output\BufferedConsoleOutput;
use function BoldMinded\DataGrab\Dependency\Termwind\render;
use function BoldMinded\DataGrab\Dependency\Termwind\renderUsing;
trait Termwind
{
    protected function termwind(string $html)
    {
        renderUsing($output = new BufferedConsoleOutput());
        render($html);
        return $this->restoreEscapeSequences($output->fetch());
    }
    protected function restoreEscapeSequences(string $string)
    {
        return \preg_replace('/\\[(\\d+)m/', "\x1b[" . '\\1m', $string);
    }
}
