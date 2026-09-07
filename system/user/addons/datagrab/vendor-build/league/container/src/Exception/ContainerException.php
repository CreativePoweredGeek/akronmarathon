<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\League\Container\Exception;

use BoldMinded\DataGrab\Dependency\Psr\Container\ContainerExceptionInterface;
use RuntimeException;
class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}
