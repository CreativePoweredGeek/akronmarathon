<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use Low\Events\Library\Date;
use Low\Events\Library\Field;
use Low\Events\Library\Param;
use Low\Events\Library\Sieve;

/**
 * Low Events Fieldtype class
 *
 * @package        low_events
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-events
 * @copyright      Copyright (c) 2012-2017, Low
 */
include_once "addon.setup.php";
use Low\Events\FluxCapacitor\Base\Ft;

class Low_events_ft extends Ft
{
    public $settings;
    // Debug constant, used by load_assets
    public const DEBUG = false;

    // --------------------------------------------------------------------
    //  PROPERTIES
    // --------------------------------------------------------------------

    /**
     * Info array
     *
     * @access     public
     * @var        array
     */
    public $info = array('version' => LOW_EVENTS_VERSION);

    /**
     * Does fieldtype work in var pair
     *
     * @access     public
     * @var        bool
     */
    public $has_array_data = true;

    /**
     * BWF compat
     */
    public $ep_better_workflow_use_save_method = true;

    // --------------------------------------------------------------------

    /**
     * Package name
     *
     * @access      public
     * @var         string
     */
    private $package;

    /**
     * Default settings
     *
     * @access     private
     * @var        array
     */
    private $default_settings = array(
        'first_day' => '0',
        'time_interval' => '30',
        'default_duration' => '60',
        'default_all_day' => 'y',
        'hide_all_day' => 'n',
        'hide_end_date' => 'n',
        'overwrite_entry_date' => 'n',
        'overwrite_expiration_date' => 'n'
    );

    /**
     * Days of the week
     *
     * @access     private
     * @var        array
     */
    private $_dotw = array(
        'Sunday', 'Monday', 'Tuesday', 'Wednesday',
        'Thursday', 'Friday', 'Saturday'
    );

    /**
     * Shortcut to Low_events_date lib
     *
     * @access     private
     * @var        Object
     */
    private $date;

    /**
     * Shortcut to Low_events_event_model lib
     *
     * @access     private
     * @var        Object
     */
    private $model;

    /**
     * Site id shortcut
     *
     * @access     private
     * @var        int
     */
    private $site_id;

    /**
     * Shortcut to today's date
     *
     * @access     private
     * @var        string
     */
    private $today;

    // --------------------------------------------------------------------
    //  METHODS
    // --------------------------------------------------------------------

    /**
     * Sets up basic needs for this fieldtype
     *
     * @return  void
     */
    private function _setup()
    {
        // Set the package name
        $this->package = basename(__DIR__);

        // --------------------------------------
        // Load stuff
        // --------------------------------------
        ee()->lang->loadfile($this->package);
        ee()->load->add_package_path(PATH_THIRD . $this->package);
        ee()->load->helper('low_events');
        ee()->load->helper('date');
        ee()->load->model('low_events_event_model');

        // --------------------------------------
        // Shortcuts
        // --------------------------------------

        $this->date = new Date();
        $this->model = & ee()->low_events_event_model;
        $this->site_id = ee()->config->item('site_id');
        $this->today = date('Y-m-d');
    }

    // --------------------------------------------------------------------

    /**
     * Return array with html for setting forms
     *
     * @param   array   field settings
     * @return  array
     */
    public function display_settings($settings = array())
    {
        $this->_setup();

        // -------------------------------------
        //  Build per-setting HTML
        // -------------------------------------

        $it = array();

        // Date picker start day
        $it[] = array(
            'title' => 'le_first_day',
            'fields' => array(
                'first_day' => array(
                    'type' => 'select',
                    'value' => $this->setting('first_day'),
                    'choices' => array_map('lang', $this->_dotw),
                )
            )
        );

        // Time picker time interval
        $it[] = array(
            'title' => 'le_time_interval',
            'fields' => array(
                'time_interval' => array(
                    'type' => 'select',
                    'value' => $this->setting('time_interval'),
                    'choices' => array(
                        '15' => '15',
                        '30' => '30',
                        '60' => '60'
                    ),
                )
            )
        );

        // Default duration
        $it[] = array(
            'title' => 'le_default_duration',
            'fields' => array(
                'default_duration' => array(
                    'type' => 'select',
                    'value' => $this->setting('default_duration'),
                    'choices' => array(
                        '0' => '0',
                        '30' => '30',
                        '60' => '60',
                        '120' => '120'
                    ),
                )
            )
        );

        // All Day checked by default?
        $it[] = array(
            'title' => 'le_default_all_day',
            'fields' => array(
                'default_all_day' => array(
                    'type' => 'yes_no',
                    'value' => $this->setting('default_all_day')
                )
            )
        );

        // Hide all day?
        $it[] = array(
            'title' => 'le_hide_all_day',
            'fields' => array(
                'hide_all_day' => array(
                    'type' => 'yes_no',
                    'value' => $this->setting('hide_all_day')
                )
            )
        );

        // Hide end date?
        $it[] = array(
            'title' => 'le_hide_end_date',
            'fields' => array(
                'hide_end_date' => array(
                    'type' => 'yes_no',
                    'value' => $this->setting('hide_end_date')
                )
            )
        );

        // Overwite entry date field?
        $it[] = array(
            'title' => 'le_overwrite_entry_date',
            'fields' => array(
                'overwrite_entry_date' => array(
                    'type' => 'yes_no',
                    'value' => $this->setting('overwrite_entry_date')
                )
            )
        );

        // Overwite expiration date field?
        $it[] = array(
            'title' => 'le_overwrite_expiration_date',
            'fields' => array(
                'overwrite_expiration_date' => array(
                    'type' => 'yes_no',
                    'value' => $this->setting('overwrite_expiration_date')
                )
            )
        );

        // Return the settings
        return array($this->package => array(
            'group' => $this->package,
            'label' => 'low_events_module_name',
            'settings' => $it
        ));
    }

    /**
     * Save field settings
     *
     * @access     public
     * @param      array
     * @return     array
     */
    public function save_settings($data)
    {
        $this->_setup();

        $settings = array();

        foreach ($this->default_settings as $key => $val) {
            $settings[$key] = ee('Request')->post($key, $val);
        }

        return $settings;
    }

    // --------------------------------------------------------------------

    /**
     * Delete events
     *
     * @access     public
     * @param      array
     * @return     void
     */
    public function delete($ids)
    {
        $this->_setup();
        $this->model->delete($ids, 'entry_id');
    }

    // --------------------------------------------------------------------

    /**
     * Display field in publish form
     *
     * @param   string  Current value for field
     * @return  string  HTML containing input field
     */
    public function display_field($data)
    {
        static $loaded;

        $this->_setup();

        if (! $loaded) {
            $this->_load_assets();
            $loaded = true;
        }

        // -------------------------------------
        //  All day?
        // -------------------------------------

        $default_all_day = $this->setting('default_all_day', 'n');

        // -------------------------------------
        //  Get event dates details
        // -------------------------------------

        if ($data) {
            $data = str_replace('&quot;', '"', $data);
            $data = (array) $this->_json_decode($data);
        } else {
            $data = $this->model->empty_row();

            // Shortcut to now
            $now = $this->date->now();

            // Duration
            $duration = $this->setting('default_duration', 60);

            // Time to round to
            $round = $this->setting('time_interval') * 60;

            // Round to nearest time interval
            $start = $now - ($now % $round) + $round;
            $end = $start + ($duration * 60);

            // Initiate data
            $data['start_date'] = date('Y-m-d', $start);
            $data['end_date'] = date('Y-m-d', $end);

            $data['start_time'] = date('H:i', $start);
            $data['end_time'] = date('H:i', $end);

            $data['all_day'] = $default_all_day;
        }

        // Add field name to data
        $data['field_name'] = $this->name();

        // Make sure all_day is set
        if (! isset($data['all_day'])) {
            $data['all_day'] = $default_all_day;
        }

        // Convert to 12h clock if needed
        if (($fmt = $this->_get_time_format()) == '12') {
            $data['start_time'] = $this->_time_to_12($data['start_time'], $data['start_date']);
            $data['end_time'] = $this->_time_to_12($data['end_time'], $data['end_date']);
        }

        // -------------------------------------
        //  Hide the all day or end date?
        // -------------------------------------

        $data['hide_all_day'] = ($this->setting('hide_all_day') == 'y');
        $data['hide_end_date'] = ($this->setting('hide_end_date') == 'y');

        // -------------------------------------
        //  Add some settings to data
        // -------------------------------------

        $data['field_id'] = $this->id();
        $data['data'] = array(
            'first-day' => $this->setting('first_day'),
            'time-format' => $fmt,
            'time-interval' => $this->setting('time_interval'),
            'lang-decimal' => lang('le_decimal'),
            'lang-mins' => lang('le_mins'),
            'lang-hr' => lang('le_hr'),
            'lang-hrs' => lang('le_hrs')
        );

        // -------------------------------------
        //  Build date picker interface
        // -------------------------------------

        $it = ee()->load->view('ft_events', $data, true);

        return $it;
    }

    // --------------------------------------------------------------------

    /**
     * Make sure given data is correct
     *
     * @access     private
     * @param      array
     * @return     array
     */
    private function _prep_data($data)
    {
        // -------------------------------------
        // If no data exists, bail out
        // -------------------------------------

        $data = $this->_json_decode($data);

        if (empty($data) || ! is_array($data)) {
            return;
        }

        // -------------------------------------
        // Check all_day, remove times if enabled
        // -------------------------------------

        if (isset($data['all_day']) && $data['all_day'] == 'y') {
            $data['start_time'] = $data['end_time'] = null;
        } else {
            // Set to 'n' in other cases

            $data['all_day'] = 'n';

            // Set end_time to start_time if not given
            if ($data['end_time'] === '') {
                $data['end_time'] = $data['start_time'];
            }

            // Convert to 24h clock
            if ($this->_get_time_format() == '12') {
                $data['start_time'] = $this->_time_to_24($data['start_time'], $data['start_date']);
                $data['end_time'] = $this->_time_to_24($data['end_time'], $data['end_date']);
            }
        }

        return $data;
    }

    // --------------------------------------------------------------------

    /**
     * Validate dates for saving
     *
     * @access     public
     * @param      mixed
     * @return     mixed
     */
    public function validate($data)
    {
        // Events field wasn't in the form
        if (is_null($data)) {
            return true;
        }

        $this->_setup();

        // Prep the data
        $data = $this->_prep_data($data);

        // Initiate error message array
        $errors = array();

        // -------------------------------------
        // Check if dates are valid
        // -------------------------------------

        if (! $this->_is_date($data['start_date'])) {
            $errors[] = lang('start_date_invalid');
        }

        if (! $this->_is_date($data['end_date'])) {
            $errors[] = lang('end_date_invalid');
        }

        // -------------------------------------
        // Check if times are valid
        // -------------------------------------

        if ($data['all_day'] == 'n') {
            if (! $this->_is_time($data['start_time'])) {
                $errors[] = lang('start_time_invalid');
            }

            if (! $this->_is_time($data['end_time'])) {
                $errors[] = lang('end_time_invalid');
            }
        }

        // -------------------------------------
        // If dates and times are valid,
        // Check if end time is after start time
        // -------------------------------------

        if (! $errors) {
            $start = strtotime($data['start_date'] . ' ' . $data['start_time']);
            $end = strtotime($data['end_date'] . ' ' . $data['end_time']);

            if ($end < $start) {
                $errors[] = lang('event_ends_before_start');
            }
        }

        // -------------------------------------
        // Return error messages or TRUE if none
        // -------------------------------------

        return ($errors) ? implode('<br />', $errors) : true;
    }

    /**
     * Rough validation for date
     */
    private function _is_date($str)
    {
        return preg_match('/^(19|20)\d\d-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])$/', $str);
    }

    /**
     * Rough validation for time
     */
    private function _is_time($str)
    {
        return preg_match('/^(?:0?[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/', $str);
    }

    // --------------------------------------------------------------------

    /**
     * Return prepped field data to save
     *
     * @param   mixed   Posted data
     * @return  string  Data to save
     */
    public function save($data = '')
    {
        $this->_setup();

        // Prep it
        $data = $this->_prep_data($data);

        // Return empty
        if (empty($data)) {
            return $data;
        }

        // Return json coded string
        $data = json_encode($data);

        return $data;
    }

    /**
     * Insert/update row into low_events table
     *
     * @access     public
     * @param      mixed     Posted data
     * @return     void
     */
    public function post_save($data)
    {
        // Return null if null
        if (empty($data)) {
            return;
        }

        $this->_setup();

        $data = $this->_prep_data($data);

        // Add IDs to the data array
        $data['entry_id'] = $this->content_id();
        $data['field_id'] = $this->id();
        $data['site_id'] = $this->site_id;

        // Check if there's an existing entry
        ee()->db->where('field_id', $data['field_id']);
        $event = $this->model->get_one($data['entry_id'], 'entry_id');

        // If so, update
        if ($event) {
            $this->model->update($event['event_id'], $data);
        } else {
            // Or else just insert the new event
            $this->model->insert($data);
        }

        // Get dates from data
        list($start, $end) = low_prep_dates($data);

        // Get default time zone
        $timezone = ee()->config->item('default_site_timezone');

        // Possibly update entry date
        if (@$this->settings['overwrite_entry_date'] == 'y') {
            // Convert to timestamp
            $start = ee()->localize->string_to_timestamp($start, $timezone);

            // $entry = ee('Model')
            //  ->get('ChannelEntry')
            //  ->filter('entry_id', $data['entry_id'])
            //  ->first();

            // $entry->entry_date = $start;
            // $entry->year = date('Y', $start);
            // $entry->month = date('m', $start);
            // $entry->day = date('d', $start);

            // $entry->save();

            // Update entry in DB
            ee()->db->update('channel_titles', array(
                'entry_date' => $start,
                'year' => date('Y', $start),
                'month' => date('m', $start),
                'day' => date('d', $start)
            ), "entry_id = '{$data['entry_id']}'");
        }

        // Possibly update expiration date
        if (@$this->settings['overwrite_expiration_date'] == 'y') {
            // Convert to timestamp
            $end = ee()->localize->string_to_timestamp($end, $timezone);

            // Update entry in DB
            ee()->db->update(
                'channel_titles',
                array('expiration_date' => $end),
                "entry_id = '{$data['entry_id']}'"
            );
        }
    }

    // --------------------------------------------------------------------

    /**
     * Pre-process the given data
     */
    public function pre_process($data)
    {
        $this->_setup();

        return $this->_prep_data($data);
    }

    /**
    * Display tag in template
    *
    * @access      public
    * @param       string    Current value for field
    * @param       array     Tag parameters
    * @param       bool
    * @return      string
    */
    public function replace_tag($data, $params = array(), $tagdata = false)
    {
        return '';
    }

    // --------------------------------------------------------------------

    /**
     * Return a formatted date
     */
    private function _replace_date($date, $params)
    {
        $this->date->init($date);
        if (! isset($params['format'])) {
            $params['format'] = '%Y-%m-%d';
        }

        return $this->date->ee_format($params['format'], @$params['lang']);
    }

    /**
    * Display {var_name:start_date format="foo" lang="dutch"}
    *
    * @param       string    Current value for field
    * @param       array     Tag parameters
    * @return      string
    */
    public function replace_start_date($data, $params)
    {
        $time = ($data['all_day'] == 'y') ? '00:00' : $data['start_time'];

        return $this->_replace_date($data['start_date'] . ' ' . $time, $params);
    }

    /**
    * Display {var_name:end_date format="foo"}
    *
    * @param       string    Current value for field
    * @param       array     Tag parameters
    * @return      string
    */
    public function replace_end_date($data, $params)
    {
        $time = ($data['all_day'] == 'y') ? '23:59' : $data['end_time'];

        return $this->_replace_date($data['end_date'] . ' ' . $time, $params);
    }

    // --------------------------------------------------------------------

    /**
     * Return a time format based on given data
     */
    private function _replace_time($data, $which, $format = false)
    {
        $this->date->init($data["{$which}_date"], $data["{$which}_time"]);
        if (! $format) {
            $format = '%H:%i';
        }

        return $this->date->ee_format($format, @$params['lang']);
    }

    /**
     * start time
     */
    public function replace_start_time($data, $params)
    {
        return $data['start_time']
             ? $this->_replace_time($data, 'start', @$params['format'])
             : '';
    }

    /**
     * end time
     */
    public function replace_end_time($data, $params)
    {
        return $data['end_time']
             ? $this->_replace_time($data, 'end', @$params['format'])
             : '';
    }

    /**
     * start timestamp
     */
    public function replace_start_stamp($data, $params)
    {
        return $this->_get_start_stamp($data);
    }

    /**
     * end timestamp
     */
    public function replace_end_stamp($data, $params)
    {
        return $this->_get_end_stamp($data);
    }

    // --------------------------------------------------------------------

    /**
     * all day
     */
    public function replace_all_day($data, $params)
    {
        return ($data['all_day'] == 'y') ? 'y' : '';
    }

    /**
     * one day
     */
    public function replace_one_day($data, $params)
    {
        return ($data['start_date'] == $data['end_date']) ? 'y' : '';
    }

    /**
     * Duration
     */
    public function replace_duration($data, $params)
    {
        // If event is all day, set the times to span the whole day
        $start = $this->_get_start_stamp($data);
        $end = $this->_get_end_stamp($data);
        $now = $this->date->now();

        switch (@$params['until']) {
            case 'start':
                $from = ($start > $now) ? $now : $start;
                $to = ($start > $now) ? $start : $now;

                break;

            case 'end':
                $from = ($end > $now) ? $now : $end;
                $to = ($end > $now) ? $end : $now;

                break;

            default:
                $from = $start;
                $to = $end;
        }

        // Use EE's native format_timespan function to get the duration string
        return timespan($from, $to);
    }

    // --------------------------------------------------------------------

    /**
     * Relative start date
     */
    public function replace_relative_start_date($data, $params)
    {
        return $this->_relative_date($data, $params, 'start');
    }

    /**
     * Relative end date
     */
    public function replace_relative_end_date($data, $params)
    {
        return $this->_relative_date($data, $params, 'end');
    }

    /**
     * Relative date
     */
    private function _relative_date($data, $params, $which)
    {
        if (version_compare(APP_VER, '2.8.0', '>=')) {
            $method = "_get_{$which}_stamp";

            return ee()->TMPL->process_date($this->$method($data), $params, true, false);
        } else {
            $params['until'] = $which;

            return $this->replace_duration($data, $params);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Is passed?
     */
    public function replace_passed($data, $params)
    {
        return ($this->date->now() > $this->_get_end_stamp($data)) ? 'y' : '';
    }

    /**
     * Is upcoming?
     */
    public function replace_upcoming($data, $params)
    {
        return ($this->date->now() < $this->_get_start_stamp($data)) ? 'y' : '';
    }

    /**
     * Is active?
     */
    public function replace_active($data, $params)
    {
        $start = $this->_get_start_stamp($data);
        $end = $this->_get_end_stamp($data);

        return ($this->date->now() >= $start && $this->date->now() <= $end) ? 'y' : '';
    }

    // --------------------------------------------------------------------

    /**
     * Display 'y' or '' depending if entry is the first in [unit]
     *
     * @access     public
     * @param      array
     * @param      array
     * @return     string
     */
    public function replace_first($data, $params)
    {
        // Remember last time?
        static $last = array();

        // Unit
        $unit = (isset($params['unit']) && in_array($params['unit'], $this->date->units()))
              ? $params['unit']
              : 'month';

        // The field id
        $fid = $this->settings['field_name'];

        // Initiate return value
        $it = '';

        // Init so we can calculate
        $this->date->init($data['start_date']);

        // Get the value for this start date according to unit
        switch ($unit) {
            case 'day':
                $val = $this->date->date();

                break;

            case 'week':
                $val = $this->date->week_url();

                break;

            case 'month':
                $val = $this->date->month_url();

                break;

            case 'year':
                $val = $this->date->year();

                break;
        }

        // If it's a different value than the last one, header is yes!
        if (! isset($last[$fid][$unit]) || $last[$fid][$unit] != $val) {
            $last[$fid][$unit] = $val;
            $it = 'y';
        }

        // Please
        return $it;
    }

    // --------------------------------------------------------------------

    /**
     * Display 'y' or '' depending if date is this date (now)
     *
     * @access     private
     * @param      array
     * @param      array
     * @return     string
     */
    private function _is_this($date, $unit = false)
    {
        // Unit
        if (! in_array($unit, $this->date->units())) {
            $unit = 'month';
        }

        // Init so we can calculate
        $this->date->init($date);

        // Do the same for now
        $now = new Date();
        $now->init($this->today);

        // Get the value for this start date according to unit
        switch ($unit) {
            case 'day':
                $val = $this->date->date();
                $now = $now->date();

                break;

            case 'week':
                $val = $this->date->week_url();
                $now = $now->week_url();

                break;

            case 'month':
                $val = $this->date->month_url();
                $now = $now->month_url();

                break;

            case 'year':
                $val = $this->date->year();
                $now = $now->year();

                break;
        }

        return ($now == $val) ? 'y' : '';
    }

    /**
     * Display 'y' or '' depending if entry is the first in [unit]
     */
    public function replace_starts_this($data, $params)
    {
        return $this->_is_this($data['start_date'], @$params['unit']);
    }

    /**
     * Display 'y' or '' depending if entry is the first in [unit]
    */
    public function replace_ends_this($data, $params)
    {
        return $this->_is_this($data['end_date'], @$params['unit']);
    }

    /**
     * Event starts this year?
     */
    public function replace_starts_this_year($data)
    {
        return $this->_is_this($data['start_date'], 'year');
    }

    /**
     * Event starts this month?
     */
    public function replace_starts_this_month($data)
    {
        return $this->_is_this($data['start_date'], 'month');
    }

    /**
     * Event starts this week?
     */
    public function replace_starts_this_week($data)
    {
        return $this->_is_this($data['start_date'], 'week');
    }

    /**
     * Event starts this day?
     */
    public function replace_starts_this_day($data)
    {
        return $this->_is_this($data['start_date'], 'day');
    }

    /**
     * Event ends this year?
     */
    public function replace_ends_this_year($data)
    {
        return $this->_is_this($data['end_date'], 'year');
    }

    /**
     * Event ends this month?
     */
    public function replace_ends_this_month($data)
    {
        return $this->_is_this($data['end_date'], 'month');
    }

    /**
     * Event ends this week?
     */
    public function replace_ends_this_week($data)
    {
        return $this->_is_this($data['end_date'], 'week');
    }

    /**
     * Event ends this day?
     */
    public function replace_ends_this_day($data)
    {
        return $this->_is_this($data['end_date'], 'day');
    }

    // --------------------------------------------------------------------

    /**
     * Get timestamp for start date
     */
    private function _get_start_stamp($data)
    {
        if ($data['all_day'] == 'y') {
            $data['start_time'] = '00:00';
        }

        $start = new Date($data['start_date'], $data['start_time']);

        return $start->stamp();
    }

    /**
     * Get timestamp for end date
     */
    private function _get_end_stamp($data)
    {
        $mod = 0;

        if ($data['all_day'] == 'y') {
            $data['end_time'] = '23:59';
            $mod = 60;
        }

        $end = new Date($data['end_date'], $data['end_time']);

        return $end->stamp() + $mod;
    }

    /**
     * Convert 24h time to 12h time
     */
    private function _time_to_12($time, $date = '2000-01-01')
    {
        return date('g:ia', strtotime("{$date} {$time}:00"));
    }

    /**
     * Convert 12h time to 24h time
     */
    private function _time_to_24($time, $date = '2000-01-01')
    {
        return date('H:i', strtotime("{$date} {$time}"));
    }

    /**
     * JSON Decode string, make sure an array is returned
     *
     * @access     private
     * @param      string
     * @return     array
     */
    private function _json_decode($str)
    {
        return is_string($str)
            ? json_decode($str, true)
            : $str;
    }

    // --------------------------------------------------------------------

    /**
     * Load assets: extra JS and CSS
     *
     * @access     private
     * @return     void
     */
    private function _load_assets()
    {
        // Load jQuery UI Datepicker
        ee()->cp->add_js_script('ui', 'datepicker');

        // Cachebuster
        $v = '&amp;v=' . (static::DEBUG ? time() : $this->info['version']);

        // Package Assets
        $assets = array(
            'css' => array($this->package),
            'js' => array(
                'jquery.timepicker',
                'jquery.datepair',
                'datepair',
                $this->package
            )
        );

        // Loop through package assets
        foreach ($assets as $type => $files) {
            // Method to call
            $method = 'load_package_' . $type;

            // Loop through files
            foreach ($files as $file) {
                ee()->cp->$method($file);
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Get user's time format (12|24)
     */
    private function _get_time_format()
    {
        $fmt = ee()->config->item('time_format');

        if ($fmt == 'eu') {
            $fmt = '24';
        }
        if ($fmt == 'us') {
            $fmt = '12';
        }

        return $fmt;
    }

    // --------------------------------------------------------------------

    /**
     * Get a setting with fallback
     */
    private function setting($key, $fallback = null)
    {
        // Merge with the defaults first
        $settings = array_merge($this->default_settings, $this->settings);

        // Then check for the key
        return array_key_exists($key, $settings)
            ? $settings[$key]
            : $fallback;
    }

    // --------------------------------------------------------------------

    /**
     * Simple Zenbu Support for Low Events
     */
    public function zenbu_display($entry_id, $channel_id, $data)
    {
        $data = $this->_prep_data($data);

        if ($data['all_day'] == 'y') {
            $start = $data['start_date'];
            $end = $data['end_date'];
        } else {
            $start = $data['start_date'] . '&nbsp;' . $data['start_time'];
            $end = $data['end_date'] . '&nbsp;' . $data['end_time'];
        }

        return sprintf('%s &ndash; %s', $start, $end);
    }

    // --------------------------------------------------------------------

    /**
     * @see https://support.ellislab.com/bugs/detail/21524
     */
    public function update($version = '')
    {
        return true;
    }
}
// END Low_events_ft class
