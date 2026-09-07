<?php

use BoldMinded\Speedy\Library\Basee\Update\AbstractUpdate;
use BoldMinded\Speedy\Model\Diagnostics;
use BoldMinded\Speedy\Model\Url;

class Update_1_14_03 extends AbstractUpdate
{
    public function doUpdate()
    {
        ee()->db->data_cache = [];

        $this->addActions([
            [
                'class' => 'Speedy',
                'method' => '_loopback_test',
            ]
        ]);
    }
}
