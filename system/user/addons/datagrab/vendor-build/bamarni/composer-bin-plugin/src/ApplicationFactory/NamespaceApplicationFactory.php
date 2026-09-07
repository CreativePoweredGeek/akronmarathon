<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\Bamarni\Composer\Bin\ApplicationFactory;

use BoldMinded\DataGrab\Dependency\Composer\Console\Application;
interface NamespaceApplicationFactory
{
    public function create(Application $existingApplication) : Application;
}
