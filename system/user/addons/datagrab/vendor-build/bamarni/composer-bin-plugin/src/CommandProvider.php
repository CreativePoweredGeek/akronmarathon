<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\Bamarni\Composer\Bin;

use BoldMinded\DataGrab\Dependency\Bamarni\Composer\Bin\Command\BinCommand;
use BoldMinded\DataGrab\Dependency\Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
/**
 * @final Will be final in 2.x.
 */
class CommandProvider implements CommandProviderCapability
{
    public function getCommands() : array
    {
        return [new BinCommand()];
    }
}
