<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_5_00_04 extends AbstractUpdate
{
    public function doUpdate()
    {
        $db = ee('db');
        $pre = $db->dbprefix;

        $db->query("ALTER TABLE `" . $pre . "datagrab` CHANGE COLUMN `total_records` `total_records` int unsigned default 0");
        $db->query("ALTER TABLE `" . $pre . "datagrab` CHANGE COLUMN `last_record` `last_record` int unsigned default 0");
        $db->query("ALTER TABLE `" . $pre . "datagrab` CHANGE COLUMN `delete_records` `delete_records` int unsigned default 0");
        $db->query("ALTER TABLE `" . $pre . "datagrab` CHANGE COLUMN `error_records` `error_records` int unsigned default 0");
    }
}
