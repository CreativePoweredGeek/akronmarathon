<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use Low\Events\Library\Date;

// include super model
if (! class_exists('Low_events_model')) {
    require_once(PATH_THIRD . 'low_events/model.low_events.php');
}

/**
 * Low Events Event Model class
 *
 * @package        low_events
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-events
 * @copyright      Copyright (c) 2012-2017, Low
 */
class Low_events_event_model extends Low_events_model
{
    // --------------------------------------------------------------------
    // METHODS
    // --------------------------------------------------------------------

    /**
     * Constructor
     *
     * @access      public
     * @return      void
     */
    public function __construct()
    {
        // Call parent constructor
        parent::__construct();

        // Initialize this model
        $this->initialize(
            'low_events',
            'event_id',
            array(
                'site_id' => 'int(4) unsigned NOT NULL DEFAULT 1',
                'entry_id' => 'int(10) unsigned NOT NULL',
                'field_id' => 'int(6) unsigned NOT NULL',
                'start_date' => 'date NOT NULL',
                'start_time' => 'time',
                'end_date' => 'date',
                'end_time' => 'time',
                'all_day' => "ENUM('y','n') NOT NULL DEFAULT 'n'"
            )
        );
    }

    // --------------------------------------------------------------------

    /**
     * Installs given table
     *
     * @access      public
     * @return      void
     */
    public function install()
    {
        // Call parent install
        parent::install();

        // Add indexes to table
        foreach (array('entry_id', 'field_id', 'site_id', 'start_date', 'end_date') as $field) {
            ee()->db->query("ALTER TABLE {$this->table()} ADD INDEX (`{$field}`)");
        }
    }

    // --------------------------------------------------------------

    /**
     * Return attributes for entry returning
     *
     * @access      public
     * @param       bool
     * @return      array
     */
    public function entry_attributes($time = true)
    {
        // Default attributes to fetch
        $attrs = array('entry_id', 'start_date', 'end_date', 'all_day');

        // Add time attributes?
        if ($time) {
            $attrs = array_merge($attrs, array('start_time', 'end_time'));
        }

        // Return the attributes
        return $attrs;
    }

    // --------------------------------------------------------------

    /**
     * Replace into record into DB
     *
     * @access      public
     * @param       array     data to replace
     * @return      int
     */
    public function replace($data = array())
    {
        if (empty($data)) {
            // loop through attributes to get posted data
            foreach ($this->attributes() as $attr) {
                if (($val = ee()->input->post($attr)) !== false) {
                    $data[$attr] = $val;
                }
            }
        }

        // Insert data and return inserted id
        $sql = ee()->db->insert_string($this->table(), $data);
        $sql = str_replace('INSERT', 'REPLACE', $sql);

        return ee()->db->query($sql);
    }

    // --------------------------------------------------------------

    /**
     * Get range of events
     *
     * @access     public
     * @param      array
     * @return     array
     */
    public function get_range($range = array())
    {
        // Set cache key
        $key = json_encode($range);

        // Get cache
        $ranges = (array) low_get_cache(__CLASS__, 'ranges');

        if (! isset($ranges[$key])) {
            // Make DB-safe
            //var_dump($range);

            foreach ($range as $i=>$value) {
                $range[$i] = ($value == NULL) ? '' : $value;
            }

            //var_dump($range);

            $range = ee()->db->escape_str($range);

            // Extract it
            extract($range);

            // Now!
            $now_date = date('Y-m-d');
            $now_time = date('H:i');

            // Initiate select attributes
            $select = $this->entry_attributes();

            // Default orderby clause
            $orderby = '`start_date`, `start_time`, `end_date`, `end_time`';

            // Where clauses
            $where = array();

            if (! empty($anniversaries)) {
                // monthday key
                $md = "DATE_FORMAT(`start_date`, '%m%d')";

                // Add this so we can order by it
                $select[] = $md . ' AS md';

                // Calculate range of monthday values
                $min = new Date($start_date);

                // Set end to + 1 year if not given
                if ($end_date) {
                    $max = new Date($end_date);
                } else {
                    $max = new Date($start_date);
                    $max->add('year');
                }

                $days = array();

                // All days that can have anniversaries
                while ($min->date() <= $max->date()) {
                    $days[] = $min->month() . $min->day();
                    $min->add('day');
                }

                // Cancel out dates
                $start_date = $end_date = null;

                // Comma-separated and quoted list
                $sql_days = "'" . implode("', '", $days) . "'";

                // Limit to all_day and days in range
                $where[] = "`all_day` = 'y'";
                $where[] = "{$md} IN ({$sql_days})";

                // Make sure we can order across years, even
                $orderby = "FIELD(md, {$sql_days})";
            }

            // --------------------------------------
            // Start query
            // --------------------------------------

            ee()->db->select($select)
                ->from($this->table())
                ->order_by($orderby, '', false);

            // --------------------------------------
            // Starting from date/time
            // --------------------------------------

            if ($start_date && empty($end_date)) {
                $where[] = "end_date >= '{$start_date}'";
            }

            // --------------------------------------
            // Up until date/time
            // --------------------------------------

            if (empty($start_date) && $end_date) {
                $where[] = "end_date < '{$end_date}'";
            }

            // --------------------------------------
            // Show custom range
            // --------------------------------------

            if ($start_date && $end_date) {
                // Compose where clause
                $where[] = $this->_or(array(
                    // All events that start in between the range
                    "(start_date BETWEEN '{$start_date}' AND '{$end_date}')",
                    // All events that start before the end of the range, and end after the start of the range
                    "(start_date <= '{$end_date}' AND end_date >= '{$start_date}')"
                ));
            }

            // --------------------------------------
            // Skip passed events: end date should be in the past
            // --------------------------------------

            if (! $passed) {
                $where[] = $this->_or(array(
                    "(end_date > '{$now_date}')",
                    "(end_date = '{$now_date}' AND IFNULL(end_time, '23:59:59') > '{$now_time}')"
                ));
            }

            // --------------------------------------
            // Skip upcoming events: start date should be in the past
            // --------------------------------------

            if (! $upcoming) {
                $where[] = $this->_or(array(
                    "(start_date < '{$now_date}')",
                    "(start_date = '{$now_date}' AND IFNULL(start_time, '00:00:00') < '{$now_time}')"
                ));
            }

            // --------------------------------------
            // Skip active events
            // --------------------------------------

            if (! $active) {
                $sql_start = "UNIX_TIMESTAMP(CONCAT(start_date, ' ', IFNULL(start_time, '00:00:00')))";
                $sql_end = "UNIX_TIMESTAMP(CONCAT(end_date, ' ', IFNULL(end_time, '23:59:59')))";
                $sql_now = "UNIX_TIMESTAMP()";

                $where[] = $this->_or(array(
                    "({$sql_start} > {$sql_now})",
                    "({$sql_end} < {$sql_now})"
                ));
            }

            // --------------------------------------
            // Limit to field IDs
            // --------------------------------------

            if ($fields) {
                list($ids, $in) = low_explode_param($fields);

                ee()->db->{($in ? 'where_in' : 'where_not_in')}('field_id', $ids);
            }

            // --------------------------------------
            // Limit to site ids
            // --------------------------------------

            if ($site_id) {
                list($ids, $in) = low_explode_param($site_id);

                ee()->db->{($in ? 'where_in' : 'where_not_in')}('site_id', $ids);
            }

            // --------------------------------------
            // Add the stuff to where
            // --------------------------------------

            foreach ($where as $sql) {
                ee()->db->where($sql);
            }

            // --------------------------------------
            // Add to cache array + register to cache
            // --------------------------------------

            $ranges[$key] = ee()->db->get()->result_array();

            low_set_cache(__CLASS__, 'ranges', $ranges);
        }

        return $ranges[$key];
    }

    // --------------------------------------------------------------

    /**
     * Combine with OR
     */
    private function _or($array)
    {
        return '(' . implode(' OR ', $array) . ')';
    }

    // --------------------------------------------------------------
} // End class

/* End of file low_events_event_model.php */
