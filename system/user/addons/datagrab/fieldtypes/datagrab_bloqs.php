<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;

/**
 * DataGrab Bloqs fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_bloqs extends AbstractFieldType
{
    protected string $docUrl = 'https://docs.boldminded.com/datagrab/docs/field-types/bloqs';

    private $supportedFieldTypes = [
        'ansel',
        'date',
        'file',
        'relationship',
        'simple_grid',
        'simple_table',
    ];

    /**
     * Register a setting so it can be saved
     *
     * @param string $fieldName
     * @return array
     */
    public function register_setting(string $fieldName): array
    {
        return [
            $fieldName => [
                'bloqs',
                'unique',
                //'advanced',
            ]
        ];
    }

    public function display_configuration(
        Importer $importer,
        string   $fieldName,
        string   $fieldLabel,
        string   $fieldType,
        bool     $fieldRequired = false,
        array    $data = []
    ): array {
        $config = [];
        $config['label'] = $this->displayLabel($fieldLabel, $fieldName, $fieldRequired, 'bloqs');

        // Get current saved settings
        $default = $this->getSavedFieldValues($data, $fieldName);
        $savedGroups = $default['bloqs'] ?? [];

        $bloqs = $this->getBloqs($fieldName);
        $fieldOptions = [];

        foreach ($bloqs as $bloqDefinitionId => $bloqDefinition) {
            $groupHtml = ee('View')
                ->make('ee:_shared/form/section')
                ->render([
                    'name' => 'fieldset_group',
                    'settings' => $this->getFieldOptions(
                        $importer,
                        sprintf('%s[bloqs][%s]', $fieldName, $bloqDefinitionId),
                        $bloqDefinition['atoms'],
                        $data,
                        $savedGroups[$bloqDefinitionId]['atoms'] ?? [],
                    )
                ]);

            $fieldOptions[] = ee('View')
                ->make('datagrab:panel')
                ->render([
                    'heading' => $bloqDefinition['bloqName'],
                    'html' => $groupHtml
                ]);
        }

        $fieldOptions[] = ee('View')
            ->make('ee:_shared/form/section')
            ->render([
                'name' => 'Additional Options',
                'settings' => [
                    [
                        'title' => 'Action to take when an entry is updated',
                        'desc' => '',
                        'fields' => [
                            $fieldName . '[unique]' => [
                                'type' => 'dropdown',
                                'choices' => [
                                    '0' => 'Keep existing bloqs and append new',
                                    '-1' => 'Delete all existing bloqs',
                                ],
                                'value' => $default['unique'] ?? '-1',
                            ]
                        ]
                    ]
                ]
            ]);

        //$fieldOptions[] = [
        //    'title' => 'Advanced Import',
        //    'desc' => 'Importing data into a nested or component based Bloqs field requires selecting the node representing the the entire bloqs field.
        //        If selected, this will override all bloq and atom assignments above. You must choose one of the nodes labeld with <i>[includes N children]</i>,
        //        and all of the children must accurately represent each bloq and it\'s child atoms by their short names, and their desired nesting positions.
        //        For more information on how this works please <a href="'. $this->docUrl .'">reference the docs</a>. If you are not using a nested Bloqs
        //        field or importing component based bloqs you should leave this field blank, and assign the node values to the desired bloq and atom above
        //        to import into a flat Bloqs field.',
        //    'fields' => [
        //        $fieldName . '[advanced]' => [
        //            'type' => 'dropdown',
        //            'choices' => $data['data_fields'],
        //            'value' => $default['advanced'] ?? '',
        //        ]
        //    ]
        //];

        $config['value'] = ee('View')
            ->make('ee:_shared/form/section')
            ->render([
                'name' => 'fieldset_group',
                'settings' => $fieldOptions
            ]);

        return $config;
    }

    private function getFieldOptions(
        Importer $importer,
        string   $fieldName,
        array    $fields = [],
        array    $data = [],
        array    $savedFieldValues = [],
    ): array {
        $fieldOptions = [];

        foreach ($fields as $field) {
            if (in_array($field['type'], $this->supportedFieldTypes)) {
                $handler = $importer->getLoader()->loadFieldTypeHandler($field['type']);
                $handler->fieldPrefix = $fieldName . '[atoms]';
                $savedFieldSettings = $savedFieldValues[$field['id']] ?? [];

                $subFormFields = $handler->getFormFields(
                    $handler->createFieldName($field['id']),
                    $this->getAtomDefinitionSettings($field['id']),
                    $data,
                    $savedFieldSettings,
                    'bloqs',
                    $importer
                );

                $fieldOptions[] = ee('View')
                    ->make('ee:_shared/form/section')
                    ->render([
                        'name' => $field['label'],
                        'settings' => $subFormFields,
                    ]);
            } else {
                $fieldOptions[] = ee('View')
                    ->make('ee:_shared/form/section')
                    ->render([
                        'name' => $field['label'],
                        'settings' => [
                            [
                                'title' => 'Import Value',
                                'desc' => '',
                                'fields' => [
                                    $fieldName . '[atoms][' . $field['id'] . '][value]' => [
                                        'type' => 'dropdown',
                                        'choices' => $data['data_fields'],
                                        'value' => $savedFieldValues[$field['id']]['value'] ?? '',
                                    ]
                                ]
                            ]
                        ]
                    ]);
            }
        }

        return $fieldOptions;
    }

    /*
     * Example POST data
     *
     $_POST['field_id_X'] = [
        'blocks_new_block_1' => [
            'deleted' => 'false',
            'id' => 'blocks_new_block_1',
            'order' => '1',
            'blockDefinitionId' => '2',
            'componentDefinitionId' => 0,
            'cloneable' => '0',
            'draft' => '0',
            'values' =>
                [
                    'col_id_2' => 'A',
                    'col_id_3' => 'B',
                ],
            ],
        'blocks_new_block_2' => [
            'deleted' => 'false',
            'id' => 'blocks_new_block_2',
            'order' => '2',
            'blockDefinitionId' => '3',
            'componentDefinitionId' => 0,
            'cloneable' => '0',
            'draft' => '0',
            'values' =>
                [
                    'col_id_4' => 'http://apple.com',
                    'col_id_5' => '20',
                ],
        ],
        'blocks_new_block_3' => [
            'deleted' => 'false',
            'id' => 'blocks_new_block_3',
            'order' => '3',
            'blockDefinitionId' => '2',
            'componentDefinitionId' => 0,
            'cloneable' => '0',
            'draft' => '0',
            'values' =>
                [
                    'col_id_2' => 'C',
                    'col_id_3' => 'D',
                ],
        ],
        'tree_order' => [],
    ];
     */

    private function bloqArrayToTree(array $bloqs = [])
    {

    }

    private function validatePath(string $path, string $checkPath): string|bool
    {
        // Prepare/normalize
        $keyPatternMatch = preg_replace('/(?<=\/){n}(?=\/)/', '\d+', $checkPath ?? '');
        // Escape forward slashes, but don't double escape
        $preparedPattern = preg_replace('~(?<!\\\)/~', '\/', $keyPatternMatch);

        preg_match('/'. $preparedPattern .'$/', $path, $pathMatches);

        if (!empty(array_filter($pathMatches))) {
            return $checkPath;
        }

        return false;
    }

    public function hasFieldValue(array $settings = []): string
    {
        foreach ($settings['bloqs'] ?? [] as $bloq) {
            foreach ($bloq['atoms'] ?? [] as $atom) {
                if (($atom['value'] ?? '') !== '') {
                    return $atom['value'];
                }
            }
        }

        return '';
    }

    public function finalPostData(ImportField $importField): array
    {
        $fieldSettings = $importField->fieldImportConfig['bloqs'] ?? [];
        $unique = $importField->fieldImportConfig['unique'] ?? '';
        $advanced = $importField->fieldImportConfig['advanced'] ?? '';
        $bloqs = $this->getBloqs($importField->fieldName);
        $atomTypes = $this->getAtomTypes($importField->fieldName);
        $data = [];

        $importItem = $importField->importItem;
        $indexedFields = [];

        // @todo if importing nested fields is ever going to be an option
        if ($advanced) {
            $prepped = array_filter($importItem, function ($v, $k) use ($advanced) {
                return str_starts_with($k, $advanced) && !preg_match('/\[includes \d+ child/', $v);
            }, ARRAY_FILTER_USE_BOTH);

            //$expanded = $importField->importer->dataType->expand($prepped);
            //$tree = $this->bloqArrayToTree($expanded);
        }

        if (
            $importField->entryId &&
            $unique === '-1'
        ) {
            $this->deleteExistingBloqs($importField->entryId, $importField->fieldId);
        }

        $hasValues = false;

        foreach ($fieldSettings as $fields) {
            if (count(array_filter(array_column($fields['atoms'], 'value')))) {
                $hasValues = true;
                break;
            }
        }

        // Avoid unnecessary iterations if no fields were configured for import
        // @todo - reduce $fieldSettings to only those with values to further reduce iterations
        // or reduce $importItem paths to the values found in the settings?
        if (!$hasValues) {
            return [];
        }

        $subItemsKeys = [];

        foreach ($importItem as $path => $fieldValue) {
            foreach ($fieldSettings as $bloqId => $atoms) {
                foreach ($atoms['atoms'] as $atomId => $settings) {
                    $fieldType = $atomTypes[$atomId]['type'] ?? '';
                    $handler = $importField->importer->getLoader()->loadFieldTypeHandler($fieldType);

                    if ($handler && method_exists($handler, 'validatePath')) {
                        $validPath = $handler->validatePath($path, $settings);
                    } else {
                        $validPath = $this->validatePath($path, $settings['value'] ?? '');
                    }

                    if (!$validPath) {
                        continue;
                    }

                    if ($handler && method_exists($handler, 'preparePostData')) {
                        $importSubItem = null;

                        // For things like Grid fields, we need to extract the subset of data from the main importItem
                        // or else it will fetch all matching paths and insert them all into each instance of the same
                        // grid field appearing in the import. This is the only way to import multiple Grid fields
                        // into a Fluid field. Don't process the same key multiple times, otherwise in the case of Grid
                        // fields, it will create multiple instances of the same grid field, one for each row in the field.
                        $subItemsKey = $settings['value'] ?? '';

                        if ($subItemsKey && in_array($subItemsKey, $subItemsKeys)) {
                            continue;
                        }

                        if ($handler::HAS_SUB_ITEMS) {
                            if ($subItemsKey) {
                                $subItemsKeys[] = $subItemsKey;
                            }

                            $importSubItem = $importField->importer->dataType->extract_sub_items($importItem, $path);

                            if (empty($importSubItem)) {
                                continue;
                            }
                        }

                        $value = $handler->preparePostData(new ImportField(
                            importer: $importField->importer,
                            importItem: $importSubItem ?? $importItem,
                            propertyName: $validPath,
                            propertyValue: $fieldValue,
                            fieldImportConfig: $settings,
                            fieldSettings: $this->getAtomDefinitionSettings($atomId),
                            entryId: $importField->entryId,
                            fieldName: $importField->fieldName,
                            fieldId: $atomId ?? $importField->fieldId,
                            contentType: 'bloqs',
                        ));

                        $indexedFields[] = [
                            'bloqId' => $bloqId,
                            'atomId' => $atomId,
                            'value' => $value,
                            'settings' => $settings,
                            'path' => $path,
                            'type' => $atomTypes[$atomId]['type'] ?? '',
                        ];
                    } else {
                        $indexedFields[] = [
                            'bloqId' => $bloqId,
                            'atomId' => $atomId,
                            'value' => $fieldValue,
                            'settings' => $settings,
                            'path' => $path,
                            'type' => $atomTypes[$atomId]['type'] ?? '',
                        ];
                    }
                }
            }
        }

        $rowNum = 1;

        foreach ($indexedFields as $index => $field) {
            $bloqId = $field['bloqId'];
            $atomId = $field['atomId'];
            $value = $field['value'];
            $atomIds = array_keys($fieldSettings[$bloqId]['atoms'] ?? []);
            $bloqName = 'blocks_new_block_' . $rowNum;

            if (!array_key_exists($bloqName, $data)) {
                $data[$bloqName] = [
                    'deleted' => 'false',
                    'id' => $bloqName,
                    'order' => $rowNum,
                    'blockDefinitionId' => $bloqId,
                    'componentDefinitionId' => 0,
                    'cloneable' => '0',
                    'draft' => '0',
                    'values' => [],
                ];
            }

            $data[$bloqName]['values']['col_id_' . $atomId] = $value;

            // Group #0 means they are basically ungrouped and only 1 field can exist in it.
            // So start a new field row. Otherwise, look ahead to see if it's going to change groups.
            $nextBloq = $indexedFields[$index+1] ?? [];

            if (
                count($atomIds) === 1
                || $bloqId !== $nextBloq['bloqId']
                || $nextBloq['atomId'] === $atomId // can't have 2 fields of the same ID in a bloq
                || !in_array($nextBloq['atomId'], $atomIds)
                || count($data[$bloqName]['values']) === count($bloqs[$bloqId]['atoms'])
            ) {
                $rowNum++;
            }
        }

        $data['tree_order'] = [];

        // Reset Bloqs' internal cache, otherwise the validate() function
        // will keep returning the first entry that is imported.
        ee()->session->cache['Bloqs_ft'] = [];

        return $data;
    }

    private function getAtomTypes(string $fieldName): array
    {
        $bloqs = $this->getBloqs($fieldName);
        $collection = [];

        foreach ($bloqs as $bloqId => $bloq) {
            foreach ($bloq['atoms'] as $atom) {
                $collection[$atom['id']] = $atom;
            }
        }

        return $collection;
    }

    /**
     * @param string $fieldName
     * @return array
     */
    private function getBloqs(string $fieldName): array
    {
        $field = ee('Model')->get('ChannelField')->filter('field_name', $fieldName)->first();
        $bloqDefinitions = ee('bloqs:Adapter')->getBlockDefinitionsForField($field->field_id);
        $bloqs = [];

        /** @var \BoldMinded\Bloqs\Entity\BlockDefinition $bloqDefinition */
        foreach ($bloqDefinitions as $bloqDefinition) {
            $atomDefinitions = $bloqDefinition->getAtomDefinitions();
            $atoms = [];

            foreach ($atomDefinitions as $atomDefinition) {
                $atoms[] = [
                    'label' => $atomDefinition->getName(),
                    'id' => $atomDefinition->getId(),
                    'name' => $atomDefinition->getShortName(),
                    'type' => $atomDefinition->getType(),
                ];
            }

            $bloqs[$bloqDefinition->getId()] = [
                'bloqName' => $bloqDefinition->getName(),
                'atoms' => $atoms,
            ];
        }

        return $bloqs;
    }

    private function deleteExistingBloqs(int $entryId, int $fieldId)
    {
        /** @var CI_DB_result $blocksQuery */
        $blocksQuery = ee()->db
            ->where('entry_id', $entryId)
            ->where('field_id', $fieldId)
            ->get('blocks_block');

        $blocks = array_column($blocksQuery->result_array(), 'id');

        if (!empty($blocks)) {
            ee()->db
                ->where_in('block_id', $blocks)
                ->delete('blocks_atom');

            ee()->db
                ->where_in('id', $blocks)
                ->delete('blocks_block');
        }
    }

    private function getAtomDefinitionSettings(int $atomId): array
    {
        $atomDefinition = ee('db')->where('id', $atomId)->get('blocks_atomdefinition')->row();
        return json_decode($atomDefinition->settings ?? '', true);
    }
}
