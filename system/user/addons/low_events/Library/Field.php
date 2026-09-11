<?php

namespace Low\Events\Library;

/**
 * Low Alphabet Fields class, for getting field info
 *
 * @package        low_events
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-events
 * @copyright      Copyright (c) 2012-2017, Low
 */
class Field
{
    /**
     * Native string fields
     */
    private static $_native_strings = array(
        'title',
        'url_title',
        'status'
    );

    /**
     * Native date fields
     */
    private static $_native_dates = array(
        'entry_date',
        'expiration_date',
        'comment_expiration_date',
        'recent_comment_date',
        'edit_date'
    );

    /**
     * Native numeric fields
     */
    private static $_native_numeric = array(
        'view_count_one',
        'view_count_two',
        'view_count_thee',
        'view_count_four',
        'comment_total'
    );

    // --------------------------------------------------------------------

    /**
     * Get fields
     *
     * @access     public
     * @return     array
     */
    public static function get()
    {
        static $cache = [];

        if (empty($cache)) {
            // Don't use the API anymore. It's legacy. We'll just use our own cache.
            $fields = ee('Model')
                ->get('ChannelField')
                ->fields('field_id', 'site_id', 'field_name', 'field_type')
                ->all();

            foreach ($fields as $row) {
                $cache[$row->field_id] = [
                    'site_id' => $row->site_id,
                    'name' => $row->field_name,
                    'type' => $row->field_type,
                    'column' => 'field_id_' . $row->field_id,
                    // 'table'   => $row->getDataTable()
                ];
            }
        }

        // Return the cached fields
        return $cache;
    }

    // --------------------------------------------------------------------

    /**
     * Get field id for given field short name
     *
     * @access      public
     * @param       string
     * @return      int|bool
     */
    public static function id($str)
    {
        // Get custom channel fields from cache
        $fields = static::get();

        // --------------------------------------
        // To be somewhat compatible with MSM, get the first ID that matches,
        // not just for current site, but all given.
        // --------------------------------------

        $site_ids = isset(ee()->TMPL)
            ? ee()->TMPL->site_ids
            : [ee()->config->item('site_id')];

        // Add 0 to site ids
        $site_ids[] = 0;

        // Filter the fields based on field name and site IDs
        $fields = array_filter($fields, function ($field) use ($str, $site_ids) {
            return ($field['name'] == $str && in_array($field['site_id'], $site_ids));
        });

        // return the first ID encountered, if any
        return empty($fields) ? false : key($fields);
    }

    // --------------------------------------------------------------------

    /**
     * Filter custom fields by given key/val
     */
    private function filter($key, $val)
    {
        return array_filter(static::get(), function ($field) use ($key, $val) {
            return ($field[$key] == $val);
        });
    }

    /**
     * Get database field column
     *
     * @access      public
     * @param       string
     * @param       mixed
     * @return      string|bool
     */
    public static function column($str)
    {
        if (static::isNative($str) || preg_match('/^field_id_\d+$/', $str)) {
            return $str;
        } elseif ($id = static::id($str)) {
            return 'field_id_' . $id;
        } else {
            return false;
        }
    }

    // --------------------------------------------------------------------

    /**
     * Check if given field is native
     */
    public static function isNative($field)
    {
        return in_array($field, array_merge(
            static::$_native_strings,
            static::$_native_dates,
            static::$_native_numeric
        ));
    }

    /**
     * Check if given field is native numeric
     */
    public static function isNumeric($field)
    {
        return in_array($field, array_merge(
            static::$_native_dates,
            static::$_native_numeric
        ));
    }
}
// End of file Field.php
