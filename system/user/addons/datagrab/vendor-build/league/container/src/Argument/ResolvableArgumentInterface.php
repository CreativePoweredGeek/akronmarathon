<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\League\Container\Argument;

interface ResolvableArgumentInterface extends ArgumentInterface
{
    public function getValue() : string;
}
