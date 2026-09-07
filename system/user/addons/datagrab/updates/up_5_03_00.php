<?php


use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;
use BoldMinded\DataGrab\Model\Endpoint;

class Update_5_03_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        if (ee()->db->table_exists(Endpoint::getMetaData('table_name')) !== true) {
            ee()->load->dbforge();
            ee()->dbforge->add_key(Endpoint::getMetaData('primary_key'), true);
            ee()->dbforge->add_field(Endpoint::getMetaData('table_columns'));
            ee()->dbforge->create_table(Endpoint::getMetaData('table_name'));
        }

        $this->addActions([
            [
                'class' => 'Datagrab',
                'method' => 'run_endpoint',
                'csrf_exempt' => 1,
            ],
        ]);
    }
}
