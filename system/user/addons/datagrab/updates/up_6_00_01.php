<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_6_00_01 extends AbstractUpdate
{
    public function doUpdate()
    {
        ee()->load->dbforge();
        $db = ee('db');
        $prefix = $db->dbprefix;

        // Remove last remnants of Ajw.
        if ($db->table_exists('ajw_datagrab')) {
            ee()->dbforge->drop_table('ajw_datagrab');
        }

        ee('db')->query(
            "UPDATE `{$prefix}extensions` SET `hook` = replace(hook, 'ajw_datagrab_', 'datagrab_')"
        );
    }
}
