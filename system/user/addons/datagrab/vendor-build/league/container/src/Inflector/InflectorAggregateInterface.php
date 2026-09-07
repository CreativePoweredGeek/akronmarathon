<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\League\Container\Inflector;

use IteratorAggregate;
use BoldMinded\DataGrab\Dependency\League\Container\ContainerAwareInterface;
interface InflectorAggregateInterface extends ContainerAwareInterface, IteratorAggregate
{
    public function add(string $type, ?callable $callback = null) : Inflector;
    public function inflect(mixed $object) : mixed;
}
