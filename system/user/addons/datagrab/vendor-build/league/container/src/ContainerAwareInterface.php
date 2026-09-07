<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\League\Container;

interface ContainerAwareInterface
{
    public function getContainer() : DefinitionContainerInterface;
    public function setContainer(DefinitionContainerInterface $container) : ContainerAwareInterface;
}
