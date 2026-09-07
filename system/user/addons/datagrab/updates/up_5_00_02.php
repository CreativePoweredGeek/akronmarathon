<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_5_00_02 extends AbstractUpdate
{
    public function doUpdate()
    {
        if (!ee('db')->field_exists('error_records', 'datagrab')) {
            ee()->load->dbforge();
            ee()->dbforge->add_column('datagrab', [
                'error_records' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'default' => 0
                ],
            ]);
        }
    }
}
