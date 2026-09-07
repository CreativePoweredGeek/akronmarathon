<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Container\Attributes;

use Attribute;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Container\Container;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Container\ContextualAttribute;
#[\Attribute(Attribute::TARGET_PARAMETER)]
class Give implements ContextualAttribute
{
    /**
     * Provide a concrete class implementation for dependency injection.
     *
     * @param  string  $class
     * @param  array|null  $params
     */
    public function __construct(public string $class, public array $params = [])
    {
    }
    /**
     * Resolve the dependency.
     *
     * @param  self  $attribute
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return mixed
     */
    public static function resolve(self $attribute, Container $container) : mixed
    {
        return $container->make($attribute->class, $attribute->params);
    }
}
