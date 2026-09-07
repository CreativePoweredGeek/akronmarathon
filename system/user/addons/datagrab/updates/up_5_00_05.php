<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_5_00_05 extends AbstractUpdate
{
    public function doUpdate()
    {
        if (!ee('db')->field_exists('delete_entryids', 'datagrab')) {
            ee()->load->dbforge();
            ee()->dbforge->add_column('datagrab', [
                'delete_entryids' => [
                    'type' => 'text',
                    'null' => true,
                ],
            ]);
        }

        if (!ee('db')->field_exists('last_delete_record', 'datagrab')) {
            ee()->load->dbforge();
            ee()->dbforge->add_column('datagrab', [
                'last_delete_record' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'default' => 0
                ],
            ]);
        }

        $db = ee('db');
        $pre = $db->dbprefix;

        if (
            ee('db')->field_exists('delete_records', 'datagrab') &&
            !ee('db')->field_exists('total_delete_records', 'datagrab')
        ) {
            $db->query("ALTER TABLE `" . $pre . "datagrab` CHANGE COLUMN `delete_records` `total_delete_records` int unsigned default 0");
        }

        $imports = $db->get('datagrab');

        // Make sure everything is an in defaulted to 0, not null. This is the result of not defining the defaults sooner in the schema.
        foreach ($imports->result_array() as $import) {
            if (!is_int($import['total_records'])) {
                $import['total_records'] = 0;
            }
            if (!is_int($import['last_record'])) {
                $import['last_record'] = 0;
            }
            if (!is_int($import['total_delete_records'])) {
                $import['total_delete_records'] = 0;
            }
            if (!is_int($import['error_records'])) {
                $import['error_records'] = 0;
            }

            $id = $import['id'];
            unset($import['id']);

            $db
                ->where('id', $id)
                ->update('datagrab', $import);
        }
    }
}
