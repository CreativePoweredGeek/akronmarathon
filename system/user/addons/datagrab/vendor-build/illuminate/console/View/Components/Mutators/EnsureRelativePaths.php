<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Console\View\Components\Mutators;

class EnsureRelativePaths
{
    /**
     * Ensures the given string only contains relative paths.
     *
     * @param  string  $string
     * @return string
     */
    public function __invoke($string)
    {
        if (\function_exists('BoldMinded\\DataGrab\\Dependency\\app') && app()->has('path.base')) {
            $string = \str_replace(base_path() . '/', '', $string);
        }
        return $string;
    }
}
