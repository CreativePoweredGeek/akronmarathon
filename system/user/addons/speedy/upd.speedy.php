<?php

use BoldMinded\Speedy\Library\Basee\Setting;
use BoldMinded\Speedy\Library\Basee\Updater;
use BoldMinded\Speedy\Model\CacheBreaking;
use BoldMinded\Speedy\Model\DatabaseDriver;
use BoldMinded\Speedy\Model\DatabaseDriverStats;
use BoldMinded\Speedy\Model\Tag;

class Speedy_upd
{
    /** @var string */
    public $version;

    /** @var string */
    public $module_name;

    /** @var string */
    public $module_class;

    /**
     * Speedy_upd constructor.
     */
    public function __construct()
    {
        ee()->load->dbforge();
        $this->version = SPEEDY_VERSION;
        $this->module_name = SPEEDY_CLASS_NAME;
        $this->module_class = ucfirst(SPEEDY_CLASS_NAME);
    }

    /**
     * Install the module and register any actions.
     *
     * @return bool
     */
    public function install()
    {
        $updater = new Updater();
        $updater
            ->setFilePath(PATH_THIRD.'speedy/updates')
            ->setHookTemplate([
                'class' => 'Speedy_ext',
                'settings' => '',
                'priority' => 5,
                'version' => SPEEDY_VERSION,
                'enabled' => 'y',
            ])
            ->fetchUpdates(0, true)
            ->runUpdates();

        return true;
    }

    /**
     * Uninstall the module and deregister any actions.
     *
     * @return bool
     */
    public function uninstall()
    {
        // Uninstall module
        ee('Model')->get('Module')
            ->filter('module_name', $this->module_class)
            ->delete();

        // Uninstall actions
        ee('Model')->get('Action')
            ->filter('class', $this->module_class)
            ->delete();

        // Drop custom tables
        ee()->dbforge->drop_table(Tag::getMetaData('table_name'));
        ee()->dbforge->drop_table(CacheBreaking::getMetaData('table_name'));
        ee()->dbforge->drop_table('speedy_driver_configuration');

        ee()->db->where('class', SPEEDY_EXT);
        ee()->db->delete('extensions');

        return true;
    }

    /**
     * Perform any database updates between versions.
     *
     * @param string $current
     * @return bool
     */
    public function update($current = '')
    {
        ee()->load->dbforge();

        try {
            $updater = new Updater();
            $updater
                ->setFilePath(PATH_THIRD.'speedy/updates')
                ->setHookTemplate([
                    'class' => 'Speedy_ext',
                    'settings' => '',
                    'priority' => 5,
                    'version' => SPEEDY_VERSION,
                    'enabled' => 'y',
                ])
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
        ee()->db->update('modules', ['module_version' => $this->version], ['module_name' => 'Speedy']);
    }
}
