<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\Bamarni\Composer\Bin\ApplicationFactory;

use BoldMinded\DataGrab\Dependency\Composer\Console\Application;
final class FreshInstanceApplicationFactory implements NamespaceApplicationFactory
{
    public function create(Application $existingApplication) : Application
    {
        return new Application();
    }
}
