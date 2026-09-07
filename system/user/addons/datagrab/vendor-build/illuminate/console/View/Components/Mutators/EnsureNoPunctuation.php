<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Console\View\Components\Mutators;

use BoldMinded\DataGrab\Dependency\Illuminate\Support\Stringable;
class EnsureNoPunctuation
{
    /**
     * Ensures the given string does not end with punctuation.
     *
     * @param  string  $string
     * @return string
     */
    public function __invoke($string)
    {
        if ((new Stringable($string))->endsWith(['.', '?', '!', ':'])) {
            return \substr_replace($string, '', -1);
        }
        return $string;
    }
}
