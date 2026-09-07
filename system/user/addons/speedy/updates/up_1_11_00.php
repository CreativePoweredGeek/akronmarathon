<?php

use BoldMinded\Speedy\Library\Basee\Update\AbstractUpdate;
use BoldMinded\Speedy\Model\Diagnostics;

class Update_1_11_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        $prefix = ee('db')->dbprefix;
        $db = ee('db');

        if (!$db->field_exists('entity_id', 'speedy_breaking')) {
            $db->query(sprintf(
                'ALTER TABLE `%s` CHANGE `%s` `%s` %s',
                $prefix . 'speedy_breaking',
                'channel_id',
                'entity_id',
                'int (10)'
            ));
        }

        if (!$db->field_exists('entity_type', 'speedy_breaking')) {
            $db->query(sprintf(
                'ALTER TABLE `%s` ADD `%s` %s AFTER %s',
                $prefix . 'speedy_breaking',
                'entity_type',
                'text',
                'id'
            ));

            // Default to channel
            $db->update('speedy_breaking', [
                'entity_type' => 'channel'
            ]);
        }
    }
}
