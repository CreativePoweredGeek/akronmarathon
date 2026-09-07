<?php

use BoldMinded\Speedy\Library\Basee\Update\AbstractUpdate;
use BoldMinded\Speedy\Model\DatabaseDriver;
use BoldMinded\Speedy\Model\DatabaseDriverStats;

class Update_1_07_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        if (ee()->db->table_exists(DatabaseDriver::getMetaData('table_name')) !== true) {
            ee()->dbforge->add_key(DatabaseDriver::getMetaData('primary_key'), true);
            ee()->dbforge->add_field(DatabaseDriver::getMetaData('table_columns'));
            ee()->dbforge->create_table(DatabaseDriver::getMetaData('table_name'));
        }

        if (ee()->db->table_exists(DatabaseDriverStats::getMetaData('table_name')) !== true) {
            ee()->dbforge->add_key(DatabaseDriverStats::getMetaData('primary_key'), true);
            ee()->dbforge->add_field(DatabaseDriverStats::getMetaData('table_columns'));
            ee()->dbforge->create_table(DatabaseDriverStats::getMetaData('table_name'));
        }
    }
}
