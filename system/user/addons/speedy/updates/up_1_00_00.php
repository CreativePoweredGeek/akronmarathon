<?php

use BoldMinded\Speedy\Library\Basee\Setting;
use BoldMinded\Speedy\Library\Basee\Update\AbstractUpdate;
use BoldMinded\Speedy\Model\CacheBreaking;
use BoldMinded\Speedy\Model\Tag;

class Update_1_00_00 extends AbstractUpdate
{
    /** @var array */
    private static $actions = [
        '_break_cache',
        '_break_entry_cache',
        '_is_frontedit_enabled',
    ];

    /** @var string[] */
    private static $hooks = [
        'core_boot',
        'cp_js_end',
        // Pre-escape
        'template_fetch_template',
        // Cache breaking
        'after_channel_entry_save',
        'after_channel_entry_delete',
        'after_comment_save',
        'after_comment_delete',

        // @todo: Add cache breaking for other add-ons (e.g. Low Reorder)
    ];

    public function doUpdate()
    {
        // Install module
        ee('Model')->make('Module', [
            'module_name'        => ucfirst(SPEEDY_CLASS_NAME),
            'module_version'     => SPEEDY_VERSION,
            'has_cp_backend'     => 'y',
            'has_publish_fields' => 'n',
        ])->save();

        // Install actions
        foreach (self::$actions as $method) {
            ee('Model')->make('Action', [
                'class'  => ucfirst(SPEEDY_CLASS_NAME),
                'method' => $method,
            ])->save();
        }

        foreach (self::$hooks as $hook) {
            $this->addHooks([
                ['hook' => $hook, 'method' => $hook],
            ]);
        }

        // Create tags table
        if (ee()->db->table_exists(Tag::getMetaData('table_name')) !== true) {
            ee()->dbforge->add_key(Tag::getMetaData('primary_key'), true);
            ee()->dbforge->add_field(Tag::getMetaData('table_columns'));
            ee()->dbforge->create_table(Tag::getMetaData('table_name'));
        }

        // Create cache breaking table
        if (ee()->db->table_exists(CacheBreaking::getMetaData('table_name')) !== true) {
            ee()->dbforge->add_key(CacheBreaking::getMetaData('primary_key'), true);
            ee()->dbforge->add_field(CacheBreaking::getMetaData('table_columns'));
            ee()->dbforge->create_table(CacheBreaking::getMetaData('table_name'));
        }

        // Create driver configuration table
        if (ee()->db->table_exists('speedy_driver_configuration') !== true) {
            ee()->dbforge->add_field([
                'id'       => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'site_id'  => ['type' => 'int', 'constraint' => 4, 'default' => 1],
                'driver'   => ['type' => 'varchar', 'constraint' => 50, 'null' => false],
                'settings' => ['type' => 'text', 'default' => null],
            ]);
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->add_key(['site_id', 'driver']);
            ee()->dbforge->create_table('speedy_driver_configuration');
        }

        /** @var Setting $setting */
        $setting = ee('speedy:Setting');
        $setting->createTable();
        $setting->save([
            'installed_date' => time(),
            'installed_version' => SPEEDY_VERSION,
            'installed_build' => SPEEDY_BUILD_VERSION,
        ]);

        $this->migrateCeCache();
    }

    private function migrateCeCache()
    {
        $db = ee('db');

        $installed = ee()->addons->get_installed('modules');

        if (
            !array_key_exists('ce_cache', $installed) &&
            !$db->table_exists('ce_cache_breaking') &&
            !$db->table_exists('ce_cache_tagged_items')
        ) {
            return;
        }

        /** @var CI_DB_result $breaks */
        $breaks = $db->where('items !=', '')->get('ce_cache_breaking');

        foreach ($breaks->result() as $row) {
            $model = ee('Model')->make('speedy:CacheBreaking');
            $model->entity_id = $row->channel_id;
            $model->tags = explode('|', $row->tags);
            $model->items = explode('|', $row->items);
            $model->refresh = $row->refresh;

            $model->save();
        }

        /** @var CI_DB_result $items */
        $items = $db->get('ce_cache_tagged_items');

        foreach ($items->result() as $row) {
            $model = ee('Model')->make('speedy:Tag');

            $key = preg_replace(
                '/ce_cache\/(.*?)\//',
                SPEEDY_CLASS_NAME .'/'. ee()->config->item('site_short_name') . '/',
                $row->item_id
            );

            $model->key = $key;
            $model->tag = $row->tag;

            $model->save();
        }

        // Update template tags
        $replacements = [
            'exp:ce_cache:it' => 'exp:speedy:fragment',
            'exp:ce_cache:stat:ic' => 'exp:speedy:static',
            'exp:ce_cache:escape' => 'exp:speedy:escape',
            'exp:ce_cache:clear' => 'exp:speedy:clear',
            'exp:ce_cache:add_tag' => 'speedy:add_tag',
            'exp:ce_cache:until' => 'speedy:set_ttl',

            // Tags that Speedy does not have
            'exp:ce_cache:save' => 'exp:speedy:not_available',
            'exp:ce_cache:get' => 'exp:speedy:not_available',
            'exp:ce_cache:delete' => 'exp:speedy:not_available',
            'exp:ce_cache:get_metadata' => 'exp:speedy:not_available',
            'exp:ce_cache:is_supported' => 'exp:speedy:not_available',
        ];

        $templates = ee('Model')->get('Template')->all();

        foreach ($templates as $template) {
            $template->template_data = str_ireplace(
                array_keys($replacements),
                array_values($replacements),
                $template->template_data
            );
            $template->edit_date = ee()->localize->now;
        }

        $templates->save();
    }
}
