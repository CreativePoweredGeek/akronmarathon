<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\League\Container\Attribute;

use Attribute;
use BoldMinded\DataGrab\Dependency\League\Container\ContainerAwareInterface;
use BoldMinded\DataGrab\Dependency\League\Container\ContainerAwareTrait;
use BoldMinded\DataGrab\Dependency\Psr\Container\ContainerExceptionInterface;
use BoldMinded\DataGrab\Dependency\Psr\Container\NotFoundExceptionInterface;
#[\Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
class Inject implements AttributeInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;
    public function __construct(protected string $id)
    {
    }
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function resolve() : mixed
    {
        return $this->getContainer()->get($this->id);
    }
}
