<?php

use BoldMinded\DataGrab\DataTypes\AbstractDataType;
use BoldMinded\DataGrab\Dependency\Cake\Utility\Hash;
use BoldMinded\DataGrab\Service\PreflightXml;

/**
 * DataGrab XML import class
 *
 * Allows XML imports
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_xml extends AbstractDataType
{
    public string $type = 'XML';

    public array $datatype_info = [
        'name' => 'XML',
        'version' => '2.0',
        'description' => 'Import data from an XML formatted file/feed/api response',
        'allow_comments' => true,
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
                        'value' => $this->get_value($values, "filename") ?: '{base_url}/my-file.xml',
                    ]
                ]
            ],
            [
                'title' => 'XML path',
                'desc' => 'The path within the XML to the element you want to import (eg, <code>/rss/channel/item</code>). 
                    If importing an RSS/ATOM feed, leave blank and it will try and guess. 
                    <strong>Note: this is not the path to the file.</strong>',
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
                $xmlString = $data;
            } else {
                if (!$this->getFilename()) {
                    $this->addError('You must supply a filename/url.');
                    return -1;
                }

                $xmlString = $this->curlFetch($this->getFilename(), $this->settings['importId']);

                $issues = PreflightXml::check($xmlString);
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
            return -1;
        }

        if ($xmlString === false) {
            $this->addError('Cannot open file/url: ' . $this->settings['filename']);
            return -1;
        }

        // Turn it into the new dot notation for searching purposes Hash::get()
        $path = ltrim(str_replace('/', '.', $this->settings['path'] ?? ''), '.');

        try {
            // Allow parsing errors to be caught
            libxml_use_internal_errors(true);

            $array = $this->xmlToStructuredArray($xmlString, $path);
        } catch (Exception $exception) {
            if ($parseErrors = libxml_get_errors()) {
                $this->addError(sprintf(
                    'Invalid XML: %s: Line #%s.',
                    $parseErrors[0]->message,
                    $parseErrors[0]->line
                ));
            } else {
                $this->addError(sprintf(
                    'Invalid XML: %s.',
                    $exception->getMessage()
                ));
            }

            return -1;
        }

        if (!is_array($array)) {
            $this->addError(sprintf(
                'Invalid XML: %s.',
                json_encode($array)
            ));

            return -1;
        }

        $this->flatten($array, $path);

        if (empty($this->items)) {
            $this->addError(sprintf('No items were found. Please check file type, url/path to the file, 
            and XML path (%s) to the entries are correct.', $this->settings["path"]));

            return -1;
        }

        return 1;
    }

    protected function flatten(array $array, string $path): void
    {
        if (!$path) {
            $firstKey = array_key_first($array);

            if ($firstKey === 'feed') {
                // ATOM feed
                $path = 'feed.entry';
            } elseif ($firstKey === 'rss') {
                // RSS feed
                $path = 'rss.channel.item';
            } else {
                $this->addError('Cannot find valid XML path to import from.');
            }
        }

        $this->items = Hash::get($array, $path);

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

    /**
     * Normally transforming an xml string into an array improperly nests
     * child elements. Duplicate keys are not distinct, they're grouped together.
     * This preserves the array order and maintains the hierarchical structure.
     */
    protected function xmlToStructuredArray(string $xmlString, string $basePath): array {
        // Hard-deny external entity resolution (XXE) and network access during parse.
        libxml_set_external_entity_loader(static fn() => null);
        $xml = new SimpleXMLElement($xmlString, LIBXML_NONET);

        // Convert dot notation basePath (e.g., "root.entry.something.something") to XPath
        $xpath = '/' . str_replace('.', '/', $basePath);

        // Use XPath to locate the correct node
        $nodes = $xml->xpath($xpath);

        if (!$nodes) {
            return []; // Return empty array if no matching nodes are found
        }

        $entries = [];
        foreach ($nodes as $node) {
            $entries[] = $this->simpleXmlToArray($node);
        }

        // Wrap the extracted data in the correct hierarchy
        return $this->wrapInHierarchy(explode('.', $basePath), $entries);
    }

    protected function simpleXmlToArray(SimpleXMLElement $xml): array {
        $result = [];

        foreach ($xml->children() as $child) {
            $grandChildren = $child->children();
            $count = count($grandChildren);
            if (!empty($grandChildren) && $count > 0) {
                $result[] = [$child->getName() .'/' . self::PARENT_NODE_NAME => sprintf(
                    $count === 1
                        ? self::INCLUDES_CHILDREN_LABEL
                        : self::INCLUDES_CHILDREN_LABEL_PLURAL
                    , count($grandChildren)
                )];
            }

            // Capture attributes
            //foreach ($child->attributes() as $attrName => $attrValue) {
            //    $result[] = ["@{$attrName}" => (string) $attrValue];
            //}

            $name = $child->getName();
            $value = trim((string) $child);

            // If the node has children, recursively parse it
            if ($child->count() > 0) {
                $value = $this->simpleXmlToArray($child);
            }

            // Maintain order and prevent merging of duplicate keys
            $result[] = [$name => $value];
        }

        return $result;
    }

    protected function wrapInHierarchy(array $pathParts, array $entries) {
        $nested = $entries;

        // Reconstruct hierarchy from the base path
        while (!empty($pathParts)) {
            $key = array_pop($pathParts);
            $nested = [$key => $nested];
        }

        return $nested;
    }
}
