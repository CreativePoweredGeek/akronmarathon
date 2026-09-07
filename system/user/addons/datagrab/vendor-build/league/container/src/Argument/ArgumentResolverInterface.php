<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\League\Container\Argument;

use BoldMinded\DataGrab\Dependency\League\Container\ContainerAwareInterface;
interface ArgumentResolverInterface extends ContainerAwareInterface
{
    public function resolveArguments(array $arguments) : array;
}
