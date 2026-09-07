<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\Termwind\Laravel;

use BoldMinded\DataGrab\Dependency\Illuminate\Console\OutputStyle;
use BoldMinded\DataGrab\Dependency\Illuminate\Support\ServiceProvider;
use BoldMinded\DataGrab\Dependency\Termwind\Termwind;
final class TermwindServiceProvider extends ServiceProvider
{
    /**
     * Sets the correct renderer to be used.
     */
    public function register() : void
    {
        $this->app->resolving(OutputStyle::class, function ($style) : void {
            Termwind::renderUsing($style->getOutput());
        });
    }
}
