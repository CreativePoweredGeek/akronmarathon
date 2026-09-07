<?php

namespace BoldMinded\DataGrab\Dependency\Laravel\Prompts\Concerns;

trait Cursor
{
    /**
     * Indicates if the cursor has been hidden.
     */
    protected static bool $cursorHidden = \false;
    /**
     * Hide the cursor.
     */
    public function hideCursor() : void
    {
        static::writeDirectly("\x1b[?25l");
        static::$cursorHidden = \true;
    }
    /**
     * Show the cursor.
     */
    public function showCursor() : void
    {
        static::writeDirectly("\x1b[?25h");
        static::$cursorHidden = \false;
    }
    /**
     * Restore the cursor if it was hidden.
     */
    public function restoreCursor() : void
    {
        if (static::$cursorHidden) {
            $this->showCursor();
        }
    }
    /**
     * Move the cursor.
     */
    public function moveCursor(int $x, int $y = 0) : void
    {
        $sequence = '';
        if ($x < 0) {
            $sequence .= "\x1b[" . \abs($x) . 'D';
            // Left
        } elseif ($x > 0) {
            $sequence .= "\x1b[{$x}C";
            // Right
        }
        if ($y < 0) {
            $sequence .= "\x1b[" . \abs($y) . 'A';
            // Up
        } elseif ($y > 0) {
            $sequence .= "\x1b[{$y}B";
            // Down
        }
        static::writeDirectly($sequence);
    }
    /**
     * Move the cursor to the given column.
     */
    public function moveCursorToColumn(int $column) : void
    {
        static::writeDirectly("\x1b[{$column}G");
    }
    /**
     * Move the cursor up by the given number of lines.
     */
    public function moveCursorUp(int $lines) : void
    {
        static::writeDirectly("\x1b[{$lines}A");
    }
}
