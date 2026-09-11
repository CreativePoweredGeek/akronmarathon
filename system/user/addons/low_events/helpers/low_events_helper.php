<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Low Search helper functions
 *
 * @package        low_events
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-events
 * @copyright      Copyright (c) 2012-2017, Low
 */

// --------------------------------------------------------------

/**
 * Takes event row, returns array with start/end date/time strings
 *
 * @param       string
 * @return      array
 */
if (! function_exists('low_prep_dates')) {
    function low_prep_dates($row)
    {
        // Start date and start time
        $sd = $row['start_date'];
        $st = ($row['all_day'] == 'y') ? '00:00:00' : $row['start_time'];

        // End date and end time
        $ed = $row['end_date'];
        $et = ($row['all_day'] == 'y') ? '23:59:59' : $row['end_time'];

        return array("{$sd} {$st}", "{$ed} {$et}");
    }
}

/**
 * Order a result set by end date/time
 *
 * @param       array
 * @param       array
 * @return      int
 */
if (! function_exists('low_sort_by_end')) {
    function low_sort_by_end($a, $b)
    {
        // Start date and start time
        list($a_start, $a_end) = low_prep_dates($a);
        list($b_start, $b_end) = low_prep_dates($b);

        return strcmp($a_end, $b_end);
    }
}

// --------------------------------------------------------------

/**
 * Converts EE parameter to workable php vars
 *
 * @access     public
 * @param      string    String like 'not 1|2|3' or '40|15|34|234'
 * @return     array     [0] = array of ids, [1] = boolean whether to include or exclude: TRUE means include, FALSE means exclude
 */
if (! function_exists('low_explode_param')) {
    function low_explode_param($str)
    {
        // --------------------------------------
        // Initiate $in var to TRUE
        // --------------------------------------

        $in = true;

        // --------------------------------------
        // Check if parameter is "not bla|bla"
        // --------------------------------------

        if (strtolower(substr($str, 0, 4)) == 'not ') {
            // Change $in var accordingly
            $in = false;

            // Strip 'not ' from string
            $str = substr($str, 4);
        }

        // --------------------------------------
        // Return two values in an array
        // --------------------------------------

        return array(preg_split('/[&\|]/', $str), $in);
    }
}

// --------------------------------------------------------------------

/**
 * Flatten results
 *
 * Given a DB result set, this will return an (associative) array
 * based on the keys given
 *
 * @param      array
 * @param      string    key of array to use as value
 * @param      string    key of array to use as key (optional)
 * @return     array
 */
if (! function_exists('low_flatten_results')) {
    function low_flatten_results($resultset, $val, $key = false)
    {
        $array = array();

        foreach ($resultset as $row) {
            if ($key !== false) {
                $array[$row[$key]] = $row[$val];
            } else {
                $array[] = $row[$val];
            }
        }

        return $array;
    }
}

// --------------------------------------------------------------------

/**
 * Associate results
 *
 * Given a DB result set, this will return an (associative) array
 * based on the keys given
 *
 * @param      array
 * @param      string    key of array to use as key
 * @param      bool      sort by key or not
 * @return     array
 */
if (! function_exists('low_associate_results')) {
    function low_associate_results($resultset, $key, $sort = false)
    {
        $array = array();

        foreach ($resultset as $row) {
            if (array_key_exists($key, $row) && ! array_key_exists($row[$key], $array)) {
                $array[$row[$key]] = $row;
            }
        }

        if ($sort === true) {
            ksort($array);
        }

        return $array;
    }
}

// --------------------------------------------------------------

/**
 * Get cache value, either using the cache method (EE2.2+) or directly from cache array
 *
 * @param       string
 * @param       string
 * @return      mixed
 */
if (! function_exists('low_get_cache')) {
    function low_get_cache($a, $b)
    {
        if (method_exists(ee()->session, 'cache')) {
            return ee()->session->cache($a, $b);
        } else {
            return (isset(ee()->session->cache[$a][$b]) ? ee()->session->cache[$a][$b] : false);
        }
    }
}

// --------------------------------------------------------------

/**
 * Set cache value, either using the set_cache method (EE2.2+) or directly to cache array
 *
 * @param       string
 * @param       string
 * @param       mixed
 * @return      void
 */
if (! function_exists('low_set_cache')) {
    function low_set_cache($a, $b, $c)
    {
        if (method_exists(ee()->session, 'set_cache')) {
            ee()->session->set_cache($a, $b, $c);
        } else {
            ee()->session->cache[$a][$b] = $c;
        }
    }
}

// --------------------------------------------------------------

/**
 * Debug
 *
 * @param       mixed
 * @param       bool
 * @return      void
 */
if (! function_exists('low_dump')) {
    function low_dump($var, $exit = true)
    {
        echo '<pre>' . print_r($var, true) . '</pre>';
        if ($exit) {
            exit;
        }
    }
}

// --------------------------------------------------------------

/* End of file low_events_helper.php */
