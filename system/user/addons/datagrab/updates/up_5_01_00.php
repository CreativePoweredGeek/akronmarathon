<?php


use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_5_01_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        if (!ee('db')->field_exists('order', 'datagrab')) {
            ee()->load->dbforge();
            ee()->dbforge->add_column('datagrab', [
                'order' => [
                    'type' => 'int',
                    'default' => 0,
                ],
            ]);
        }

        $this->addActions([
            [
                'class' => 'Datagrab',
                'method' => 'sort_imports',
            ],
        ]);
    }
}
