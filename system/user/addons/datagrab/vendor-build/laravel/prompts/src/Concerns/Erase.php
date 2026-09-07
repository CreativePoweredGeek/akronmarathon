<?php

namespace BoldMinded\DataGrab\Dependency\Laravel\Prompts\Concerns;

trait Erase
{
    /**
     * Erase the given number of lines downwards from the cursor position.
     */
    public function eraseLines(int $count) : void
    {
        $clear = '';
        for ($i = 0; $i < $count; $i++) {
            $clear .= "\x1b[2K" . ($i < $count - 1 ? "\x1b[{$count}A" : '');
        }
        if ($count) {
            $clear .= "\x1b[G";
        }
        static::writeDirectly($clear);
    }
    /**
     * Erase from cursor until end of screen.
     */
    public function eraseDown() : void
    {
        static::writeDirectly("\x1b[J");
    }
}
