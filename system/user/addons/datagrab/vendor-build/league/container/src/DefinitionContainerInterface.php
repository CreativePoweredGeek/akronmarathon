<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\League\Container;

use BoldMinded\DataGrab\Dependency\League\Container\Definition\DefinitionInterface;
use BoldMinded\DataGrab\Dependency\League\Container\Inflector\InflectorInterface;
use BoldMinded\DataGrab\Dependency\League\Container\ServiceProvider\ServiceProviderInterface;
use BoldMinded\DataGrab\Dependency\Psr\Container\ContainerInterface;
interface DefinitionContainerInterface extends ContainerInterface
{
    public function add(string $id, mixed $concrete = null, bool $overwrite = \false) : DefinitionInterface;
    public function addServiceProvider(ServiceProviderInterface $provider) : self;
    public function addShared(string $id, mixed $concrete = null, bool $overwrite = \false) : DefinitionInterface;
    public function extend(string $id) : DefinitionInterface;
    public function getNew(string $id) : mixed;
    public function inflector(string $type, ?callable $callback = null) : InflectorInterface;
}
