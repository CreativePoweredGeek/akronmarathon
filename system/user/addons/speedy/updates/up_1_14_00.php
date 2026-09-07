<?php

use BoldMinded\Speedy\Library\Basee\Update\AbstractUpdate;
use BoldMinded\Speedy\Model\Diagnostics;
use BoldMinded\Speedy\Model\Url;

class Update_1_14_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        ee()->db->data_cache = [];

        if (ee()->db->table_exists(Url::getMetaData('table_name')) !== true) {
            ee()->dbforge->add_key(Url::getMetaData('primary_key'), true);
            ee()->dbforge->add_field(Url::getMetaData('table_columns'));
            ee()->dbforge->create_table(Url::getMetaData('table_name'));
        }
    }
}
