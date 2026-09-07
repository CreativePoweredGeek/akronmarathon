<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_0_01_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        // Initial install scripts. Use the update files to re-create the history of DataGrab updates.
        ee()->load->dbforge();
        ee()->db->data_cache = [];

        // Add to modules table
        ee()->db->insert('modules', [
            'module_name' => 'Datagrab',
            'module_version' => '0.1.0',
            'has_cp_backend' => 'y'
        ]);

        // Add an action to call imports from templates
        ee()->db->insert('actions', [
            'class' => 'Datagrab',
            'method' => 'run_action'
        ]);

        // Create table for saved imports if it is a real new install
        $fields = [
            'id' => [
                'type' => 'int',
                'constraint' => '6',
                'unsigned' => true,
                'auto_increment' => true
            ],
            'name' => [
                'type' => 'varchar',
                'constraint' => '255',
                'null' => false
            ],
            'description' => [
                'type' => 'text',
                'null' => false
            ],
            'settings' => [
                'type' => 'text'
            ],
            'last_run' => [
                'type' => 'int',
                'constraint' => '10',
                'unsigned' => true,
                'default' => 0,
            ],
        ];

        ee()->dbforge->add_field($fields);
        ee()->dbforge->add_key('id', true);
        ee()->dbforge->create_table('ajw_datagrab');
    }
}
