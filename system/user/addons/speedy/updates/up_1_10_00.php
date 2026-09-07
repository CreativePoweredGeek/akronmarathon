<?php

use BoldMinded\Speedy\Library\Basee\Update\AbstractUpdate;
use BoldMinded\Speedy\Model\Diagnostics;

class Update_1_10_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        $this->addHooks([
            ['hook' => 'after_category_save', 'method' => 'after_category_save'],
            ['hook' => 'after_category_delete', 'method' => 'after_category_delete'],
        ]);
    }
}
