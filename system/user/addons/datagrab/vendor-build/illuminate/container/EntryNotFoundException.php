<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Container;

use Exception;
use BoldMinded\DataGrab\Dependency\Psr\Container\NotFoundExceptionInterface;
class EntryNotFoundException extends Exception implements NotFoundExceptionInterface
{
    //
}
