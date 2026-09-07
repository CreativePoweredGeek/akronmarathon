<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_1_09_01 extends AbstractUpdate
{
    public function doUpdate()
    {
        ee()->db->data_cache = [];

        if (!ee('db')->field_exists('status', 'ajw_datagrab')) {
            ee()->load->dbforge();

            // Add batch import field
            $fields = [
                'status' => [
                    'type' => 'varchar',
                    'constraint' => 255,
                    'default' => ''
                ],
                'last_record' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'default' => 0
                ],
                "total_records" => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'default' => 0
                ]
            ];

            ee()->dbforge->add_column('ajw_datagrab', $fields, 'settings');
        }
    }
}
