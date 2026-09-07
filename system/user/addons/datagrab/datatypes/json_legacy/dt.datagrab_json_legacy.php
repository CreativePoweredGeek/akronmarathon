<?php

use BoldMinded\DataGrab\DataTypes\AbstractDataTypeLegacy;

/**
 * DataGrab JSON import class
 *
 * Allows JSON imports
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_json_legacy extends AbstractDataTypeLegacy
{
    public $type = 'JSON';

    public $datatype_info = [
        'name' => 'JSON (Legacy)',
        'version' => '1.0',
        'description' => 'Import data from a JSON file/feed',
        'allow_comments' => false,
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
                        'value' => $this->get_value($values, 'filename') ?: '{base_url}/my-file.json',
                    ]
                ]
            ],
            [
                'title' => 'JSON Element',
                'desc' => 'Optional. The JSON file should return an array of objects to import. Some JSON APIs return the array within another element. eg, if the objects are returned in a <code>data</code> element you should specify it in this field. You can specify child nodes as well. eg, <code>/data/products</code>',
                'fields' => [
                    'path' => [
                        'required' => true,
                        'type' => 'text',
                        'value' => $this->get_value($values, 'path'),
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
                $json = $data;
            } else {
                $json = $this->curlFetch($this->getFilename(), $this->settings['importId']);
            }
        } catch (Exception $exception) {
            $this->addError($exception->getMessage());
            return -1;
        }

        if ($json === false) {
            $this->addError('Cannot open file/url: ' . $this->getFilename());
            return -1;
        }

        if (function_exists('json_decode') === false) {
            ee()->load->library('Services_Json');
        }

        $json_array = json_decode($json, true);

        if (empty($json_array)) {
            $this->addError('Cannot parse JSON in file/url: ' . $this->getFilename());
            return -1;
        }

        // Parse JSON in array $this->items
        if ($this->settings['path'] != '') {
            $path = explode('/', $this->settings['path']);
            if (count($path) > 1) {
                foreach ($path as $key) {
                    if (isset($json_array[$key])) {
                        $json_array = (array) $json_array[$key];
                    }
                }
                $this->items = $json_array;
            } else {
                $this->items = $json_array[$path[0]];
            }
        } else {
            $this->items = $json_array;
        }

        if (empty($this->items)) {
            $this->addError(sprintf('No items were found. Please check file type, url/path to the file, and JSON path (%s) to the entries are correct.', $this->settings["path"]));
            return -1;
        }
    }

    public function next()
    {
        // PHP 8.1 change
        if (!is_array($this->items)) {
            $this->items = (array) $this->items;
        }

        $item = current($this->items);
        next($this->items);

        if ($item === false) {
            return false;
        }

        $new = [];
        $this->_parse_json_into_array($item, $new);

        return $new;
    }

    public function fetch_columns(): array
    {
        try{
            $this->fetch();
            $columns = $this->next();

            while ($item = $this->next()) {
                $columns = array_merge($columns, $item);
            }

            $titles = [];
            foreach ($columns as $columnName => $title) {
                if ($this->isUniqueColumn($columnName)) {
                    if ($title && strlen($title) > 32) {
                        $title = substr(htmlspecialchars($title), 0, 32) . "...";
                    }
                    $titles[$columnName] = $columnName . " - eg, " . $title;
                }
            }

            return $titles;
        } catch (Error $error) {
            $this->addError($error->getMessage());
        }

        return [];
    }

    /**
     * When importing Grid data, or anything with repeated rows, we'll end up with possibly a very long
     * list of column names, e.g. something/0/title, something/1/title, something/2/title, but we don't
     * need every single column names, just the unique ones so we can configure what the import is looking for.
     *
     * @param string $columnName
     * @return bool
     */
    private function isUniqueColumn(string $columnName = ''): bool
    {
        if (
            substr($columnName, -1, 1) !== '#' &&
            preg_match('/^(\S+)\/(?<num>\d+)\/(\S+)?/u', $columnName, $matches) &&
            isset($matches['num']) && $matches['num'] > 0
        ) {
            return false;
        }

        return true;
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
        $value = false;

        if ($this->sub_item_ptr == -1) {
            return false;
        }

        $segments = explode("/", $key);

        if (count($segments) == 1) {
            // single element (not an array)
            $this->sub_item_ptr = -1;
            return $this->get_item($item, $key);
        }

        // if( ! is_numeric( $segments[ count( $segments ) - 2 ] ) ) {
        // 	$id .= "/" . $this->sub_item_ptr;
        // 	$segments = explode( "/", $id );
        // }

        // Failed attempt to handle deeply nested data sets
        //if (preg_match('/\/0\//', $id, $matches)) {
        //    $rev = array_reverse($segments);
        //    $key = array_search('0', $rev);
        //    $rev[$key] = $this->sub_item_ptr;
        //    $new_id_x = implode('/', array_reverse($rev));
        //
        //    if (isset($item[$new_id_x])) {
        //        $value = $this->get_item($item, $new_id_x);
        //    }
        //}

         if (is_numeric($segments[count($segments) - 2])) {
            // handle item/0/item array of objects
            $segments[count($segments) - 2] = $this->sub_item_ptr;
            $new_id = implode("/", $segments);
            if (isset($item[$new_id])) {
                $value = $this->get_item($item, $new_id);
            }
        } elseif (is_numeric($segments[count($segments) - 1])) {
            // handle item/0 array
            $segments[count($segments) - 1] = $this->sub_item_ptr;
            $new_id = implode("/", $segments);
            if (isset($item[$new_id])) {
                $value = $this->get_item($item, $new_id);
            }
        } else {
            $value = $this->get_item($item, $key);
            $this->sub_item_ptr = -1;
            return $value;
        }

        $this->sub_item_ptr++;

        return $value;
    }

    /**
     * @param stdClass|array $json_obj
     * @param array  $result
     * @param string $prefix
     * @return void
     */
    private function _parse_json_into_array($json_obj, array &$result = [], string $prefix = '')
    {
        $json_array = (array)$json_obj;

        foreach ($json_array as $key => $value) {
            $newKey = trim($prefix . '/' . $key, '/');

            if (is_object($value) || is_array($value)) {
                $this->_parse_json_into_array($value, $result, $newKey);
            } else {
                $result[$newKey] = $value;
            }
        }
    }
}
