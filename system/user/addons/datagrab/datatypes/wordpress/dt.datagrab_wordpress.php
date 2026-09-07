<?php

use BoldMinded\DataGrab\DataTypes\StructuredArray;
use BoldMinded\DataGrab\datatypes\wordpress\ParseException;
use BoldMinded\DataGrab\datatypes\wordpress\Parser;
use BoldMinded\DataGrab\Dependency\Cake\Utility\Hash;

require_once PATH_THIRD . 'datagrab/datatypes/xml/dt.datagrab_xml.php';

/**
 * DataGrab JSON import class
 *
 * Allows JSON imports
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_wordpress extends Datagrab_xml
{
    use StructuredArray;

    public string $type = 'WordPress';

    public array $datatype_info = [
        'name' => 'WordPress',
        'version' => '2.0',
        'description' => 'Import data from a WordPress export file.',
        'allow_subloop' => true,
        'allow_multiple_fields' => true
    ];

    public array $settings = [
        'filename' => '',
        'post_type' => 'post',
    ];

    private array $postTypes = [];

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
                        'value' => $this->get_value($values, 'filename') ?: '{base_url}/my-file.xml',
                    ]
                ]
            ],
            [
                'title' => 'Post Type',
                'desc' => 'Choose which post type to import. If you\'re importing a basic blog it will most likely be 
                    <code>post</code>. You can only import one post type at a time. If you have additional custom
                    post types you will need to create another import using a different Channel. If you are wanting
                    to import the <code>attachment</code> post type you will probably want to choose <code>File</code>
                    in the Import type above, then choose a directory to import the files to.',
                'fields' => [
                    'post_type' => [
                        'required' => true,
                        'type' => 'text',
                        'value' => $this->get_value($values, 'post_type') ?: 'post',
                    ]
                ]
            ],
        ];
    }

    protected function xmlToStructuredArray(string $xmlString, string $basePath): array
    {
        try {
            return Parser::parseString($xmlString, $this->settings['post_type']);
        } catch (ParseException $e) {
            $this->addError($e->getMessage());
            return [];
        }
    }

    protected function flatten(array $array, string $path): void
    {
        $posts = $array['posts'] ?? [];

        $filtered = $this->filterPostTypes($posts);

        $this->items = array_map(function ($item) use ($path) {
            return $this->toStructuredArray($item, true);
        }, $filtered);

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
    }

    public function fetchPostTypes(): array
    {
        return array_unique(array_values($this->postTypes));
    }

    private function filterPostTypes(array $posts = [])
    {
        return array_values(array_filter($posts, function ($item) {
            $this->postTypes[] = $item['post_type'];

            if (
                isset($this->settings['post_type']) &&
                $this->settings['post_type'] !== $item['post_type']
            ) {
                return false;
            }

            return true;
        }));
    }
}
