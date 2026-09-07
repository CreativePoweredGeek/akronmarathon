<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Setting;
use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_4_00_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        ee()->db->data_cache = [];

        $this->addHooks([
            [
                'version' => DATAGRAB_VERSION,
                'class' => 'Datagrab_ext',
                'hook' => 'cp_js_end',
                'method' => 'cp_js_end',
                'settings' => '',
                'priority' => 5,
                'enabled' => 'y'
            ],
        ]);

        /** @var Setting $setting */
        $setting = ee('datagrab:Setting');
        $setting->createTable();
        $setting->save([
            'installed_date' => time(),
            'installed_version' => DATAGRAB_VERSION,
            'installed_build' => DATAGRAB_BUILD_VERSION,
        ]);

        ee()->load->library('smartforge');
        ee()->smartforge->rename_table('ajw_datagrab', 'datagrab');

        if (!ee('db')->field_exists('import_entryids', 'datagrab')) {
            ee()->dbforge->add_column('datagrab', [
                'import_entryids' => [
                    'type' => 'text',
                    'null' => true,
                ],
            ]);
        }

        // Keep the same action IDs for smoother transition
        ee('db')->update('actions', [
            'class' => 'Datagrab'
        ], [
            'class' => 'Ajw_datagrab'
        ]);

        // Update the settings to they are not prefixed with ajw_
        $imports = ee('db')->get('datagrab')->result_array();

        foreach ($imports as $import) {
            $settings = unserialize($import['settings']);
            $settings['import']['type'] = str_replace('ajw_', '', $settings['import']['type']);

            ee('db')->update('datagrab', [
                'settings' => serialize($settings)
            ], [
                'id' => $import['id']
            ]);
        }
    }
}
