<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_1_07_02 extends AbstractUpdate
{
    public function doUpdate()
    {
        ee()->db->data_cache = [];

        if (!ee('db')->field_exists('passkey', 'ajw_datagrab')) {
            ee()->load->dbforge();

            // Add a passkey field
            $fields = [
                'passkey' => [
                    'type' => 'varchar',
                    'constraint' => 255,
                    'default' => ''
                ]
            ];

            ee()->dbforge->add_column('ajw_datagrab', $fields, 'description');
        }
    }
}
