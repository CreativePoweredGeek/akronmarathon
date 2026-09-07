<?php

use BoldMinded\DataGrab\DataTypes\AbstractDataType;
use BoldMinded\DataGrab\DataTypes\StructuredArray;
use BoldMinded\DataGrab\Dependency\Cake\Utility\Hash;
use BoldMinded\DataGrab\Service\PreflightJson;

/**
 * DataGrab JSON import class
 *
 * Allows JSON imports
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_json extends AbstractDataType
{
    use StructuredArray;

    public string $type = 'JSON';

    public array $datatype_info = [
        'name' => 'JSON',
        'version' => '2.0',
        'description' => 'Import data from a JSON file/feed',
        'allow_comments' => false,
        'allow_subloop' => true
    ];

    public array $settings = [
        'filename' => '',
        'path' => '',
        'importId' => 0,
    ];

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
                'desc' => 'The JSON file should return an array of objects to import. Some JSON APIs return the array within another element. eg, if the objects are returned in a <code>data</code> element you should specify it in this field. You can specify child nodes as well. eg, <code>/data/products</code>',
                'fields' => [
                    'path' => [
                        'required' => true,
                        'type' => 'text',
                        'value' => $this->get_value($values, 'path') ?: '/',
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

                $issues = PreflightJson::check($json);
                if (!empty($issues)) {
                    foreach ($issues as $issue) {
                        if (isset($issue['line'])) {
                            $message = sprintf(
                                'Line %d: %s',
                                $issue['line'] ,
                                $issue['msg']
                            );
                        } else {
                            $message = $issue['msg'];
                        }

                        $this->addWarning($message);
                    }
                }
            }
        } catch (Exception $exception) {
            $this->addError($exception->getMessage());
            return -1;
        }

        if ($json === false) {
            $this->addError('Cannot open file/url: ' . $this->getFilename());
            return -1;
        }

        $json_array = json_decode($json, true);

        if (empty($json_array)) {
            $this->addError('Cannot parse JSON in file/url: ' . $this->getFilename());
            return -1;
        }

        // Turn it into the new dot notation for searching purposes Hash::get()
        $path = ltrim(str_replace('/', '.', $this->settings['path'] ?? ''), '.');

        if ($path === '' || $path === '/') {
            $this->items = $json_array;
        } else {
            $structuredArray = $this->toStructuredArray($json_array);
            $this->items = Hash::get($structuredArray, $path);
        }

        foreach ($this->items as $item) {
            $flatMappingPaths = [];
            $flatItem = Hash::flatten($item, '/');

            foreach ($flatItem as $nodePath => $value) {
                $nodePath = preg_replace('/^(\d+\/)/', '', $nodePath);

                if (!isset($flatMappingPaths[$nodePath])) {
                    $flatMappingPaths[$nodePath] = $value;
                }
            }

            $this->itemsFlat[] = $flatMappingPaths;
        }

        if (empty($this->items)) {
            $this->addError(sprintf('No items were found. Please check file type, url/path to the file, and JSON path (%s) to the entries are correct.', $this->settings["path"]));
            return -1;
        }

        return 1;
    }
}
