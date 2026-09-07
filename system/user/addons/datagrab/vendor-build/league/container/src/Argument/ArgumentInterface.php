<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\League\Container\Argument;

interface ArgumentInterface
{
    public function getValue() : mixed;
}
