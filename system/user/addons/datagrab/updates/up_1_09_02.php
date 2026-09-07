<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_1_09_02 extends AbstractUpdate
{
    public function doUpdate()
    {
        ee()->db->data_cache = [];

        if (!ee('db')->field_exists('import_id', 'ajw_datagrab')) {
            ee()->load->dbforge();

            // Add batch deletion fields
            $fields = [
                'import_id' => [
                    'type' => 'varchar',
                    'constraint' => 255,
                    'default' => ''
                ],
                'last_started' => [
                    'type' => 'int',
                    'constraint' => '10',
                    'unsigned' => true,
                    'default' => 0,
                ]
            ];

            ee()->dbforge->add_column('ajw_datagrab', $fields, 'last_run');
        }
    }
}
