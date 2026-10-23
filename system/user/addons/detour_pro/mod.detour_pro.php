<?php

// This is what we have to do for EE2 support
require_once 'addon.setup.php';

use EEHarbor\DetourPro\FluxCapacitor\Base\Mod;

/**
 * Detour Pro Module Front End File
 *
 * @package     ExpressionEngine
 * @subpackage  Addons
 * @category    Module
 * @author      Mike Hughes - City Zen
 * @author      Tom Jaeger - EEHarbor
 * @link        http://eeharbor.com/detour_pro
 */

class Detour_pro extends Mod
{
    public $return_data;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Tag to add current URL as 404
     */
    public function not_found()
    {
        //if the URI is exct match of 404 template, we do nothing
        if (ee()->uri->uri_string=='' || ee()->uri->uri_string==ee()->config->item('site_404')) {
            return;
        }

        $original_url = trim((string) ee()->uri->uri_string);
        $site_id = ee()->config->item('site_id');
        $today = date('Y-m-d');

        $table = ee()->db->dbprefix('detours_not_found');
        $sql = "INSERT INTO {$table} (detour_id, original_url, hit_date, hits, site_id)
            VALUES (NULL, ?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE hits = hits + 1";

        ee()->db->query($sql, array($original_url, $today, $site_id));
    }
}
/* End of file mod.detour_pro.php */
/* Location: /system/expressionengine/third_party/detour_pro/mod.detour_pro.php */
