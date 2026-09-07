<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\League\Container\Argument;

class ResolvableArgument implements ResolvableArgumentInterface
{
    public function __construct(protected string $value)
    {
    }
    public function getValue() : string
    {
        return $this->value;
    }
}
