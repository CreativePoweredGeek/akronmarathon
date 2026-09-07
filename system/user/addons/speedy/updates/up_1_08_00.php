<?php

use BoldMinded\Speedy\Library\Basee\Update\AbstractUpdate;
use BoldMinded\Speedy\Model\DatabaseDriver;
use BoldMinded\Speedy\Model\DatabaseDriverStats;

class Update_1_08_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        ee()->db->data_cache = [];

        if (!ee('db')->field_exists('statuses', 'speedy_breaking')) {
            ee()->load->dbforge();

            $fields = [
                'statuses' => [
                    'type' => 'text',
                    'default' => null
                ],
                'categories' => [
                    'type' => 'text',
                    'default' => null
                ]
            ];

            ee()->dbforge->add_column('speedy_breaking', $fields);
        }
    }
}
