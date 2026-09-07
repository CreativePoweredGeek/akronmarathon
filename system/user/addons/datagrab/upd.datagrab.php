<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Updater;

/**
 * @package     ExpressionEngine
 * @subpackage  Module
 * @category    DataGrab
 * @author      Brian Litzinger
 * @copyright   Copyright (c) 2022 - BoldMinded, LLC
 * @link        https://boldminded.com/add-ons/datagrab
 * @license
 *
 * All rights reserved.
 *
 * This source is commercial software. Use of this software requires a
 * site license for each domain it is used on. Use of this software or any
 * of its source code without express written permission in the form of
 * a purchased commercial or other license is prohibited.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 *
 * As part of the license agreement for this software, all modifications
 * to this source must be submitted to the original author for review and
 * possible inclusion in future releases. No compensation will be provided
 * for patches, although where possible we will attribute each contribution
 * in file revision notes. Submitting such modifications constitutes
 * assignment of copyright to the original author (Brian Litzinger and
 * BoldMinded, LLC) for such modifications. If you do not wish to assign
 * copyright to the original author, your license to  use and modify this
 * source is null and void. Use of this software constitutes your agreement
 * to this clause.
 */
class Datagrab_upd
{
    /**
     * @var string
     */
    public $version = DATAGRAB_VERSION;

    /**
     * @var array
     */
    private $hookTemplate = [
        'class' => 'Datagrab_ext',
        'settings' => '',
        'priority' => 5,
        'version' => DATAGRAB_VERSION,
        'enabled' => 'y',
    ];

    public function install()
    {
        $updater = new Updater();

        // If it exists it means it's an upgrade to 4.0 from 3.x, so start at 4.0
        if (ee()->db->table_exists('ajw_datagrab')) {
            // Add to modules table
            ee()->db->insert('modules', [
                'module_name' => 'Datagrab',
                'module_version' => DATAGRAB_VERSION,
                'has_cp_backend' => 'y'
            ]);

            $updater
                ->setFilePath(PATH_THIRD . 'datagrab/updates')
                ->setHookTemplate($this->hookTemplate)
                ->fetchUpdates('3.0.3')
                ->runUpdates();

            $this->updateVersion();

            return true;
        }

        $updater = new Updater();
        $updater
            ->setFilePath(PATH_THIRD . 'datagrab/updates')
            ->setHookTemplate($this->hookTemplate)
            ->fetchUpdates(0, true)
            ->runUpdates();

        $this->updateVersion();

        return true;
    }

    public function uninstall()
    {
        ee()->db->select('module_id');
        $query = ee()->db->get_where('modules', ['module_name' => 'Datagrab']);

        ee()->db->where('module_id', $query->row('module_id'));
        ee()->db->delete('module_member_roles');

        ee()->db->where('module_name', 'Datagrab');
        ee()->db->delete('modules');

        ee()->db->where('class', 'Datagrab');
        ee()->db->delete('actions');

        ee()->db->where('class', 'Datagrab_mcp');
        ee()->db->delete('actions');

        ee()->load->dbforge();
        ee()->dbforge->drop_table('datagrab');
        ee()->dbforge->drop_table('datagrab_endpoints');
        ee()->dbforge->drop_table('datagrab_failed_jobs');
        ee()->dbforge->drop_table('datagrab_jobs');
        ee()->dbforge->drop_table('datagrab_settings');

        ee('db')->where('class', 'Datagrab_ext')->delete('extensions');

        return true;
    }

    /**
     * Module Updater
     *
     * @param string $current
     * @return bool true
     */
    public function update($current = '')
    {
        $updater = new Updater();

        try {
            $updater
                ->setFilePath(PATH_THIRD . 'datagrab/updates')
                ->setHookTemplate($this->hookTemplate)
                ->fetchUpdates($current)
                ->runUpdates();

            $this->updateVersion();

        } catch (\Exception $exception) {
            show_error($exception->getMessage());
        }

        return true;
    }

    private function updateVersion()
    {
        ee()->db->update('modules', ['module_version' => $this->version], ['module_name' => 'Datagrab']);
    }
}
