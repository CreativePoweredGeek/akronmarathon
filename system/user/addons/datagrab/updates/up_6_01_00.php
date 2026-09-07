<?php


use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_6_01_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        if (!ee('db')->field_exists('version', 'datagrab')) {
            ee()->load->dbforge();
            ee()->dbforge->add_column('datagrab', [
                'version' => [
                    'type' => 'float',
                    'default' => 0,
                ],
            ]);
        }
    }
}
