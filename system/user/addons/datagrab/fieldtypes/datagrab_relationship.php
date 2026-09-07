<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\App;
use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;

/**
 * DataGrab Relationship fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_relationship extends AbstractFieldType
{
    public int $order = 1;

    protected string $docUrl = 'https://docs.boldminded.com/datagrab/docs/field-types/relationships';

    public function register_setting(string $fieldName): array
    {
        return [
            $fieldName => [
                'value',
                'relationship_field',
                'separator',
            ],
        ];
    }

    public function display_configuration(Importer $importer, string $fieldName, string $fieldLabel, string $fieldType, bool $fieldRequired = false, array $data = []): array
    {
        $config = [];
        $config['label'] = $this->displayLabel($fieldLabel, $fieldName, $fieldRequired, 'relationship');
        $fieldSettings = $data['field_settings'][$fieldName] ?? [];

        $fieldSets = ee('View')
            ->make('ee:_shared/form/section')
            ->render([
                'name' => 'fieldset_group',
                'settings' => $this->getFormFields(
                    $fieldName,
                    $fieldSettings,
                    $data,
                    $this->getSavedFieldValues($data, $fieldName),
                )
            ]);

        $config['value'] = $fieldSets;

        return $config;
    }

    public function getFormFields(
        string $fieldName,
        array $fieldSettings,
        array $data = [],
        array $savedFieldValues = [],
        string $contentType = 'channel',
    ): array {
        $fieldOptions[] = [
            'title' => 'Import Value',
            'desc' => '',
            'fields' => [
                $fieldName . '[value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Field to match',
            'desc' => 'Choose which field will be used to match a valid entry and create a relationship.',
            'fields' => [
                $fieldName . '[relationship_field]' => [
                    'type' => 'dropdown',
                    'choices' => $data['all_fields'],
                    'value' => $savedFieldValues['relationship_field'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Separator',
            'desc' => 'If importing multiple entries, choose which character to use to separate them. 
                If you are importing a single entry, this will be ignored. 
                Choose a value that will not appear in an entry title, otherwise it will not be able to match the entry.',
            'fields' => [
                $fieldName . '[separator]' => [
                    'type' => 'dropdown',
                    'choices' => [
                        ',' => 'Comma ( , )',
                        '|' => 'Pipe ( | )',
                        "||" => 'Double Pipe ( || )',
                        ';' => 'Semicolon ( ; )',
                        "\n" => 'New Line ( \n )',
                    ],
                    'value' => $savedFieldValues['separator'] ?? '',
                ]
            ]
        ];

        // @todo don't think this is actually needed to maintain feature parity with v5
        //$fieldOptions[] = [
        //    'title' => 'Action to take when an entry is updated',
        //    'desc' => '',
        //    'fields' => [
        //        $fieldName . '[unique]' => [
        //            'type' => 'dropdown',
        //            'choices' => [
        //                '0' => 'Keep existing relationships and append new',
        //                '-1' => 'Delete all existing relationships and replace with new',
        //            ],
        //            'value' => $savedFieldValues['unique'] ?? '-1',
        //        ]
        //    ]
        //];

        return $fieldOptions;
    }

    /**
     * If multiple properties appear with the same name, e.g. courses/title, courses/title#2, courses/title#3
     * then we want assign all of them to the same Relationship field if it's a top level field.
     * See grid-with-relationships.xml test file. This maintains legacy/original operations.
     */
    public function preparePostData(ImportField $importField): array
    {
        if ($importField->contentType === 'grid') {
            return $this->preparePostDataGrid($importField);
        }

        if ($importField->importer->dataType->initialise_sub_item()) {
            $data = [
                'sort' => [],
                'data' => [],
            ];

            $this->order = 1;

            // Loop over sub items
            while ($subItem = $importField->importer->dataType->get_sub_item(
                $importField->importItem,
                $importField->propertyName,
                $importField->importer->settings,
                $importField->fieldName,
            )) {
                $sortedEntries = $this->getSortedRelationships($importField);

                foreach ($sortedEntries as $entryId => $entryTitle) {
                    $data['data'][] = $entryId;
                    $data['sort'][] = $this->order++;

                    // -------------------------------------------
                    //  'datagrab_prepare_post_data_relationships' hook
                    //      - Extensions implementing this hook must accept $data as a reference
                    //
                    if ($importField->importer->extensions->active_hook('datagrab_prepare_post_data_relationships')) {
                        $importField->importer->logger->log('Calling datagrab_prepare_post_data_relationships() hook.');
                        $importField->importer->extensions->call(
                            'datagrab_prepare_post_data_relationships',
                            $data,
                            $subItem,
                            $importField->fieldName,
                            $importField->fieldId,
                            $this->order,
                            $importField->importer
                        );
                    }
                    //
                    // -------------------------------------------
                }

                if (App::isAddonInstalled('publisher')) {
                    // Fudge some data that Publisher_grid_hooks->grid_save() will use
                    // to build the cache result that will eventually be handled by the Relationship field.
                    $_POST['field_id_' . $importField->fieldId] = [
                        'rows' => $data
                    ];
                }
            }

            return $data;
        }

        return [];
    }

    /**
     * If multiple properties appear with the same name, e.g. courses/title, courses/title#2, courses/title#3
     * then we want each to be assigned to separate grid rows, instead of all assigned to the first row.
     * See grid-with-relationships.xml test file. This maintains legacy/original operations.
     */
    public function preparePostDataGrid(ImportField $importField): array
    {
        $data = [];

        $sortedEntries = $this->getSortedRelationships($importField);

        foreach ($sortedEntries as $entryId => $entryTitle) {
            $data['data'][] = $entryId;
            $data['sort'][] = $this->order++;

            // -------------------------------------------
            //  'datagrab_prepare_post_data_relationships' hook
            //      - Extensions implementing this hook must accept $data as a reference
            //
            if ($importField->importer->extensions->active_hook('datagrab_prepare_post_data_relationships')) {
                $importField->importer->logger->log('Calling datagrab_prepare_post_data_relationships() hook.');
                $importField->importer->extensions->call(
                    'datagrab_prepare_post_data_relationships',
                    $data,
                    $importField->propertyValue,
                    $importField->fieldName,
                    $importField->fieldId,
                    $this->order,
                    $importField->importer
                );
            }
            //
            // -------------------------------------------
        }

        if (App::isAddonInstalled('publisher')) {
            // Fudge some data that Publisher_grid_hooks->grid_save() will use
            // to build the cache result that will eventually be handled by the Relationship field.
            $_POST['field_id_' . $importField->fieldId] = [
                'rows' => $data
            ];
        }

        return $data;
    }

    public function finalPostData(
        ImportField $importField,
        array $existingData = [],
    ) {
        // In Relationships case, if the data element is empty, no relationships, then we need to unset the
        // field entirely, otherwise the rebuild_post_data event will not trigger, so it's impossible to assign
        // more than 1 relationship at a time. Without this it will create the relationships, but continually
        // reset their assignments to 0 if the entry is updated again in the same import routine.
        $fieldData = $existingData['field_id_' . $importField->fieldId]['data'] ?? [];

        if (empty($fieldData)) {
            return null;
        }

        return $existingData['field_id_' . $importField->fieldId];
    }

    public function rebuildPostData(
        Importer $importer,
        int      $fieldId = 0,
        array    $existingData = [],
        array    $entryData = []
    ) {
        $where = [
            'parent_id' => $existingData['entry_id'] ?? $entryData['entry_id'] ?? 0,
            'field_id' => $fieldId
        ];

        // -------------------------------------------
        //  'datagrab_rebuild_relationships_query' hook
        //
        if ($importer->extensions->active_hook('datagrab_rebuild_relationships_query')) {
            $importer->logger->log('Calling datagrab_rebuild_relationships_query() hook.');
            $query = $importer->extensions->call('datagrab_rebuild_relationships_query', $where);
        } else {
            ee()->db->select('child_id, order');
            ee()->db->where($where);
            ee()->db->order_by('order');
            $query = ee()->db->get('exp_relationships');
        }
        //
        // -------------------------------------------

        $d = [];
        $sort = [];

        foreach ($query->result_array() as $row) {
            $d[] = $row['child_id'];
            $sort[] = $row['order'];
        }

        // Rebuild selections array
        return [
            'data' => $d,
            'sort' => $sort
        ];
    }

    /**
     * @param mixed    $subItem
     * @param mixed    $fieldName
     * @param array    $data
     * @param int      $fieldId
     * @param Importer $importer
     * @param string   $contentType
     * @return array
     */
    public function getSortedRelationships(ImportField $importField): array
    {
        // Check whether item matches a valid entry and create a relationship
        // Check which field to compare
        if (!isset($importField->fieldImportConfig['relationship_field'])) {
            // If not set (usually old saved import) then default to title
            $relationshipFieldName = 'title';
        } else {
            // Custom field
            $relationshipFieldName = $importField->fieldImportConfig['relationship_field'];
        }

        $relatedValue = $importField->propertyValue;
        $relationshipFieldName = str_replace("exp_channel_titles.", "", $relationshipFieldName) ?: 'title';
        $allowMultiple = boolval($importField->fieldSettings['allow_multiple'] ?? 0);
        $separator = $importField->fieldImportConfig['separator'] ?? ',';

        if ($allowMultiple && $separator && strpos($importField->propertyValue, $separator) !== false) {
            $relatedValue = explode($separator, $importField->propertyValue);

            if (is_array($relatedValue)) {
                $relatedValue = array_map('trim', $relatedValue);
            }
        }

        $channelIds = $importField->field['channels'] ?? [];
        $dictionaryValueField = ($relationshipFieldName === 'entry_id') ? 'title' : $relationshipFieldName;

        // Make sure we have actual entry IDs
        if ($allowMultiple && is_array($relatedValue) && !empty($relatedValue)) {
            $builder = ee('Model')->get('ChannelEntry')
                ->filter($relationshipFieldName, 'IN', $relatedValue);

            if (!empty($channelIds)) {
                $builder->filter('channel_id', 'IN', $channelIds);
            }

            $entries = $builder->all()->getDictionary('entry_id', $dictionaryValueField);
        } else {
            $builder = ee('Model')->get('ChannelEntry')
                ->filter($relationshipFieldName, $relatedValue);

            if (!empty($channelIds)) {
                $builder->filter('channel_id', 'IN', $channelIds);
            }

            $entries = $builder->all()->getDictionary('entry_id', $dictionaryValueField);
        }

        if (!is_array($relatedValue)) {
            $relatedValue = [$relatedValue];
        }

        if ($relationshipFieldName === 'entry_id') {
            $sortedEntries = $this->sortArrayByArray($entries, $relatedValue);
        } else {
            $sortedEntries = array_flip($this->sortArrayByArray(array_flip($entries), $relatedValue));
        }

        return $sortedEntries;
    }

    /**
     * @param array $toSort
     * @param array $sortBy
     * @return array
     */
    public static function sortArrayByArray(array $toSort, array $sortBy): array
    {
        $ordered = [];

        foreach ($sortBy as $key) {
            if (array_key_exists($key, $toSort)) {
                $ordered[$key] = $toSort[$key];
                unset($toSort[$key]);
            }
        }

        return $ordered + $toSort;
    }
}
