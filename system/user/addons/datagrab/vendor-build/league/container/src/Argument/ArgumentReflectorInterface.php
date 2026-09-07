<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\League\Container\Argument;

use BoldMinded\DataGrab\Dependency\League\Container\ContainerAwareInterface;
use ReflectionFunctionAbstract;
interface ArgumentReflectorInterface extends ContainerAwareInterface
{
    public function reflectArguments(ReflectionFunctionAbstract $method, array $args = []) : array;
}
