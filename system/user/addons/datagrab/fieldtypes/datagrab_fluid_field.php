<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;

/**
 * DataGrab Fluid Field fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_fluid_field extends AbstractFieldType
{
    protected string $docUrl = 'https://docs.boldminded.com/datagrab/docs/field-types/fluid';

    private $supportedFieldTypes = [
        'ansel',
        'date',
        'file',
        'grid',
        'relationship',
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
                'groups',
                'fields',
                'unique',
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
        $config['label'] = $this->displayLabel($fieldLabel, $fieldName, $fieldRequired, 'fluid_field');

        // Get current saved settings
        $default = $this->getSavedFieldValues($data, $fieldName);
        $savedGroups = $default['groups'] ?? [];

        $fluidFields = $this->getFluidFields($fieldName);
        $fieldOptions = [];

        foreach ($fluidFields as $groupId => $fields) {
            if ($groupId > 0) {
                $groupHtml = ee('View')
                    ->make('ee:_shared/form/section')
                    ->render([
                        'name' => 'fieldset_group',
                        'settings' => $this->getFieldOptions(
                            $importer,
                            sprintf('%s[groups][%s]', $fieldName, $groupId),
                            $fields['fields'],
                            $data,
                            $savedGroups[$groupId]['fields'] ?? [],
                        )
                    ]);

                $fieldOptions[] = ee('View')
                    ->make('datagrab:panel')
                    ->render([
                        'heading' => $fields['groupName'],
                        'html' => $groupHtml
                    ]);
            } else {
                $fieldOptions = array_merge($fieldOptions, $this->getFieldOptions(
                    $importer,
                    sprintf('%s[groups][%s]', $fieldName, $groupId),
                    $fields,
                    $data,
                    $savedGroups[$groupId]['fields'] ?? [],
                ));
            }
        }

        $config['value'] = ee('View')
            ->make('ee:_shared/form/section')
            ->render([
                'name' => 'fieldset_group',
                'settings' => $fieldOptions
            ]);

        return $config;
    }

    public function save_configuration(
        Importer $importer,
        string   $fieldName = '',
        array    $customFieldSettings = []
    ) {
        foreach ($customFieldSettings['groups'] as &$groupFields) {
            foreach ($groupFields['fields'] as &$fieldSettings) {
                if (isset($fieldSettings['fieldType'])) {
                    $handler = $importer->getLoader()->loadFieldTypeHandler($fieldSettings['fieldType']);

                    if ($handler && method_exists($handler, 'save_configuration')) {
                        $fieldSettings = $handler->save_configuration($importer, $fieldName, $fieldSettings);
                    }
                }
            }
        }

        return $customFieldSettings;
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
                $handler->fieldPrefix = $fieldName . '[fields]';
                $savedFieldSettings = $savedFieldValues[$field['id']] ?? [];

                $subFormFields = $handler->getFormFields(
                    $handler->createFieldName($field['id']),
                    $data['field_settings'][$field['name']] ?? [],
                    $data,
                    $savedFieldSettings,
                    'fluid_field',
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
                                    $fieldName . '[fields][' . $field['id'] . '][value]' => [
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
     * $_POST['field_id_X'] = [
            'fields' => [
                'new_field_1' => [
                    'field_group_id_0' => [
                        'field_id_1' => 'description 1',
                    ],
                ],
                'new_field_2' => [
                    'field_group_id_1' => [
                        'field_id_1' => 'desc group 1',
                        'field_id_4' => 'heading group 1',
                    ],
                ],
                'new_field_3' => [
                    'field_group_id_0' => [
                        'field_id_1' => 'description 2',
                    ],
                ],
                'new_field_4' => [
                    'field_group_id_2' => [
                        'field_id_6' => '',
                        'field_id_8' => '20',
                    ],
                ],
                'new_field_5' => [
                    'field_group_id_1' => [
                        'field_id_1' => 'A',
                        'field_id_4' => 'B',
                    ],
                ],
            ],
        ];
     */

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

    private function findUniqueFieldSettingValues(array $fieldSettings): array
    {
        $results = [];

        foreach ($fieldSettings as $key => $value) {
            if (is_array($value)) {
                $results = array_merge($results, $this->findUniqueFieldSettingValues($value));
            } elseif ($key === 'value' && $value !== '') {
                $results[] = $value;
            }
        }

        return $results;
    }

    public function hasFieldValue(array $settings = []): string
    {
        $values = [];

        foreach ($settings['groups'] ?? [] as $group) {
            foreach ($group['fields'] ?? [] as $field) {
                if (($field['value'] ?? '') !== '') {
                    $values[] = $field['value'];
                }
            }
        }

        return implode(',', $values);
    }

    public function finalPostData(ImportField $importField): array
    {
        $fieldSettings = $importField->fieldImportConfig['groups'] ?? [];
        $fieldTypes = $this->getFluidFieldTypes($importField->fieldName);
        $data = [];

        $importItem = $importField->importItem;
        $indexedFields = [];

        // If there are no mapped fields to import reduce the array size, thus reducing the iterations below.
        // If we have no settings at all, return early as there is nothing configured for this field.
        $fieldSettingsReduced = array_filter($fieldSettings, function ($value, $key) {
            return !empty(array_filter(array_column($value['fields'], 'value')));
        }, ARRAY_FILTER_USE_BOTH);

        // Avoid unnecessary iterations if no fields were configured for import. If the import file is large,
        // e.g. a WordPress file with lots of meta fields, $importItem is going to have a LOT of paths, so
        // this is going to perform a lot of iterations.
        if (empty($fieldSettingsReduced)) {
            return ['fields' => $data];
        }

        $uniqueFieldSettingValues = $this->findUniqueFieldSettingValues($fieldSettings);

        // Make the importItem list as small as possible to reduce excessive iterations. In testing this reduced
        // it from about 1300 extra iterations an entry to just 15. That's just 1 use case, but shows how effective this is.
        $importItem = array_filter($importItem, function ($key) use ($uniqueFieldSettingValues, $importField) {
            $newKey = $importField->importer->dataType->item_key_placeholders($key);
            return in_array($newKey, $uniqueFieldSettingValues);
        }, ARRAY_FILTER_USE_KEY);

        $subItemsKeys = [];

        foreach ($importItem as $path => $fieldValue) {
            foreach ($fieldSettingsReduced as $groupId => $fields) {
                foreach ($fields['fields'] as $fieldId => $settings) {
                    $fieldType = $fieldTypes[$fieldId]['type'] ?? '';
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
                        if ($handler::HAS_SUB_ITEMS) {
                            $importSubItem = $importField->importer->dataType->extract_sub_items($importItem, $path);
                            $importSubItemUid = md5(serialize(array_keys($importSubItem)));

                            if (
                                empty($importSubItem) ||
                                in_array($importSubItemUid, $subItemsKeys)
                            ) {
                                continue;
                            }

                            $subItemsKeys[] = $importSubItemUid;
                        }

                        $value = $handler->preparePostData(new ImportField(
                            importer: $importField->importer,
                            importItem: $importSubItem ?? $importItem,
                            propertyName: $validPath,
                            propertyValue: $fieldValue,
                            fieldImportConfig: $settings,
                            fieldSettings: $importField->importer->getCustomFieldSettings($importField->fieldName),
                            entryId: $importField->entryId,
                            fieldName: $importField->fieldName,
                            fieldId: $fieldId ?? $importField->fieldId,
                            contentType: 'fluid',
                        ));

                        $indexedFields[] = [
                            'groupId' => $groupId,
                            'fieldId' => $fieldId,
                            'value' => $value,
                            'settings' => $settings,
                            'type' => $fieldType,
                        ];
                    } else {
                        $indexedFields[] = [
                            'groupId' => $groupId,
                            'fieldId' => $fieldId,
                            'value' => $fieldValue,
                            'settings' => $settings,
                            'type' => $fieldType,
                        ];
                    }
                }
            }
        }

        $rowNum = 1;

        foreach ($indexedFields as $index => $field) {
            $groupId = $field['groupId'];
            $fieldId = $field['fieldId'];
            $value = $field['value'];
            $fieldIds = array_keys($fieldSettings[$groupId]['fields'] ?? []);

            $data['new_field_' . $rowNum]['field_group_id_' . $groupId]['field_id_' . $fieldId] = $value;

            // Group #0 means they are basically ungrouped and only 1 field can exist in it.
            // So start a new field row. Otherwise, look ahead to see if it's going to change groups.
            $nextField = $indexedFields[$index+1] ?? [];

            if (
                $groupId === 0
                || count($fieldIds) === 1
                || $groupId !== $nextField['groupId']
                || $nextField['fieldId'] === $fieldId // can't have 2 fields of the same ID in a group
                || !in_array($nextField['fieldId'], $fieldIds)
            ) {
                $rowNum++;
            }
        }

        return ['fields' => $data];
    }

    private function findParentPath(string $path): string {
        $preparedPath = preg_replace('/\/(\d+)\//', '.$1.', $path);
        $preparedPath = str_replace('/__parent__', '', $preparedPath);

        if (preg_match('/^(.*)\.(\d+)(\D.*|$)/', $preparedPath, $matches)) {
            return $matches[1] . '.' . ((int) $matches[2] + 1);
        }

        return $path;
    }

    private function getFluidFieldTypes(string $fieldName): array
    {
        $fields = $this->getFluidFields($fieldName);
        $collection = [];

        foreach ($fields as $groupId => $field) {
            if ($groupId === 0) {
                foreach ($field as $ungroupedField) {
                    $collection[$ungroupedField['id']] = $ungroupedField;
                }
            } else {
                foreach ($field['fields'] as $groupedField) {
                    $collection[$groupedField['id']] = $groupedField;
                }
            }
        }

        return $collection;
    }

    /**
     * @param string $fieldName
     * @return array
     */
    private function getFluidFields(string $fieldName): array
    {
        $field = ee('Model')->get('ChannelField')->filter('field_name', $fieldName)->first();
        $fieldOptions = array_filter($field->field_settings['field_channel_fields'] ?? []);
        $fieldGroupOptions = array_filter($field->field_settings['field_channel_field_groups'] ?? []);
        $groupFields = [];

        $fields = ee('Model')->get('ChannelField')
            ->filter('site_id', 'IN', [ee()->config->item('site_id'), 0])
            ->filter('field_id', 'IN', $fieldOptions)
            ->order('field_label')
            ->all()
            ->filter(function ($field) {
                return $field->getField()->acceptsContentType('fluid_field');
            })
            ->map(function ($field) {
                return [
                    'label' => $field->field_label,
                    'id' => $field->getId(),
                    'name' => $field->field_name,
                    'type' => $field->field_type,
                ];
            });

        $groupFields[0] = $fields;

        if (count($fieldGroupOptions) > 0) {
            $fieldGroups = ee('Model')->get('ChannelFieldGroup')
                ->with('ChannelFields')
                ->filter('group_id', 'IN', $fieldGroupOptions)
                ->order('group_name', 'desc')
                ->all();

            foreach ($fieldGroups as $group) {
                $groupFields[$group->group_id] = [
                    'groupName' => $group->group_name,
                    'fields' => $group->ChannelFields->map(function ($field) {
                        return [
                            'label' => $field->field_label,
                            'id' => $field->getId(),
                            'name' => $field->field_name,
                            'type' => $field->field_type,
                        ];
                    }),
                ];
            }
        }

        return $groupFields;
    }
}
