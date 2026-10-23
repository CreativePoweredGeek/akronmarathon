<?php

/**
 * EEHarbor Module File
 *
 * PHP Version 5.3
 *
 * @category   Module
 * @package    ExpressionEngine
 * @subpackage Addons
 * @author     EEHarbor <help@eeharbor.com>
 * @license    https://eeharbor.com/license EEHarbor Add-on License
 * @link       https://eeharbor.com/
 */

require_once 'addon.setup.php';

use EEHarbor\DetourPro\FluxCapacitor\FluxCapacitor;
use EEHarbor\DetourPro\FluxCapacitor\Base\Ext;

class Detour_pro_ext extends Ext
{
    public $settings       = array();
    public $name           = 'Detour Pro';
    public $description    = 'Reroute urls to another URL.';
    public $settings_exist = 'n';
    public $docs_url       = 'https://eeharbor.com/detour-pro/documentation';

    public function __construct()
    {
        parent::__construct();

        $this->settings = $this->flux->getSettings(true);
        $this->settings = $this->sanitizeSettings($this->settings);

        // Ensure this hook exists for already-installed sites without forcing a reinstall.
        if (defined('REQ') && REQ == 'CP') {
            $this->register_extension('cp_js_end');
        }
    }

    public function activateExtension()
    {
        ee()->db->where('class', 'Detour_ext');
        ee()->db->delete('extensions');

        ee()->functions->clear_caching('db');

        $this->register_extension('sessions_start', null, 1, 'n');
        $this->register_extension('cp_custom_menu');
        $this->register_extension('cp_js_end');
    }

    public function disableExtension()
    {
        parent::disableExtension();

        ee()->functions->clear_caching('db');
    }

    public function updateExtension($current = false)
    {
        $updated = false;

        if (!$current || $current == $this->version) {
            return false;
        }

        // add cp_custom_menu hook
        if (version_compare($current, '2.0.11', "<")) {
            $this->register_extension("cp_custom_menu");
            $updated = true;
        }

        // Add add-ons page license status adapter.
        if (version_compare($current, '4.0.1', "<")) {
            $this->register_extension("cp_js_end");
            $updated = true;
        }

        if ($updated) {
            $this->update_version();
        }
    }

    /**************************************************\
     ******************* ALL HOOKS: *******************
    \**************************************************/

    public function sessions_start()
    {
        if (REQ == 'CP') {
            return false;
        }

        if (isset($this->settings['url_detect']) && $this->settings['url_detect'] == 'php') {
            $site_index_file = (ee()->config->item('site_index')) ? ee()->config->item('site_index') . '/' : '';
            $url = str_replace($site_index_file, '', $_SERVER['REQUEST_URI']);
            $baseUrlPath = ee()->config->item('base_url');

            $position = strpos($url,"?");

            if (is_int($position)) {
                $url = substr($url, 0 , $position);
            }

            $url = ltrim($url, '/');
            if (empty($this->settings['allow_trailing_slash']) || $this->settings['allow_trailing_slash'] != 1) {
                $url = rtrim($url, '/');
            }

        } else {
            $url = trim((string) ee()->uri->uri_string);
        }

        $url = urldecode($url);

        $query_literal = false;

        $sql = "SELECT
                    detour_id,
                    original_url,
                    new_url,
                    detour_method,
                    start_date,
                    end_date
                FROM
                    exp_detours
                WHERE
                    (start_date IS NULL OR start_date <= NOW())
                    AND (end_date IS NULL OR end_date >= NOW())
                    AND ? LIKE REPLACE(original_url, '_', '[_') ESCAPE '['
                    AND site_id = ?
                ORDER BY
                    CASE
                        WHEN LOCATE('%', original_url) = 0 THEN 0
                        WHEN LOCATE('%%', original_url) > 0 THEN 1
                        ELSE 2
                    END ASC,
                    CHAR_LENGTH(REPLACE(original_url, '%', '')) DESC,
                    CHAR_LENGTH(original_url) DESC,
                    detour_id ASC";
        $detour = ee()->db->query($sql, [$url, ee()->config->item('site_id')])->row_array();

        if (empty($detour) && !empty($this->settings['allow_trailing_slash']) && $this->settings['allow_trailing_slash'] == 1) {
            $search = $url . "/";
            $sql = "SELECT
                        detour_id,
                        original_url,
                        new_url,
                        detour_method,
                        start_date,
                        end_date
                    FROM
                        exp_detours
                    WHERE
                        (start_date IS NULL OR start_date <= NOW())
                        AND (end_date IS NULL OR end_date >= NOW())
                        AND ? LIKE REPLACE(original_url, '_', '[_') ESCAPE '['
                        AND site_id = ?
                    ORDER BY
                        CASE
                            WHEN LOCATE('%', original_url) = 0 THEN 0
                            WHEN LOCATE('%%', original_url) > 0 THEN 1
                            ELSE 2
                        END ASC,
                        CHAR_LENGTH(REPLACE(original_url, '%', '')) DESC,
                        CHAR_LENGTH(original_url) DESC,
                        detour_id ASC";
            $detour = ee()->db->query($sql, [$search, ee()->config->item('site_id')])->row_array();
        }

        if (empty($detour) && !empty($this->settings['allow_qs']) && $this->settings['allow_qs'] == 1) {
            $search = $url . "?" . http_build_query($_GET);
            $sql = "SELECT
                        detour_id,
                        original_url,
                        new_url,
                        detour_method,
                        start_date,
                        end_date
                    FROM
                        exp_detours
                    WHERE
                        (start_date IS NULL OR start_date <= NOW())
                        AND (end_date IS NULL OR end_date >= NOW())
                        AND ? LIKE REPLACE(original_url, '_', '[_') ESCAPE '['
                        AND site_id = ?
                    ORDER BY
                        CASE
                            WHEN LOCATE('%', original_url) = 0 THEN 0
                            WHEN LOCATE('%%', original_url) > 0 THEN 1
                            ELSE 2
                        END ASC,
                        CHAR_LENGTH(REPLACE(original_url, '%', '')) DESC,
                        CHAR_LENGTH(original_url) DESC,
                        detour_id ASC";
                $detour = ee()->db->query($sql, [$search, ee()->config->item('site_id')])->row_array();

            // This is a detour set up like the following:
            // old: resources/search/?color=red
            // new: resources/search/?color=blue
            // Where it doesn't make sense to pass the old filter parameter
            // since they want the param changed as well, flag the detour as a literal.
            if (!empty($detour)) {
                $query_literal = true;
            }
        }

        if (!empty($detour)) {
            $newUrl = $this->segmentReplace($url, $detour['original_url'], $detour['new_url'], $query_literal);
            $site_url = (ee()->config->item('site_url')) ? rtrim((string) ee()->config->item('site_url'), '/') . '/' : '';
            $site_index = (ee()->config->item('site_index')) ? rtrim((string) ee()->config->item('site_index'), '/') . '/' : '';
            $site_index = $site_url . $site_index;

            if (isset($this->settings['hit_counter']) && $this->settings['hit_counter'] == 'y') {
                // Update detours_hits table
                ee()->db->set('detour_id', $detour['detour_id']);
                ee()->db->set('hit_date', 'NOW()', false);
                ee()->db->insert('detours_hits');
            }

            if ($url != $newUrl) {
                if (substr((string) $detour['new_url'], 0, 4) == 'http') {
                    header('Location: ' . $newUrl, true, $detour['detour_method']);
                } else {
                    header('Location: ' . $site_index . ltrim((string) $newUrl, '/'), true, $detour['detour_method']);
                }
                ee()->extensions->end_script = true;
                exit;
            }
        }
    }

    public function cp_js_end()
    {
        $scripts = array();

        if (ee()->extensions->last_call !== false) {
            $scripts[] = ee()->extensions->last_call;
        }

        $scripts[] = <<<'JS'
(function () {
    var search = window.location.search || '';
    var cpRoute = '';

    if (search.charAt(0) === '?') {
        cpRoute = search.substring(1).split('&')[0];
    }

    if (!cpRoute) {
        var pathname = window.location.pathname || '';
        var cpIndex = pathname.indexOf('/cp/');
        if (cpIndex !== -1) {
            cpRoute = pathname.substring(cpIndex);
        }
    }

    cpRoute = cpRoute.replace(/\/+$/, '');
    if (cpRoute !== '/cp/addons') {
        return;
    }

    var statusByWidth = {
        2: 'expired'
    };

    var maxAttempts = 30;
    var attempt = 0;

    function appendExpiredRibbon() {
        var card = document.querySelector('div[data-addon="detour_pro"]');
        if (!card) {
            return false;
        }

        if (card.querySelector('.corner-ribbon.expired')) {
            return true;
        }

        var ribbonWrap = document.createElement('div');
        ribbonWrap.className = 'corner-ribbon-wrap';

        var ribbon = document.createElement('p');
        ribbon.className = 'corner-ribbon top-left expired shadow';
        ribbon.textContent = 'Expired';

        ribbonWrap.appendChild(ribbon);
        card.appendChild(ribbonWrap);

        return true;
    }

    function applyExpiredStatusFromRuntime() {
        attempt++;

        var statusImg = window.detour_proi;
        if (!statusImg) {
            if (attempt < maxAttempts) {
                setTimeout(applyExpiredStatusFromRuntime, 200);
            }
            return;
        }

        var width = Number(statusImg.width || statusImg.naturalWidth || 0);
        var status = statusByWidth[width] || '';

        if (status === 'expired') {
            appendExpiredRibbon();
            return;
        }

        if (attempt < maxAttempts) {
            setTimeout(applyExpiredStatusFromRuntime, 200);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyExpiredStatusFromRuntime);
    } else {
        applyExpiredStatusFromRuntime();
    }
})();
JS;

        return implode("\n", $scripts);
    }

    /**************************************************\
     ******************* ALL ELSE: *******************
    \**************************************************/

    protected function segmentReplace($url, $originalUrl, $newUrl, $query_literal)
    {
        $replace     = $this->headsOrTails($originalUrl);
        $segments    = ee()->uri->segment_array();
        $newSegments = array();


        $originalUrlClean = trim((string) $originalUrl, '%/');
        $newUrlClean      = trim((string) $newUrl, '%');


        if (empty($this->settings['allow_trailing_slash']) || $this->settings['allow_trailing_slash'] != 1) {
            $newUrlClean = rtrim($newUrlClean, '/');
        }

        switch ($replace) {
            case 'both':
                $newUrl = str_replace($originalUrlClean, $newUrlClean, $url);
                break;

            case 'head':
                $newUrl = str_replace($originalUrlClean, $newUrlClean, $url);
                $newUrl = substr($newUrl, 0, (strpos($newUrl, $newUrlClean) + strlen($newUrlClean)));
                break;

            case 'tail':
                $newUrl = str_replace($originalUrlClean, $newUrlClean, $url);
                $newUrl = substr($newUrl, strpos($newUrl, $newUrlClean), strlen($newUrl));
                break;
        }

        if ($_SERVER['QUERY_STRING'] && !empty($this->settings['allow_qs']) && $this->settings['allow_qs'] == 1 && !$query_literal) {
            // parse the URL to pull out the query and fragment to potentially reorder them (fragment needs to be last)
            $path = parse_url((string) $newUrl, PHP_URL_PATH);
            $query = parse_url((string) $newUrl, PHP_URL_QUERY);
            $fragment = parse_url((string) $newUrl, PHP_URL_FRAGMENT);

            // parse foo=bar&keywords=test into an array
            parse_str((string) $query, $result);

            // Combine the parameter arrays with the defined parameters overriding the GET params if relevant
            // e.g. redirect from /page to /other-page?thing=test, but with ?foo=bar in the original request,
            // the final URL should be /other-page?thing=test&foo=bar (append foo=bar)
            // But that same redirect with ?thing=old&foo=bar should resolve to /other-page?thing=test&foo=bar
            // (use the Detour defined `thing` value to overwrite the user-defined parameter, and append foo=bar)
            $params = array_merge($_GET, $result);

            $newUrl = $path
                . (count($params) > 0 ? '?' . http_build_query($params) : '')
                . ($fragment ? '#' . $fragment : '');
        }

        return $newUrl;
    }

    protected function headsOrTails($original_url)
    {
        if (substr((string) $original_url, -2, 2) == '%%' && substr((string) $original_url, 0, 2) == '%%') {
            return 'both';
        }

        if (substr((string) $original_url, -2, 2) == '%%' && substr((string) $original_url, 0, 2) != '%%') {
            return 'tail';
        }

        if (substr((string) $original_url, -2, 2) != '%%' && substr((string) $original_url, 0, 2) == '%%') {
            return 'head';
        }

        return 'none';
    }

    /**
     * Remove non-serializable values (e.g. Closures from addon.setup.php)
     * so extension settings can be serialized safely during install.
     */
    private function sanitizeSettings($settings)
    {
        if (!is_array($settings)) {
            return array();
        }

        $clean = array();
        foreach ($settings as $key => $value) {
            if ($value instanceof \Closure) {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizeSettings($value);
                continue;
            }

            if (is_object($value)) {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
//END CLASS
