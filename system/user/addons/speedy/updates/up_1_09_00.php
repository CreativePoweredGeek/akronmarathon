<?php

use BoldMinded\Speedy\Library\Basee\Update\AbstractUpdate;
use BoldMinded\Speedy\Model\Diagnostics;

class Update_1_09_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        ee()->db->data_cache = [];

        if (ee()->db->table_exists(Diagnostics::getMetaData('table_name')) !== true) {
            ee()->dbforge->add_key(Diagnostics::getMetaData('primary_key'), true);
            ee()->dbforge->add_field(Diagnostics::getMetaData('table_columns'));
            ee()->dbforge->create_table(Diagnostics::getMetaData('table_name'));
        }
    }
}
