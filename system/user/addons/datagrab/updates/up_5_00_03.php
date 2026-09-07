<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_5_00_03 extends AbstractUpdate
{
    public function doUpdate()
    {
        // Dropped on 5.0.1, re-added in 5.0.3
        if (!ee('db')->field_exists('import_entryids', 'datagrab')) {
            ee()->load->dbforge();
            ee()->dbforge->add_column('datagrab', [
                'import_entryids' => [
                    'type' => 'text',
                    'null' => true,
                ],
            ]);
        }


        $db = ee('db');
        $pre = $db->dbprefix;

        if (!ee('db')->field_exists('total_records', 'datagrab')) {
            $db->query("ALTER TABLE `" . $pre . "datagrab` CHANGE COLUMN `import_totalrecords` `total_records` int unsigned");
        }
        if (!ee('db')->field_exists('last_record', 'datagrab')) {
            $db->query("ALTER TABLE `" . $pre . "datagrab` CHANGE COLUMN `import_lastrecord` `last_record` int unsigned");
        }
        if (!ee('db')->field_exists('status', 'datagrab')) {
            $db->query("ALTER TABLE `" . $pre . "datagrab` CHANGE COLUMN `import_status` `status` varchar(255)");
        }
        if (!ee('db')->field_exists('delete_records', 'datagrab')) {
            $db->query("ALTER TABLE `" . $pre . "datagrab` CHANGE COLUMN `import_deleterecords` `delete_records` int unsigned");
        }
        if (!ee('db')->field_exists('error_records', 'datagrab')) {
            $db->query("ALTER TABLE `" . $pre . "datagrab` CHANGE COLUMN `import_errorrecords` `error_records` int unsigned");
        }
    }
}
