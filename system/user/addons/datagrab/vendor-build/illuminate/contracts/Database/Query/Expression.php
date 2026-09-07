<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Database\Query;

use BoldMinded\DataGrab\Dependency\Illuminate\Database\Grammar;
interface Expression
{
    /**
     * Get the value of the expression.
     *
     * @param  \Illuminate\Database\Grammar  $grammar
     * @return string|int|float
     */
    public function getValue(Grammar $grammar);
}
