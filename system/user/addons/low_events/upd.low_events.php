<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Low Events Update class
 *
 * @package        low_events
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-events
 * @copyright      Copyright (c) 2012-2017, Low
 */
include_once "addon.setup.php";
use Low\Events\FluxCapacitor\Base\Upd;

class Low_events_upd extends Upd
{
    // --------------------------------------------------------------------
    // PROPERTIES
    // --------------------------------------------------------------------

    /**
     * This version
     *
     * @access      public
     * @var         string
     */
    public $version = LOW_EVENTS_VERSION;

    /**
     * Package name
     *
     * @access      public
     * @var         string
     */
    private $package;

    /**
     * Class name
     *
     * @access      private
     * @var         array
     */
    private $class_name;

    /**
     * Actions used
     *
     * @access      private
     * @var         array
     */
    private $actions = array();

    /**
     * Extension hooks
     *
     * @var        array
     * @access     private
     */
    private $hooks = array();

    // --------------------------------------------------------------------
    // METHODS
    // --------------------------------------------------------------------

    /**
     * Constructor
     *
     * @access     public
     * @return     void
     */
    public function __construct()
    {
        parent::__construct();

        // Set the package name
        $this->package = basename(__DIR__);

        // --------------------------------------
        // Load stuff
        // --------------------------------------

        ee()->load->model('low_events_event_model');

        // --------------------------------------
        // Set class name
        // --------------------------------------

        $this->class_name = ucfirst($this->package);
    }

    // --------------------------------------------------------------------

    /**
     * Install the module
     *
     * @access      public
     * @return      bool
     */
    public function install()
    {
        // --------------------------------------
        // Install tables
        // --------------------------------------

        ee()->low_events_event_model->install();

        // --------------------------------------
        // Add row to modules table
        // --------------------------------------

        ee()->db->insert('modules', array(
            'module_name' => $this->class_name,
            'module_version' => $this->version,
            'has_cp_backend' => 'n'
        ));

        // --------------------------------------
        // Add rows to action table
        // --------------------------------------

        foreach ($this->actions as $row) {
            list($class, $method) = $row;

            ee()->db->insert('actions', array(
                'class' => $class,
                'method' => $method
            ));
        }

        // --------------------------------------
        // Add rows to extensions table
        // --------------------------------------

        foreach ($this->hooks as $hook) {
            $this->_add_hook($hook);
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Uninstall the module
     *
     * @return  bool
     */
    public function uninstall()
    {
        // --------------------------------------
        // get module id
        // --------------------------------------

        $query = ee()->db->select('module_id')
            ->from('modules')
            ->where('module_name', $this->class_name)
            ->get();

        // --------------------------------------
        // remove references from module_member_groups
        // --------------------------------------

        ee()->db->where('module_id', $query->row('module_id'));
        ee()->db->delete(version_compare(APP_VER, '6.0', '>=') ? 'module_member_roles' : 'module_member_groups');

        // --------------------------------------
        // remove references from modules
        // --------------------------------------

        ee()->db->where('module_name', $this->class_name);
        ee()->db->delete('modules');

        // --------------------------------------
        // remove references from actions
        // --------------------------------------

        ee()->db->where_in('class', array($this->class_name, $this->class_name . '_mcp'));
        ee()->db->delete('actions');

        // --------------------------------------
        // remove references from extensions
        // --------------------------------------

        ee()->db->where('class', $this->class_name . '_ext');
        ee()->db->delete('extensions');

        // --------------------------------------
        // Uninstall tables
        // --------------------------------------

        ee()->low_events_event_model->uninstall();

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Update the module
     *
     * @return  bool
     */
    public function update($current = '')
    {
        // --------------------------------------
        // Same version? A-okay, daddy-o!
        // --------------------------------------

        if ($current == '' or version_compare($current, $this->version) === 0) {
            return false;
        }

        // Update to next version
        if (version_compare($current, '1.1.0', '<')) {
            // Add LS hook
            // $this->_add_hook($this->hooks[0]);
        }

        // Update to 1.2.0 version
        if (version_compare($current, '1.2.0', '<')) {
            // Get all fields
            $query = ee()->db->select('field_id, field_settings')
                ->from('channel_fields')
                ->where('field_type', $this->package)
                ->get();

            // Loop through results to change settings
            foreach ($query->result() as $row) {
                // Read current settings
                $settings = unserialize(base64_decode($row->field_settings));

                // Check default values for overwrite dates
                $val = (isset($settings['overwrite_dates']) &&
                    $settings['overwrite_dates'] == 'y') ? 'y' : 'n';

                // Set new values based on previous
                $settings['overwrite_entry_date'] = $val;
                $settings['overwrite_expiration_date'] = $val;

                // Remove overwrite_dates setting
                unset($settings['overwrite_dates']);

                // Encode for saving
                $settings = base64_encode(serialize($settings));

                // Update row
                ee()->db->update(
                    'channel_fields',
                    array('field_settings' => $settings),
                    "field_id = '{$row->field_id}'"
                );
            }
        }

        // Update to 1.4.0 version
        if (version_compare($current, '1.4.0', '<')) {
            // Remove extension in favor of Low Search filter
            ee()->db->where('class', $this->class_name . '_ext');
            ee()->db->delete('extensions');
        }

        // Return TRUE to update version number in DB
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Add hook to table
     *
     * @access  private
     * @param   string
     * @return  void
     */
    private function _add_hook($hook)
    {
        ee()->db->insert('extensions', array(
            'class' => $this->class_name . '_ext',
            'method' => $hook,
            'hook' => $hook,
            'settings' => '',
            'priority' => 5,
            'version' => $this->version,
            'enabled' => 'y'
        ));
    }
} // End class

/* End of file upd.low_events.php */
