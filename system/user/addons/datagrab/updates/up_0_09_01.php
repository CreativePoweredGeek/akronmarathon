<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_0_09_01 extends AbstractUpdate
{
    public function doUpdate()
    {
        ee()->db->data_cache = [];

        if (!ee('db')->field_exists('site_id', 'ajw_datagrab')) {
            // Add a site_id field for MSM sites
            $fields = [
                'site_id' => [
                    'type' => 'INT',
                    'constraint' => 4,
                    'unsigned' => true,
                    'default' => 1
                ]
            ];

            ee()->load->dbforge();
            ee()->dbforge->add_column('ajw_datagrab', $fields, 'id');
        }
    }
}
