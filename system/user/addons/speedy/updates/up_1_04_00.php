<?php

use BoldMinded\Speedy\Library\Basee\Update\AbstractUpdate;
use BoldMinded\Speedy\Service\Drivers\StaticDriver;

class Update_1_04_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        $this->addActions([
            [
                'class' => 'Speedy',
                'method' => '_is_frontedit_enabled',
            ]
        ]);

        $staticDriver = new StaticDriver();
        $cachePath = $staticDriver->getCachePath();
        $staticUtilitiesPath = $cachePath . '/utilities';

        // Delete this file so static cache users will be forced to re-save the utilities
        @unlink($staticUtilitiesPath . '/configured.txt');
    }
}
