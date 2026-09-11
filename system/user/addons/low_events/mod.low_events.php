<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use Low\Events\Library\Date;
use Low\Events\Library\Field;
use Low\Events\Library\Sieve;

/**
 * Low Events Module class
 *
 * @package        low_events
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-events
 * @copyright      Copyright (c) 2012-2017, Low
 */
include_once "addon.setup.php";
use Low\Events\FluxCapacitor\Base\Mod;

class Low_events extends Mod
{
    public $return_data;
    // --------------------------------------------------------------------
    // PROPERTIES
    // --------------------------------------------------------------------

    /**
     * Package name
     *
     * @access      public
     * @var         string
     */
    private $package;

    /**
     * Custom channel:entries params
     *
     * @access     private
     * @var        array
     */
    private $params = array(
        'date',
        'date_from',
        'date_to',
        'unit',
        'show_active',
        'show_passed',
        'show_upcoming'
    );

    /**
     * Date formats for variables
     *
     * @access     private
     * @var        array
     */
    private $formats = array(
        '%F' => 'month',
        '%m' => 'month_num',
        '%M' => 'month_short',
        '%n' => 'month_num_short',
        '%Y' => 'year',
        '%y' => 'year_short'
    );

    /**
     * Weekdays and their ISO numeric representation
     *
     * @access     private
     * @var        array
     */
    private $weekdays = array(
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
        7 => 'sunday'
    );

    /**
     * Shortcut to Low_date lib
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
     * channel fields shortcut/cache
     *
     * @access     private
     * @var        array
     */
    private $fields;

    /**
     * Shortcut to today's date
     *
     * @access     private
     * @var        string
     */
    private $today;

    /**
     * For custom units, date from
     *
     * @access     private
     * @var        string
     */
    private $date_from;

    /**
     * For custom units, date to
     *
     * @access     private
     * @var        string
     */
    private $date_to;

    // --------------------------------------------------------------------
    // PUBLIC METHODS
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

        ee()->load->helper($this->package);
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
     * Show events
     *
     * @access      public
     * @param       bool
     * @return      string
     */
    public function entries($ids_only = false)
    {
        // --------------------------------------
        // Initiate the date to work with
        // --------------------------------------

        $this->_init_date();

        // --------------------------------------
        // Prep no_results to avoid conflicts
        // --------------------------------------

        $this->_prep_no_results();

        // --------------------------------------
        // Determine unit to display
        // --------------------------------------

        // Unit parameter defaults to what was given in date param
        $unit = $this->_get_param('unit', $this->date->given());

        // Log it for debugging
        $this->_log("Unit: {$unit}");

        // --------------------------------------
        // Initiate rows
        // --------------------------------------

        $rows = array();

        // --------------------------------------
        // Initiate range array (faux object)
        // --------------------------------------

        $range = $this->_get_range();

        // --------------------------------------
        // Get the rows depending on unit
        // --------------------------------------

        switch ($unit) {
            case 'year':
                list($range['start_date'], $range['end_date']) = $this->date->get_year_range();

                break;

            case 'month':
                list($range['start_date'], $range['end_date']) = $this->date->get_month_range();

                break;

            case 'week':
                list($range['start_date'], $range['end_date']) = $this->date->get_week_range();

                break;

            case 'day':
                $range['start_date'] = $range['end_date'] = $this->date->date();

                break;

            case 'custom':
                $range['start_date'] = $this->date->from();
                $range['end_date'] = $this->date->to();

                break;

            case 'passed':
                $range['end_date'] = $this->date->date();
                $range['active'] = $range['upcoming'] = false;

                break;

            case 'active':
                $range['start_date'] = $range['end_date'] = $this->date->date();
                $range['passed'] = $range['upcoming'] = false;

                break;

            case 'all':
                $range['start_date'] = $range['end_date'] = null;

                break;

            default:
                $unit = 'upcoming';
                $range['start_date'] = $this->date->date();

                break;
        }

        // --------------------------------------
        // Get the rows depending on unit
        // --------------------------------------

        $rows = $this->model->get_range($range);

        // --------------------------------------
        // Optionally sort by end date instead of start date
        // Do it here so caching of rows can stay intact
        // --------------------------------------

        if ($orderby = $this->_get_param('orderby')) {
            if (preg_match('/^low_events:(start|end)$/', $orderby, $match)) {
                // Sort by end with php
                if ($match[1] == 'end') {
                    usort($rows, 'low_sort_by_end');
                }

                // Get rid of orderby param
                unset(ee()->TMPL->tagparams['orderby']);
            }
        }

        // Get ids only
        $entry_ids = $rows ? low_flatten_results($rows, 'entry_id') : array();

        // Clean up
        unset($rows);

        // --------------------------------------
        // Check for show_pages parameter
        // --------------------------------------

        if ($show_pages = $this->_get_param('show_pages')) {
            // Get all page IDs
            $page_ids = $this->_get_page_ids();

            switch ($show_pages) {
                case 'no':
                    // Filter out page ids
                    $entry_ids = array_diff($entry_ids, $page_ids);

                    break;

                case 'only':
                    // Only page ids
                    $entry_ids = array_intersect($entry_ids, $page_ids);

                    break;
            }
        }

        // --------------------------------------
        // Check for existing entry_id parameter
        // --------------------------------------

        if ($entry_id_param = $this->_get_param('entry_id')) {
            $this->_log('entry_id parameter found, filtering event ids accordingly');

            // Get the parameter value
            list($ids, $in) = low_explode_param($entry_id_param);

            // Either remove $ids from $entry_ids OR limit $entry_ids to $ids
            $method = $in ? 'array_intersect' : 'array_diff';

            // Get list of entry ids that should be listed
            $entry_ids = $method($entry_ids, $ids);
        }

        // --------------------------------------
        // If IDs only, return those
        // --------------------------------------

        if ($ids_only) {
            return $entry_ids;
        }

        // --------------------------------------
        // If there are no entry_ids, return nothin
        // --------------------------------------

        if (empty($entry_ids)) {
            $this->_log('No event ids found, returning no results');

            return ee()->TMPL->no_results();
        }

        // --------------------------------------
        // set fixed_order / entry_id according to presence of orderby param
        // --------------------------------------

        $param = ($this->_get_param('orderby')) ? 'entry_id' : 'fixed_order';
        $param_val = implode('|', $entry_ids);

        $this->_log(sprintf('Setting %s="%s"', $param, $param_val));
        ee()->TMPL->tagparams[$param] = $param_val;

        // --------------------------------------
        // Make sure the following params are set
        // --------------------------------------

        $set_params = array(
            'dynamic' => 'no',
            'paginate' => 'bottom'
        );

        foreach ($set_params as $key => $val) {
            if (! ee()->TMPL->fetch_param($key)) {
                ee()->TMPL->tagparams[$key] = $val;
            }
        }

        // --------------------------------------
        // Let the Channel module do all the heavy lifting
        // --------------------------------------

        return $this->_channel_entries();
    }

    /**
     * Show event IDs
     *
     * @access      public
     * @return      string
     */
    public function entry_ids()
    {
        // --------------------------------------
        // Get some parameters and check pair tag
        // --------------------------------------

        $pair = ($tagdata = ee()->TMPL->tagdata) ? true : false;
        $no_results = ee()->TMPL->fetch_param('no_results');
        $separator = ee()->TMPL->fetch_param('separator', '|');

        // --------------------------------------
        // Get ids and create single string from entry ids
        // --------------------------------------

        $entry_ids = $this->entries(true);
        $entry_ids = empty($entry_ids) ? $no_results : implode($separator, $entry_ids);

        // --------------------------------------
        // Parse+return or just return, depending on tag pair or not
        // --------------------------------------

        if ($pair) {
            return ee()->TMPL->parse_variables_row($tagdata, array(
                'low_events:entry_ids' => $entry_ids
            ));
        } else {
            return $entry_ids;
        }
    }

    // --------------------------------------------------------------------

    /**
     * Get current/given date
     *
     * @access     public
     * @return     string
     */
    public function this_date()
    {
        return $this->_format();
    }

    /**
     * Get next date
     */
    public function next_date()
    {
        return $this->_format('add');
    }

    /**
     * Get previous date
     */
    public function prev_date()
    {
        return $this->_format('sub');
    }

    /**
     * Return date in format
     */
    private function _format($mod = false)
    {
        // --------------------------------------
        // Initiate the date to work with
        // --------------------------------------

        $this->_init_date();

        // --------------------------------------
        // What are we going to display?
        // --------------------------------------

        $unit = ee()->TMPL->fetch_param('unit', $this->date->given());

        // --------------------------------------
        // Do we need to modify the date given
        // --------------------------------------

        if ($mod && $unit) {
            // Change to 1st of the month if unit is month,
            // to prevent mismatch by the end of the month
            if ($unit == 'month') {
                $this->date->first_of_month();
            }

            $this->date->$mod($unit);

            // Log it
            $this->_log("Modify date: {$mod} {$unit} to " . $this->date->date());
        }

        // --------------------------------------
        // Check in what format
        // --------------------------------------

        // Get format="" param
        $format = ee()->TMPL->fetch_param('format');

        // Check for year_format="", month_format="" or day_format=""
        $format = ee()->TMPL->fetch_param($unit . '_format', $format);

        // Get lang="" param
        $lang = ee()->TMPL->fetch_param('lang');

        if (! $format) {
            switch ($unit) {
                case 'week':
                    $this->return_data = $this->date->week_url();

                    break;

                case 'month':
                    $this->return_data = $this->date->month_url();

                    break;

                case 'year':
                    $this->return_data = $this->date->year();

                    break;

                default:
                    $this->return_data = $this->date->date();
            }
        } else {
            $this->return_data = $this->date->ee_format($format, $lang);
        }

        return $this->return_data;
    }

    // --------------------------------------------------------------------

    /**
     * Generate Calendar based on events
     *
     * @access      public
     * @return      string
     */
    public function calendar()
    {
        // --------------------------------------
        // Initiate the date to work with
        // --------------------------------------

        $this->_init_date();

        // Keep track of given date
        $given_date = $this->date->date();
        $given_year = $this->date->year();
        $given_month = $this->date->month_url();
        $given_week = ($this->date->given() == 'week') ? $this->date->week_url() : false;

        // --------------------------------------
        // If a week is given, make sure the thursday
        // is in the same month, or else advance one month
        // --------------------------------------

        if ($given_week) {
            // Get the thursday
            $this->date->modify('+ 3 days');

            if ($given_month == $this->date->month_url()) {
                // No probs, same month given, reset back to original given date
                $this->date->reset();
            } else {
                // Thursday is in next month, so set the date to that
                $given_date = $this->date->date();
                $given_year = $this->date->year();
                $given_month = $this->date->month_url();
            }
        }

        // Set date to first of the month
        $this->date->first_of_month();

        // Get next and previous month
        $next = new Date($this->date->date());
        $prev = new Date($this->date->date());
        $next->add('month');
        $prev->sub('month');

        // Days in month
        $dim = $this->date->days_in_month();

        // Day of the Week
        $dotw = $this->date->first_day_of_month();

        // Week of the month
        $wotm = 0;

        // --------------------------------------
        // Day to start the week
        // --------------------------------------

        $start_day = strtolower(ee()->TMPL->fetch_param('start_day'));

        // Force monday on non-existent weekday or if a week is given
        if (! in_array($start_day, $this->weekdays) || $given_week !== false) {
            $start_day = 'monday';
        }

        $start_day = array_search($start_day, $this->weekdays);

        // --------------------------------------
        // Calculate number of leading days (prev month)
        // --------------------------------------

        if ($leading_days = (($dotw - $start_day) + 7) % 7) {
            $this->date->modify("- {$leading_days} days");
        }

        // Keep track of start date
        $start_date = $this->date->date();

        // Initiate weeks and weekdays arrays
        $weeks = $weekdays = $days = array();

        // Initiate day count
        $day_count = 0;

        // Add leading 0s to day number?
        $leading = (ee()->TMPL->fetch_param('leading_zeroes', 'no') == 'yes');

        // --------------------------------------
        // Language parameter
        // --------------------------------------

        $lang = ee()->TMPL->fetch_param('lang');

        // --------------------------------------
        // Populate weeks array
        // --------------------------------------

        while (true) {
            // Initiate week
            if (! isset($weeks[$wotm])) {
                $weeks[$wotm] = array(
                    'days' => array(),
                    'week_url' => $this->date->week_url(),
                    'is_given_week' => ($given_week == $this->date->week_url()) ? 'y' : ''
                );
            }

            // Add the day row to the week
            $weeks[$wotm]['days'][] = array(
                'day_number' => $leading ? $this->date->day() : intval($this->date->day()),
                'day_url' => $this->date->day_url(),
                'day' => $this->date->day_url(),
                'is_prev' => ($this->date->month_url() == $prev->month_url()) ? 'y' : '',
                'is_next' => ($this->date->month_url() == $next->month_url()) ? 'y' : '',
                'is_current' => ($this->date->month_url() == $given_month) ? 'y' : '',
                'is_given' => ($this->date->given() == 'day' && $this->date->date() == $given_date) ? 'y' : '',
                'is_today' => ($this->date->date() == $this->today) ? 'y' : '',
                'is_weekend' => ($this->date->day_of_the_week() > 5) ? 'y' : '',
                'events_on_day' => 0
            );

            // Populate weekdays
            if (! $wotm) {
                $weekdays[] = array(
                    'weekday' => $this->date->ee_format('%l', $lang),
                    'weekday_short' => $this->date->ee_format('%D', $lang),
                    'weekday_1' => substr($this->date->ee_format('%D', $lang), 0, 1)
                );
            }

            // Advance by one day
            $this->date->add('day');

            // if days is divisible by 7, a week is done
            if ($done = ! (++$day_count % 7)) {
                // If we're caught up with the next month too, exit the loop
                if ($this->date->month_url() == $next->month_url()) {
                    break;
                }

                // Or else just increase the week of the month
                $wotm++;
            }
        }

        // End date
        $end_date = $this->date->date();
        $this->date->reset();

        $this->_log("Initiated calendar from {$start_date} to {$end_date}");

        // --------------------------------------
        // Get events for this calendar range
        // --------------------------------------

        // Initiate events
        $events = $entries = array();

        $range = $this->_get_range();
        $range['start_date'] = $start_date;
        $range['end_date'] = $end_date;

        $rows = $this->model->get_range($range);

        // Query the rest of the entry details if there are events present
        if ($entries = $this->_get_event_entries($rows)) {
            foreach ($entries as $row) {
                // If anniversary, change the years to given year
                if ($range['anniversaries']) {
                    $start_year = (int) substr($row['start_date'], 0, 4);
                    $end_year = (int) substr($row['end_date'], 0, 4);
                    $end_year = $given_year + ($end_year - $start_year);

                    $row['start_date'] = $given_year . substr($row['start_date'], 4);
                    $row['end_date'] = $end_year . substr($row['end_date'], 4);
                }

                // Skip the ones not found in $entries
                if ($row['start_date'] == $row['end_date']) {
                    $events[$row['start_date']][] = $row;
                } else {
                    // Assign each day between start and end to events array
                    $date = new Date($row['start_date']);

                    while (($start = $date->date()) <= $row['end_date']) {
                        $events[$start][] = $row;
                        $date->add('day');
                    }
                }
            }
        } else {
            // No events in this range
        }

        // Keep track of total events found
        $total_entries = count($entries);
        $total_days = count($events);

        $this->_log("In this range: {$total_entries} entries, spanning {$total_days} days");

        // --------------------------------------
        // Assign entry count to days
        // --------------------------------------

        if ($events) {
            foreach ($weeks as &$week) {
                foreach ($week['days'] as &$day) {
                    if (array_key_exists($day['day'], $events)) {
                        $day['events_on_day'] = count($events[$day['day']]);
                    }
                }
            }
        }

        // --------------------------------------
        // Parse prev/this/next month links ourselves
        // --------------------------------------

        $this->return_data = ee()->TMPL->tagdata;

        foreach (ee()->TMPL->var_single as $key => $format) {
            if (preg_match('/^(prev|this|next)_month(\s|$)/', $key, $match)) {
                $format = (strpos($format, '%') !== false) ? $format : '%Y-%m';

                if (($match[1]) == 'this') {
                    $month = $this->date->ee_format($format, $lang);
                } else {
                    $month = ${$match[1]}->ee_format($format, $lang);
                }

                $this->return_data = str_replace(LD . $key . RD, $month, $this->return_data);
            }
        }

        // --------------------------------------
        // Create data array for parsing vars
        // --------------------------------------

        $data = array(
            'next_month_url' => $next->month_url(),
            'prev_month_url' => $prev->month_url(),
            'this_month_url' => $this->date->month_url(),
            'weekdays' => $weekdays,
            'weeks' => $weeks
        );

        $this->_log('Parsing calendar tagdata');

        return ee()->TMPL->parse_variables_row($this->return_data, $data);
    }

    // --------------------------------------------------------------------

    /**
     * Generate month list based on events
     *
     * @access      public
     * @return      string
     */
    public function archive()
    {
        // --------------------------------------
        // Prep no_results to avoid conflicts
        // --------------------------------------

        $this->_prep_no_results();

        // --------------------------------------
        // Get the events
        // --------------------------------------

        $events = $this->model->get_range($this->_get_range());

        if (! ($events = $this->_get_event_entries($events))) {
            $this->_log('No events found, returning no results');

            return ee()->TMPL->no_results();
        }

        // --------------------------------------
        // Check unit parameter (month is default)
        // --------------------------------------

        $unit = ee()->TMPL->fetch_param('unit', 'month');

        if (! in_array($unit, $this->date->units())) {
            $unit = 'month';
        }

        // --------------------------------------
        // Loop through events and add them to the rows array
        // --------------------------------------

        $rows = array();

        foreach ($events as $event) {
            // Create Low Date objects from each date
            $start = new Date($event['start_date']);
            $end = new Date($event['end_date']);

            // Set both dates to the first of the month
            // and return the month url: YYYY-MM
            $start_date = ($unit == 'year') ? $start->year() : $start->first_of_month()->month_url();
            $end_date = ($unit == 'year') ? $end->year() : $end->first_of_month()->month_url();

            // If event starts and ends in the same date,
            // simply add it to the dates array
            if ($start_date == $end_date) {
                $rows[$start_date][] = $event['entry_id'];
            } else {
                // Or else add each spanning date to the rows array

                // To do this, increase the start date by a month
                // until it exceeds the end month
                $method = ($unit == 'year') ? 'year' : 'month_url';
                while ($start->$method() <= $end_date) {
                    $rows[$start->$method()][] = $event['entry_id'];
                    $start->add($unit);
                }
            }
        }

        // Sort ascending for now
        ksort($rows);

        // --------------------------------------
        // Fill'er up?
        // --------------------------------------

        if (ee()->TMPL->fetch_param('show_empty') == 'yes') {
            // Get all dates
            $keys = array_keys($rows);
            $suffix = ($unit == 'year') ? '-01-01' : '-01';
            $method = ($unit == 'year') ? 'year' : 'month_url';

            // Get start and end dates
            $start = new Date($keys[0] . $suffix);
            $end = $keys[count($keys) - 1] . $suffix;

            while ($start->date() < $end) {
                $start->add($unit);

                $key = $start->$method();

                if (! array_key_exists($key, $rows)) {
                    $rows[$key] = array();
                }
            }

            // and sort again
            ksort($rows);
        }

        // --------------------------------------
        // Reverse sort, if necessary
        // --------------------------------------

        if (ee()->TMPL->fetch_param('sort', 'asc') == 'desc') {
            krsort($rows);
        }

        // --------------------------------------
        // Limit/offset the output array by slicing
        // --------------------------------------

        $offset = (int) ee()->TMPL->fetch_param('offset', 0);
        $limit = (int) ee()->TMPL->fetch_param('limit');

        // Force NULL to limit
        if (! $limit) {
            $limit = null;
        }

        // Slice it
        if ($offset || $limit) {
            $rows = array_slice($rows, $offset, $limit, true);
        }

        // --------------------------------------
        // Language parameter
        // --------------------------------------

        $lang = ee()->TMPL->fetch_param('lang');

        // --------------------------------------
        // Create data array based on unit given
        // --------------------------------------

        $data = array();

        foreach ($rows as $key => $events) {
            // Create new date for this key
            $date = new Date($key);

            // Initiate new row for data
            $row = array(
                'unit' => $unit,
                'date_url' => $key,
                'num_events' => count($events)
            );

            // Depricated: use generic vars above instead
            if ($unit == 'month') {
                $row['month_url'] = $row['date_url'];
                $row['events_in_month'] = $row['num_events'];
            }

            // Add each possible format to this row
            foreach ($this->formats as $fmt => $k) {
                $row[$k] = $date->ee_format($fmt, $lang);
            }

            // Then add the row to the data array
            $data[] = $row;
        }

        // --------------------------------------
        // Sweet magic
        // --------------------------------------

        return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $data);
    }

    // --------------------------------------------------------------------
    // PRIVATE METHODS
    // --------------------------------------------------------------------

    /**
     * Check for {if low_events_no_results}
     *
     * @access      private
     * @return      void
     */
    private function _prep_no_results()
    {
        // Shortcut to tagdata
        $td = & ee()->TMPL->tagdata;
        $open = 'if ' . $this->package . '_no_results';
        $close = '/if';

        // Check if there is a custom no_results conditional
        if (strpos($td, $open) !== false && preg_match('#' . LD . $open . RD . '(.*?)' . LD . $close . RD . '#s', $td, $match)) {
            $this->_log("Prepping {$open} conditional");

            // Check if there are conditionals inside of that
            if (stristr($match[1], LD . 'if')) {
                $match[0] = ee()->functions->full_tag($match[0], $td, LD . 'if', LD . '\/if' . RD);
            }

            // Set template's no_results data to found chunk
            ee()->TMPL->no_results = substr($match[0], strlen(LD . $open . RD), -strlen(LD . $close . RD));

            // Remove no_results conditional from tagdata
            $td = str_replace($match[0], '', $td);
        }
    }

    /**
     * Call the native channel:entries method
     *
     * @access     private
     * @return     string
     */
    private function _channel_entries()
    {
        // --------------------------------------
        // Unset custom parameters
        // --------------------------------------

        foreach ($this->params as $param) {
            unset(ee()->TMPL->tagparams[$param]);
        }

        $this->_log('Calling the channel module');

        // --------------------------------------
        // Include channel module
        // --------------------------------------

        if (! class_exists('channel')) {
            require_once PATH_MOD . 'channel/mod.channel.php';
        }

        // --------------------------------------
        // Create new Channel instance
        // --------------------------------------

        $channel = new Channel();

        // --------------------------------------
        // Let the Channel module do all the heavy lifting
        // --------------------------------------

        return $channel->entries();
    }

    /**
     * Events based on parameters present
     *
     * @access     private
     * @return     array
     */
    private function _get_event_entries($rows = array())
    {
        // --------------------------------------
        // No rows? No entries
        // --------------------------------------

        if (empty($rows)) {
            return $rows;
        }

        // --------------------------------------
        // Get entry ids
        // --------------------------------------

        $rows = low_associate_results($rows, 'entry_id');

        // --------------------------------------
        // Start a Sieve
        // --------------------------------------

        // parameters
        $params = ee()->TMPL->tagparams;

        // Check dynamic parameters
        if ($dynamic = ee()->TMPL->fetch_param('dynamic_parameters')) {
            foreach (explode('|', $dynamic) as $key) {
                if ($val = ee('Request')->post($key)) {
                    $params[$key] = $val;
                }
            }
        }

        // Add site ID to parameters
        $params['site_id'] = implode('|', ee()->TMPL->site_ids);

        $sieve = new Sieve();
        $sieve->select('entry_id');
        $sieve->params($params);
        $sieve->filter('entry_id', 'IN', array_keys($rows));

        $entries = $sieve->get();

        // --------------------------------------
        // Return the results
        // --------------------------------------

        if ($entries->count()) {
            $ids = $entries->getDictionary('entry_id', 'entry_id');
            $entries = array_intersect_key($rows, $ids);
        } else {
            $entries = array();
        }

        return $entries;
    }

    /**
     * Get field ids for event fields from param
     *
     * @access     private
     * @return     array
     */
    private function _get_event_field_ids()
    {
        // Check parameter
        if ($events_field = $this->_get_param('events_field')) {
            // Init array
            $ids = array();

            // Get fields from parameter
            list($fields, $in) = low_explode_param($events_field);

            // Get id for each field
            foreach ($fields as $field) {
                $ids[] = Field::id($field);
            }

            // Back to a param
            $events_field = implode('|', $ids);
        }

        return $events_field;
    }

    /**
     * Get all page IDs
     *
     * @access     private
     * @return     array
     */
    private function _get_page_ids()
    {
        // Init at 0 to force no results
        $page_ids = array();

        // Loop through all site pages rows, get entry ids from uris key
        if ($pages = ee()->config->item('site_pages')) {
            foreach ($pages as $site_id => $row) {
                $page_ids = array_merge($page_ids, array_keys($row['uris']));
            }
        }

        return $page_ids;
    }

    // --------------------------------------------------------------------

    /**
     * Initiate date by param or given fallback
     *
     * @access     private
     * @param      string
     * @return     void
     */
    private function _init_date()
    {
        // Get date params
        $date = $this->_get_param('date');
        $date_from = $this->_get_param('date_from', '');
        $date_to = $this->_get_param('date_to', '');

        // If from / to are set, override date param
        if ($date_from || $date_to) {
            $date = $date_from . ';' . $date_to;
        }

        // Initiate the date
        $this->date->init($date);

        // Log the custom date range if given
        $msg = ($this->date->given() == 'custom')
             ? sprintf('Custom date range given from %s to %s', $this->date->from(), $this->date->to())
             : sprintf('Working date set to %s %s', $this->date->date(), $this->date->time());

        $this->_log($msg);
    }

    /**
     * Get parameter from TMPL or post
     */
    private function _get_param($str, $fallback = false)
    {
        // Get date from tag parameter
        $val = ee()->TMPL->fetch_param($str, $fallback);

        // Check if date is dynamic
        if ($dynamic = ee()->TMPL->fetch_param('dynamic_parameters')) {
            // If date is in the dynamic_parameters param, check POST data
            list($dynamic, $in) = low_explode_param($dynamic);

            if (in_array($str, $dynamic) && ($posted_val = ee()->input->post($str))) {
                // Param was posted, use that instead
                $this->_log('Using posted dynamic ' . $str . ': ' . $posted_val);
                $val = $posted_val;
            }
        }

        return $val;
    }

    /**
     * Get range array based on params
     */
    private function _get_range()
    {
        // --------------------------------------
        // Initiate range array (faux object)
        // --------------------------------------

        $range = array(
            'start_date' => null,
            'start_time' => null,
            'end_date' => null,
            'end_time' => null
        );

        // --------------------------------------
        // Passed, Active and Upcoming
        // --------------------------------------

        foreach (array('passed', 'active', 'upcoming') as $key) {
            $range[$key] = ! ($this->_get_param('show_' . $key) == 'no');
        }

        // --------------------------------------
        // Anniversaries?
        // --------------------------------------

        $range['anniversaries'] = ($this->_get_param('anniversaries', 'no') == 'yes');

        // --------------------------------------
        // Get field IDs from param
        // --------------------------------------

        $range['fields'] = $this->_get_event_field_ids();

        // --------------------------------------
        // Site IDs
        // --------------------------------------

        $range['site_id'] = implode('|', ee()->TMPL->site_ids);

        // Return it
        return $range;
    }

    /**
     * Log message to Template Logger
     *
     * @access     private
     * @param      string
     * @return     void
     */
    private function _log($msg)
    {
        ee()->TMPL->log_item("Low Events: {$msg}");
    }
}
// End Class

/* End of file mod.low_events.php */
