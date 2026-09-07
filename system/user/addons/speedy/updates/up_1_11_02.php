<?php

use BoldMinded\Speedy\Library\Basee\Update\AbstractUpdate;
use BoldMinded\Speedy\Model\Diagnostics;

class Update_1_11_02 extends AbstractUpdate
{
    public function doUpdate()
    {
        $prefix = ee('db')->dbprefix;
        $db = ee('db');

        if (!$db->field_exists('site_id', 'speedy_tags')) {
            $db->query(sprintf(
                'ALTER TABLE `%s` ADD `%s` %s AFTER %s',
                $prefix . 'speedy_tags',
                'site_id',
                'int(4) DEFAULT 1',
                'id'
            ));
        }
    }
}
