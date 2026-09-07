<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Container\Attributes;

use Attribute;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Container\Container;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Container\ContextualAttribute;
#[\Attribute(Attribute::TARGET_PARAMETER)]
class Auth implements ContextualAttribute
{
    /**
     * Create a new class instance.
     */
    public function __construct(public ?string $guard = null)
    {
    }
    /**
     * Resolve the authentication guard.
     *
     * @param  self  $attribute
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return \Illuminate\Contracts\Auth\Guard|\Illuminate\Contracts\Auth\StatefulGuard
     */
    public static function resolve(self $attribute, Container $container)
    {
        return $container->make('auth')->guard($attribute->guard);
    }
}
