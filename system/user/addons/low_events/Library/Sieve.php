<?php

namespace Low\Events\Library;

/**
 * Sieves channel entries
 *
 * @package        low_events
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-events
 * @copyright      Copyright (c) 2012-2017, Low
 */
class Sieve
{
    private $params = [];
    private $joined = [];
    private $builder;

    /**
     * Initiates a new ChannelEntry builder object
     */
    public function __construct()
    {
        $this->builder = ee('Model')->get('ChannelEntry');
    }

    /**
     * Limits builder to given select items
     */
    public function select($field)
    {
        $field = (array) $field;

        foreach ($field as $key) {
            $this->builder->fields($key);
        }

        return $this;
    }

    /**
     * Applies parameter filters
     */
    public function params($key, $val = null)
    {
        if (is_array($key)) {
            $this->params = array_merge($this->params, $key);
        } else {
            $this->params[$key] = $val;
        }

        return $this;
    }

    /**
     * Adds a custom filter to this lot
     */
    public function filter($property, $operator, $value = false)
    {
        $this->builder->filter($property, $operator, $value);

        return $this;
    }

    /**
     * Applies filters
     */
    public function get()
    {
        // This is now!
        $now = ee()->localize->now;

        // Simple filters: site_id, entry_id, channel_id, url_title
        foreach (['site_id', 'entry_id', 'channel_id', 'url_title', 'year', 'month', 'day'] as $key) {
            if ($val = $this->param($key)) {
                $this->where($key, $val);
            }
        }

        // Filter by channel name; needs join with Channel
        if ($val = $this->param('channel')) {
            $this->where('channel_name', $val, 'Channel');
        }

        // Filter by status; needs default value
        if ($val = $this->param('status', 'open')) {
            $this->where('status', $val);
        }

        // Filter by author_id; needs additional val check
        if ($val = $this->param('author_id')) {
            // Allow for [NOT_]CURRENT_USER
            $val = str_replace('NOT_', 'not ', $val);
            $val = str_replace('CURRENT_USER', ee()->session->userdata('member_id'), $val);

            $this->where('author_id', $val);
        }

        // Filter by username; needs join with Member
        if ($val = $this->param('username')) {
            $this->where('username', $val, 'Author');
        }

        // Filter by group ID; needs join with Member
        if ($val = $this->param('group_id')) {
            $field = version_compare(APP_VER, '6.0', '>=') ? 'role_id' : 'group_id';
            $this->where($field, $val, 'Author');
        }

        // Filter by expired entries
        if ($this->param('show_expired') != 'yes') {
            $this->builder
                ->filterGroup()
                ->filter('expiration_date', 0)
                ->orFilter('expiration_date', '>', $now)
                ->endFilterGroup();
        }

        // Filter by future entries
        if ($this->param('show_future_entries') != 'yes') {
            $this->builder->filter('entry_date', '<', $now);
        }

        // Starting from entry ID #
        if ($val = $this->param('entry_id_from')) {
            $this->builder->filter('entry_id', '>=', $val);
        }

        // Ending at entry ID #
        if ($val = $this->param('entry_id_to')) {
            $this->builder->filter('entry_id', '<=', $val);
        }

        // Show Pages parameter
        if (($val = $this->param('show_pages')) && in_array($val, ['no', 'only'])) {
            // Init at 0 to force no results
            $page_ids = array(0);

            // Loop through all site pages rows, get entry ids from uris key
            foreach (ee()->config->item('site_pages') as $site) {
                $page_ids = array_merge($page_ids, array_keys($site['uris']));
            }

            // Change array to template syntax
            $val = ($val == 'no' ? 'not ' : '') . implode('|', $page_ids);

            // Set entry ID accordingly
            $this->where('entry_id', $val);
        }

        // Category groups
        if ($val = $this->param('category_group')) {
            $this->where('group_id', $val, 'Categories');
        }

        // Filter by category
        if ($val = $this->param('category')) {
            if (strpos($val, '&') > 0) {
                // Convert to array_pop
                $val = explode('&', $val);
                $val = array_filter($val, function ($v) {
                    return is_numeric($v);
                });

                // Execute query the old-fashioned way, so we don't interfere with active record
                // Get the entry ids that have all given categories assigned
                $q = ee()->db->query(
                    "SELECT entry_id, COUNT(*) AS num
                    FROM exp_category_posts
                    WHERE cat_id IN (" . implode(',', $val) . ")
                    GROUP BY entry_id HAVING num = " . count($val)
                );

                // If no entries are found, make sure we limit the query accordingly
                if ($q->num_rows()) {
                    $q = new \EllisLab\ExpressionEngine\Library\Data\Collection($q->result_array());
                    $entry_ids = $q->pluck('entry_id');
                } else {
                    $entry_ids = array(0);
                }

                $this->builder->filter('entry_id', 'IN', $entry_ids);
            } else {
                $this->where('cat_id', $val, 'Categories');
            }
        }

        // Search fields
        foreach ($this->params as $key => $val) {
            $field = substr($key, 7);
            if (substr($key, 0, 7) == 'search:' && ($column = Field::column($field))) {
                $this->search($column, $val);
                unset(ee()->TMPL->tagparams[$key]);
                unset(ee()->TMPL->search_fields[$field]);
            }
        }

        return $this->builder->all();
    }

    /**
     * Just return the entry IDs
     */
    public function ids()
    {
        return $this->get()->pluck('entry_id');
    }

    /**
     * Simple parameter getter
     */
    private function param($key, $default = null)
    {
        return array_key_exists($key, $this->params)
            ? $this->params[$key]
            : $default;
    }

    /**
     * Simple where filter
     */
    private function where($key, $val, $with = null)
    {
        if ($with) {
            if (! in_array($with, $this->joined)) {
                $this->builder->with($with);
                $this->joined[] = $with;
            }

            $key = $with . '.' . $key;
        }

        list($val, $in) = Param::explode($val);

        $this->builder->filter($key, ($in ? 'IN' : 'NOT IN'), $val);
    }

    /**
     * Handle search fields
     */
    private function search($field, $val)
    {
        // Initiate some vars
        $exact = $all = $starts = $ends = $exclude = false;
        $sep = '|';

        // Exact matches
        if (substr($val, 0, 1) == '=') {
            $val = substr($val, 1);
            $exact = true;
        }

        // Starts with matches
        if (substr($val, 0, 1) == '^') {
            $val = substr($val, 1);
            $starts = true;
        }

        // Ends with matches
        if (substr($val, -1) == '$') {
            $val = rtrim($val, '$');
            $ends = true;
        }

        // All items? -> && instead of |
        if (strpos($val, '&&') !== false) {
            $all = true;
            $sep = '&&';
        }

        // Excluding?
        if (substr($val, 0, 4) == 'not ') {
            $val = substr($val, 4);
            $exclude = true;
        }

        // Start clause group
        $this->builder->filterGroup();

        // Loop through each item of the parameter value and populate the group
        foreach (explode($sep, $val) as $item) {
            // Left hand side of the sql
            $key = $field;

            // Filter method
            $method = $all ? 'filter' : 'orFilter';

            // Are we building? Set to FALSE if IS_EMPTY, which needs a group
            $build = true;

            // whole word? Regexp search
            if (substr($item, -2) == '\W') {
                $operand = $exclude ? 'NOT REGEXP' : 'REGEXP';
                $item = preg_quote(substr($item, 0, -2));
                $item = "[[:<:]]{$item}[[:>:]]";
            }
            // Numeric operator!
            elseif (preg_match('/^([<>]=?)([\d\.]+)$/', $item, $match)) {
                $operand = $match[1];
                $val = $match[2];
            }
            // Empty item needs a group: should also account for NULL values as well as empty strings
            elseif ($item == 'IS_EMPTY') {
                $build = false;
                $group = $all ? 'filterGroup' : 'orFilterGroup';
                $glue = $exclude ? 'filter' : 'orFilter';
                $this->builder
                    ->$group()
                    ->filter($field, ($exclude ? '!=' : '='), '')
                    ->$glue($field, ($exclude ? 'IS NOT' : 'IS'), null)
                    ->endFilterGroup();
            }
            // Exact matching
            elseif ($exact || ($starts && $ends)) {
                // Use exact operand if empty or = was the first char in param
                $operand = $exclude ? '!=' : '=';
            }
            // Regular old LIKE matching
            else {
                // Use like operand in all other cases
                $operand = $exclude ? 'NOT LIKE' : 'LIKE';
                $item = "%{$item}%";

                // Allow for starts/ends with matching
                if ($starts) {
                    $item = ltrim($item, '%');
                }
                if ($ends) {
                    $item = rtrim($item, '%');
                }
            }

            // Apply the filter
            if ($build) {
                $this->builder->$method($field, $operand, $item);
            }
        }

        $this->builder->endFilterGroup();
    }
}

// End
