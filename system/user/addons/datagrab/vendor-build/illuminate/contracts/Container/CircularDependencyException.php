<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Container;

use Exception;
use BoldMinded\DataGrab\Dependency\Psr\Container\ContainerExceptionInterface;
class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
    //
}
