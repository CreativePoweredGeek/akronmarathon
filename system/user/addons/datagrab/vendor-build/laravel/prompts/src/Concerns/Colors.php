<?php

namespace BoldMinded\DataGrab\Dependency\Laravel\Prompts\Concerns;

trait Colors
{
    /**
     * Reset all colors and styles.
     */
    public function reset(string $text) : string
    {
        return "\x1b[0m{$text}\x1b[0m";
    }
    /**
     * Make the text bold.
     */
    public function bold(string $text) : string
    {
        return "\x1b[1m{$text}\x1b[22m";
    }
    /**
     * Make the text dim.
     */
    public function dim(string $text) : string
    {
        return "\x1b[2m{$text}\x1b[22m";
    }
    /**
     * Make the text italic.
     */
    public function italic(string $text) : string
    {
        return "\x1b[3m{$text}\x1b[23m";
    }
    /**
     * Underline the text.
     */
    public function underline(string $text) : string
    {
        return "\x1b[4m{$text}\x1b[24m";
    }
    /**
     * Invert the text and background colors.
     */
    public function inverse(string $text) : string
    {
        return "\x1b[7m{$text}\x1b[27m";
    }
    /**
     * Hide the text.
     */
    public function hidden(string $text) : string
    {
        return "\x1b[8m{$text}\x1b[28m";
    }
    /**
     * Strike through the text.
     */
    public function strikethrough(string $text) : string
    {
        return "\x1b[9m{$text}\x1b[29m";
    }
    /**
     * Set the text color to black.
     */
    public function black(string $text) : string
    {
        return "\x1b[30m{$text}\x1b[39m";
    }
    /**
     * Set the text color to red.
     */
    public function red(string $text) : string
    {
        return "\x1b[31m{$text}\x1b[39m";
    }
    /**
     * Set the text color to green.
     */
    public function green(string $text) : string
    {
        return "\x1b[32m{$text}\x1b[39m";
    }
    /**
     * Set the text color to yellow.
     */
    public function yellow(string $text) : string
    {
        return "\x1b[33m{$text}\x1b[39m";
    }
    /**
     * Set the text color to blue.
     */
    public function blue(string $text) : string
    {
        return "\x1b[34m{$text}\x1b[39m";
    }
    /**
     * Set the text color to magenta.
     */
    public function magenta(string $text) : string
    {
        return "\x1b[35m{$text}\x1b[39m";
    }
    /**
     * Set the text color to cyan.
     */
    public function cyan(string $text) : string
    {
        return "\x1b[36m{$text}\x1b[39m";
    }
    /**
     * Set the text color to white.
     */
    public function white(string $text) : string
    {
        return "\x1b[37m{$text}\x1b[39m";
    }
    /**
     * Set the text background to black.
     */
    public function bgBlack(string $text) : string
    {
        return "\x1b[40m{$text}\x1b[49m";
    }
    /**
     * Set the text background to red.
     */
    public function bgRed(string $text) : string
    {
        return "\x1b[41m{$text}\x1b[49m";
    }
    /**
     * Set the text background to green.
     */
    public function bgGreen(string $text) : string
    {
        return "\x1b[42m{$text}\x1b[49m";
    }
    /**
     * Set the text background to yellow.
     */
    public function bgYellow(string $text) : string
    {
        return "\x1b[43m{$text}\x1b[49m";
    }
    /**
     * Set the text background to blue.
     */
    public function bgBlue(string $text) : string
    {
        return "\x1b[44m{$text}\x1b[49m";
    }
    /**
     * Set the text background to magenta.
     */
    public function bgMagenta(string $text) : string
    {
        return "\x1b[45m{$text}\x1b[49m";
    }
    /**
     * Set the text background to cyan.
     */
    public function bgCyan(string $text) : string
    {
        return "\x1b[46m{$text}\x1b[49m";
    }
    /**
     * Set the text background to white.
     */
    public function bgWhite(string $text) : string
    {
        return "\x1b[47m{$text}\x1b[49m";
    }
    /**
     * Set the text color to gray.
     */
    public function gray(string $text) : string
    {
        return "\x1b[90m{$text}\x1b[39m";
    }
}
