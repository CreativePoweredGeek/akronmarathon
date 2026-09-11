<?php

namespace Low\Events\Library;

/**
 * Low Alphabet Param class, for manipulating parameters
 *
 * @package        low_events
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-events
 * @copyright      Copyright (c) 2012-2017, Low
 */
class Param
{
    /**
     * Explode parameter, previously low_explode_param() helper function
     *
     * @access     public
     * @param      string    String like 'not 1|2|3' or '40|15|34|234'
     * @return     array     [0] = array of ids, [1] = boolean whether to include or exclude
     */
    public static function explode($str)
    {
        // Initiate $in var to TRUE
        $in = true;

        // Check if parameter is "not bla|bla"
        if (strpos($str, 'not ') === 0) {
            // Change $in var accordingly
            $in = false;

            // Strip 'not ' from string
            $str = substr($str, 4);
        }

        // Return two values in an array
        return array(preg_split('/(&?&(?![\da-z]{2,6};|#\d{2,4};|#x[\da-f]{2,4};)|\|)/iu', $str), $in);
    }

    /**
     * Merge two parameter values
     */
    public static function merge($haystack, $needles, $as_param = false)
    {
        // Prep the haystack
        if (! is_array($haystack)) {
            // Explode the param, forget about the 'not '
            list($haystack, ) = static::explode($haystack);
        }

        // Prep the needles
        if (! is_array($needles)) {
            list($needles, $in) = static::explode($needles);
        } else {
            $in = true;
        }

        // Choose function to merge
        $method = $in ? 'array_intersect' : 'array_diff';

        // Do the merge thing
        $merged = $method($haystack, $needles);

        // Change back to parameter syntax if necessary
        if ($as_param) {
            $merged = implode('|', $merged);
        }

        return $merged;
    }
}
