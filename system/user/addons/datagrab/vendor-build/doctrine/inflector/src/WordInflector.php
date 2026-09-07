<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\Doctrine\Inflector;

interface WordInflector
{
    public function inflect(string $word) : string;
}
