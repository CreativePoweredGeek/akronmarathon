<?php

namespace BoldMinded\DataGrab\Dependency\Laravel\Prompts;

class Key
{
    const UP = "\x1b[A";
    const SHIFT_UP = "\x1b[1;2A";
    const PAGE_UP = "\x1b[5~";
    const DOWN = "\x1b[B";
    const SHIFT_DOWN = "\x1b[1;2B";
    const PAGE_DOWN = "\x1b[6~";
    const RIGHT = "\x1b[C";
    const LEFT = "\x1b[D";
    const UP_ARROW = "\x1bOA";
    const DOWN_ARROW = "\x1bOB";
    const RIGHT_ARROW = "\x1bOC";
    const LEFT_ARROW = "\x1bOD";
    const ESCAPE = "\x1b";
    const DELETE = "\x1b[3~";
    const BACKSPACE = "";
    const ENTER = "\n";
    const SPACE = ' ';
    const TAB = "\t";
    const SHIFT_TAB = "\x1b[Z";
    const HOME = ["\x1b[1~", "\x1bOH", "\x1b[H", "\x1b[7~"];
    const END = ["\x1b[4~", "\x1bOF", "\x1b[F", "\x1b[8~"];
    /**
     * Cancel/SIGINT
     */
    const CTRL_C = "\x03";
    /**
     * Previous/Up
     */
    const CTRL_P = "\x10";
    /**
     * Next/Down
     */
    const CTRL_N = "\x0e";
    /**
     * Forward/Right
     */
    const CTRL_F = "\x06";
    /**
     * Back/Left
     */
    const CTRL_B = "\x02";
    /**
     * Backspace
     */
    const CTRL_H = "\x08";
    /**
     * Home
     */
    const CTRL_A = "\x01";
    /**
     * EOF
     */
    const CTRL_D = "\x04";
    /**
     * End
     */
    const CTRL_E = "\x05";
    /**
     * Negative affirmation
     */
    const CTRL_U = "\x15";
    /**
     * Checks for the constant values for the given match and returns the match
     *
     * @param  array<string|array<string>>  $keys
     */
    public static function oneOf(array $keys, string $match) : ?string
    {
        foreach ($keys as $key) {
            if (\is_array($key) && static::oneOf($key, $match) !== null) {
                return $match;
            } elseif ($key === $match) {
                return $match;
            }
        }
        return null;
    }
}
