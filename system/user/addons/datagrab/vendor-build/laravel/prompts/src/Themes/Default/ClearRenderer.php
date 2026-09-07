<?php

namespace BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default;

class ClearRenderer extends Renderer
{
    /**
     * Clear the terminal.
     */
    public function __invoke() : string
    {
        return "\x1b[H\x1b[J";
    }
}
