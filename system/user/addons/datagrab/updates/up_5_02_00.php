<?php


use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_5_02_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        if (!ee('db')->field_exists('migration', 'datagrab')) {
            ee()->load->dbforge();
            ee()->dbforge->add_column('datagrab', [
                'migration' => [
                    'type' => 'int',
                    'default' => 0,
                ],
            ]);
        }
    }
}
