<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\League\Container\Attribute;

interface AttributeInterface
{
    public function resolve() : mixed;
}
