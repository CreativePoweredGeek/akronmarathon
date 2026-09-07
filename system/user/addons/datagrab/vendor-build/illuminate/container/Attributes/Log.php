<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Container\Attributes;

use Attribute;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Container\Container;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Container\ContextualAttribute;
#[\Attribute(Attribute::TARGET_PARAMETER)]
class Log implements ContextualAttribute
{
    /**
     * Create a new class instance.
     */
    public function __construct(public ?string $channel = null)
    {
    }
    /**
     * Resolve the log channel.
     *
     * @param  self  $attribute
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return \Psr\Log\LoggerInterface
     */
    public static function resolve(self $attribute, Container $container)
    {
        return $container->make('log')->channel($attribute->channel);
    }
}
