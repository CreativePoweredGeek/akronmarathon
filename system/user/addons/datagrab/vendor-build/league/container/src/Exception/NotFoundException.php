<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\League\Container\Exception;

use BoldMinded\DataGrab\Dependency\Psr\Container\NotFoundExceptionInterface;
use InvalidArgumentException;
class NotFoundException extends InvalidArgumentException implements NotFoundExceptionInterface
{
}
