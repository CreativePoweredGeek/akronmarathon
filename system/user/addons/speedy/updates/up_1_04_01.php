<?php

use BoldMinded\Speedy\Library\Basee\Update\AbstractUpdate;

class Update_1_04_01 extends AbstractUpdate
{
    public function doUpdate()
    {
        $this->addHooks([
            ['hook' => 'cp_js_end', 'method' => 'cp_js_end'],
        ]);
    }
}
