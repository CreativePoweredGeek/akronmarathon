<?php

use BoldMinded\DataGrab\DataTypes\AbstractDataTypeLegacy;

/**
 * DataGrab XML import class
 *
 * Allows XML imports
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_xml_legacy extends AbstractDataTypeLegacy
{
    public $type = 'XML';

    public $datatype_info = [
        'name' => 'XML (Legacy)',
        'version' => '1.0',
        'description' => 'Import data from an XML formatted file/feed/api response',
        'allow_comments' => true,
        'allow_subloop' => true
    ];

    public $settings = [
        'filename' => '',
        'path' => '',
        'importId' => 0,
    ];

    public $items;
    public $sub_item_ptr;

    /**
     * @param array $values
     * @return array[]
     */
    public function settings_form(array $values = []): array
    {
        return [
            [
                'title' => 'Filename or URL',
                'desc' => lang('datagrab_filename_instructions'),
                'fields' => [
                    'filename' => [
                        'required' => true,
                        'type' => 'text',
                        'value' => $this->get_value($values, "filename") ?: '{base_url}/my-file.xml',
                    ]
                ]
            ],
            [
                'title' => 'XML path',
                'desc' => 'The path within the XML to the element you want to import (eg, <code>/rss/channel/item</code>). If importing an RSS/ATOM feed, leave blank and it will try and guess. <strong>Note: this is not the path to the file.</strong>',
                'fields' => [
                    'path' => [
                        'required' => true,
                        'type' => 'text',
                        'value' => $this->get_value($values, "path"),
                    ]
                ]
            ]
        ];
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function fetch(string $data = '')
    {
        try {
            if ($data !== '') {
                $xml = $data;
            } else {
                $xml = $this->curlFetch($this->getFilename(), $this->settings['importId']);
            }
        } catch (Exception $exception) {
            return -1;
        }

        if ($xml === false) {
            $this->addError('Cannot open file/url: ' . $this->settings['filename']);
            return -1;
        }

        ee()->load->library('xmlparser');
        $xml_obj = ee()->xmlparser->parse_xml($xml);

        if ($xml_obj === false) {
            $this->addError('Cannot parse the XML from file/url: ' . $this->getFilename());
            return -1;
        }

        // Try to guess item path
        if ($this->settings["path"] == "") {
            if ($xml_obj->tag == "feed") {
                // ATOM feed
                $this->settings["path"] = '/feed/entry';
            } elseif ($xml_obj->tag == "rss") {
                // RSS feed
                $this->settings["path"] = '/rss/channel/item';
            }
        }

        $this->items = [];
        $this->_fetch_xml($xml_obj, $this->settings["path"], $this->items);

        if (empty($this->items)) {
            $this->addError(sprintf('No items were found. Please check file type, url/path to the file, and XML path (%s) to the entries are correct.', $this->settings["path"]));
            return -1;
        }
    }

    public function next()
    {
        // PHP 8.1 change
        if (!is_array($this->items)) {
            $this->items = (array) $this->items;
        }

        if (empty($this->items)) {
            return null;
        }

        $item = current($this->items);
        next($this->items);

        return $item;
    }

    public function fetch_columns(): array
    {
        try {
            $this->fetch();
            $columns = $this->next();

            while ($item = $this->next()) {
                $columns = array_merge($columns, $item);
            }

            if (!is_array($columns)) {
                $this->addError('Cannot find any data. Is the XML path correct? Is it a valid XML file? Run it through an <a href="https://www.w3schools.com/xml/xml_validator.asp">XML validator</a>');
                return [];
            }

            $titles = [];
            foreach ($columns as $idx => $title) {
                if (substr($idx, -1, 1) != "#") {
                    if (strlen($title) > 32) {
                        $title = substr(htmlspecialchars($title), 0, 32) . "...";
                    }
                    $titles[$idx] = $idx . " - eg, " . $title;
                }
            }

            return $titles;
        } catch (Error $error) {
            $this->addError($error->getMessage());
        }

        return [];
    }

    public function initialise_sub_item()
    {
        if ($this->datatype_info['allow_subloop'] !== true) {
            return false;
        }

        $this->sub_item_ptr = 0;
        return true;
    }

    public function get_sub_item(array $item, string $key, array $config = [], string $field = '', array $column = [])
    {
        $this->sub_item_ptr++;
        $no_elements = $this->get_item($item, $key . "#");

        if ($no_elements === false) {
            $no_elements = 99;
        }

        if ($no_elements == "") {
            $no_elements = 1;
        }

        if ($this->sub_item_ptr > $no_elements) {
            return false;
        }

        $new_id = $id;

        if ($this->sub_item_ptr > 1) {
            if (strpos($id, '@')) {
                $parts = explode("@", $id);
                $new_id = $parts[0] . '#' . $this->sub_item_ptr . '@' . $parts[1];
            } else {
                $new_id = $id . '#' . $this->sub_item_ptr;
            }
        }

        return $this->get_item($item, $new_id);
    }

    private function _fetch_xml($x, $search, &$items, $path = "", $element = 0, $in_element = false, $subpath = "")
    {
        $path = $path . "/" . $x->tag;

        if ($path == $search) {
            // Path matches exactly our search element - we are in a new item
            $element++;
            $items[$element] = [];
            $subpath = '';

            if (is_array($x->attributes)) {
                foreach ($x->attributes as $attr_key => $attr_value) {
                    $items[$element][$subpath . "@" . $attr_key] = $attr_value;
                }
            }
            $in_element = true;
        } elseif ($str = strstr($path, $search)) {
            // We are within an existing item  - get xpath of subcomponent
            $subpath = substr($str, strlen($search) + 1);

            if (!isset($items[$element][$subpath . "#"])) {
                $items[$element][$subpath . "#"] = 0;
            }

            $count = $items[$element][$subpath . "#"]++;

            if (isset($items[$element][$subpath])) {
                $subpath .= "#" . ($count + 1);
            }

        } else {
            $in_element = false;
        }

        if (gettype($x->children) != NULL && ($x->children) == 0) {
            // Element has children ie, is not a parent element
            if ($in_element) {
                // If within an item, add to its array
                $items[$element][$subpath] = $x->value;
            }
        } else {
            // Loop over all child elements...
            foreach ($x->children as $value) {
                // ...and recurse through xml structure
                $element = $this->_fetch_xml($value, $search, $items, $path, $element, $in_element, $subpath);
            }
        }

        // Add attributes
        if ($in_element && is_array($x->attributes)) {
            foreach ($x->attributes as $attr_key => $attr_value) {
                $items[$element][$subpath . "@" . $attr_key] = $attr_value;
            }
        }

        return $element;
    }

    private function _curl_fetch($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

        $data = curl_exec($ch);

        curl_close($ch);
        $info = curl_getinfo($ch);
        $httpCode = $info['http_code'];

        if (!$data) {
            $this->addError('cURL Error: ' . curl_error($ch));
        }

        if ($httpCode !== 200) {
            $this->addError('cURL Request Error Code: ' . $httpCode);
        }

        return $data;
    }

    private function _fsockopen_fetch($url)
    {
        $target = parse_url($url);

        $data = '';

        $fp = fsockopen($target['host'], 80, $error_num, $error_str, 8);

        if (is_resource($fp)) {
            fputs($fp, "GET {$url} HTTP/1.0\r\n");
            fputs($fp, "Host: {$target['host']}\r\n");
            fputs($fp, "User-Agent: EE/xmlgrab PHP/" . phpversion() . "\r\n\r\n");

            $headers = true;

            while (!feof($fp)) {
                $line = fgets($fp, 4096);

                if ($headers === false) {
                    $data .= $line;
                } elseif (trim($line) == '') {
                    $headers = false;
                }
            }

            fclose($fp);
        }

        return $data;
    }
}
