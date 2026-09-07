<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;
use BoldMinded\DataGrab\Traits\FileUploadDestinations;
use BoldMinded\SimpleGrids\Dependency\Symfony\Component\Yaml\Yaml;
use BoldMinded\SimpleGrids\FieldTypes\FieldFactory;

/**
 * DataGrab Simple Grid fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_simple_grid extends AbstractFieldType
{
    use FileUploadDestinations;

    public const HAS_SUB_ITEMS = true;

    protected string $docUrl = 'https://docs.boldminded.com/datagrab/docs/field-types/simple-grids-and-tables';

    private $supportedFieldTypes = [
        'date',
        'file',
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
                'columns',
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
    ): array
    {
        $config = [];
        $config['label'] = $this->displayLabel($fieldLabel, $fieldName, $fieldRequired, 'simple_grids');
        $fieldSettings = $this->getSavedFieldValues($data, $fieldName);

        $fieldSets = ee('View')
            ->make('ee:_shared/form/section')
            ->render([
                'name' => 'fieldset_group',
                'settings' => $this->getFormFields(
                    $fieldName,
                    $fieldSettings,
                    $data,
                    $fieldSettings ?? [],
                    'grid',
                    $importer
                ),
            ]);

        $config['value'] = $fieldSets;

        return $config;
    }

    public function getFormFields(
        string $fieldName,
        array $fieldSettings,
        array $data = [],
        array $savedFieldValues = [],
        string $contentType = 'grid',
        Importer|null $importer = null
    ): array {
        $fieldOptions = [];
        $gridColumns = $this->getGridColumns($fieldName, $fieldSettings);

        if (in_array($contentType, ['fluid_field', 'bloqs'])) {
            $fieldOptions[] = [
                'title' => 'Parent Node',
                'desc' => 'Select the parent node that contains all the children for this field. It will be labeled with <b>[includes N children]</b>.',
                'fields' => [
                    $fieldName . '[value]' => [
                        'type' => 'dropdown',
                        'choices' => $data['data_fields'],
                        'value' => $savedFieldValues['value'] ?? '',
                        'required' => true,
                    ],
                ]
            ];
        }

        foreach ($gridColumns as $colId => $row) {
            $colType = $row['col_type'];

            if (in_array($colType, $this->supportedFieldTypes)) {
                $handler = $importer->getLoader()->loadFieldTypeHandler($colType);
                $handler->fieldPrefix = $fieldName . '[columns]';
                $parsedSettings = Yaml::parse($row['col_settings'] ?? '') ?? [];

                $subFormFields = $handler->getFormFields(
                    $handler->createFieldName($colId),
                    $parsedSettings,
                    $data,
                    $savedFieldValues['columns'][$colId] ?? [],
                    'grid'
                );

                $fieldOptions[] = ee('View')
                    ->make('ee:_shared/form/section')
                    ->render([
                        'name' => $row['col_label'],
                        'settings' => $subFormFields,
                    ]);
            } else {
                $fieldOptions[] = ee('View')
                    ->make('ee:_shared/form/section')
                    ->render([
                        'name' => $row['col_label'],
                        'settings' => [
                            [
                                'title' => 'Import Value',
                                'desc' => '',
                                'fields' => [
                                    $fieldName . '[columns][' . $colId . '][value]' => [
                                        'type' => 'dropdown',
                                        'choices' => $data['data_fields'],
                                        'value' => $savedFieldValues['columns'][$colId]['value'] ?? '',
                                    ]
                                ]
                            ]
                        ]
                    ]);
            }
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
                                    '0' => 'Keep existing rows and append new',
                                    '-1' => 'Delete all existing rows',
                                ],
                                'value' => $savedFieldValues['unique'] ?? '-1',
                            ]
                        ]
                    ]
                ]
            ]);

        return $fieldOptions;
    }

    public function save_configuration(
        Importer $importer,
        string   $fieldName = '',
        array    $customFieldSettings = []
    ) {
        // If no columns are defined for accepting import data, don't remove any existing data on the entry
        // when performing an import and updating existing entries, and don't remove any rows from Grid fields
        // that we don't want to import data into.
        $columns  = $customFieldSettings['columns'] ?? [];
        $values = array_column($columns, 'value');

        if (empty($values)) {
            return [];
        }

        return $customFieldSettings;
    }

    public function hasFieldValue(array $settings = []): string
    {
        foreach ($settings['columns'] ?? [] as $column) {
            if (($column['value'] ?? '') !== '') {
                return $column['value'];
            }
        }

        return '';
    }

    public function preparePostData(ImportField $importField): array {
        return $this->finalPostData($importField);
    }

    public function finalPostData(ImportField $importField): array
    {
        $gridColumns = $this->getGridColumns($importField->fieldId, $importField->fieldSettings);

        // $fields contains a list of grid columns mapped to data elements
        // eg, $fields[3] => 5 means map data element 5 to grid column 3
        $fields = $importField->fieldImportConfig['columns'] ?? [];
        $grid = [];

        // Loop over columns
        foreach ($gridColumns as $colId => $column) {
            $colType = $column['col_type'];
            // Loop over data items
            if (
                isset($fields[$colId]) &&
                $importField->importer->dataType->initialise_sub_item()
            ) {
                $subItem = $importField->importer->dataType->get_sub_item(
                    $importField->importItem,
                    $fields[$colId]['value'],
                    $importField->importer->settings,
                    $importField->fieldName,
                    $column
                );
                $rowNum = 1;
                $rowId = 'new_row_' . $rowNum;

                while ($subItem !== false) {
                    if (!isset($grid[$rowId])) {
                        $grid[$rowId] = [];
                    }

                    $handler = $importField->importer->getLoader()->loadFieldTypeHandler($colType);

                    // This is a little odd in that we create the handler from the DataGrab supported fieldtypes, but
                    // also create an instance of the Simple Grid field, but this is the easiest way to get the settings.
                    // Also weird that we still need to use the handler. I initially thought I could just instantiate
                    // all the Simple Grid fieldtypes and call save(), but that does not work with the File field b/c
                    // The Simple Grid fields don't do complicated things like handle uploads. That's done through the
                    // field's display_field method.
                    $factory = FieldFactory::create($colType, $gridColumns[$colId]);

                    if (
                        in_array($colType, $this->supportedFieldTypes)
                        && method_exists($handler, 'preparePostData')
                    ) {
                        $handler->fieldPrefix = $importField->fieldName . '[columns]';
                        $fieldImportConfig = $fields[$colId] ?? [];

                        // nomenclature seems odd, but we're preparing to save in a Grid field
                        $grid[$rowId]['col_id_' . $colId] = $handler->preparePostData(new ImportField(
                            importer: $importField->importer,
                            importItem: $importField->importItem,
                            propertyName: $fields[$colId]['value'],
                            propertyValue: $subItem,
                            fieldImportConfig: $fieldImportConfig,
                            fieldSettings: $factory->getAllOptions(),
                            entryId: $importField->entryId,
                            fieldName: $importField->fieldName,
                            fieldId: $importField->fieldId,
                            contentType: 'grid',
                        ));
                    } else {
                        $grid[$rowId]['col_id_' . $colId] = $subItem;
                    }

                    $subItem = $importField->importer->dataType->get_sub_item(
                        $importField->importItem,
                        $fields[$colId]['value'],
                        $importField->importer->settings,
                        $importField->fieldName,
                        $column,
                    );

                    $rowNum++;
                    $rowId = 'new_row_' . $rowNum;
                }
            }
        }

        // Remove empty rows
        $newGrid = [];
        foreach ($grid as $idx => $row) {
            $empty = true;
            foreach ($row as $col) {
                if ($col != '') {
                    $empty = false;
                    continue;
                }
            }
            if (!$empty) {
                $newGrid[$idx] = $row;
            }
        }
        $grid = $newGrid;

        if ($importField->entryId) {
            // Find out what to do with existing data (delete or keep?)
            $unique = 0;
            if (isset($importField->fieldImportConfig['unique'])) {
                $unique = $importField->fieldImportConfig['unique'];
            }

            // Is this the first time this entry has been updated during this import?
            if (!in_array($importField->entryId, $importField->importer->entries)) {
                // This is the first import, so delete existing rows if required
                if ($unique == -1) {
                    // Delete existing rows
                    //$importer->logger->log('Remove existing rows from the Grid field, if any exist.');
                    $old = [];
                } else {
                    // Keep existing rows
                    // Fetch existing data
                    //$importer->logger->log('Keep existing rows from the Grid field.');
                    $old = $this->_rebuild_grid_data($importField->entryId, $importField->importer, $importField->fieldId);
                }
            } else {
                // Fetch existing data
                $old = $this->_rebuild_grid_data($importField->entryId, $importField->importer, $importField->fieldId);
            }

            // "Action to take when an entry is updated" - If $unique is set to a positive int value, then it's a
            // col_id from the config array to only update the row if the new column value does not match
            // the existing, column value.
            if ($unique > 0) {
                $indexedGrid = array_values($grid);
                $indexedOld = array_values($old);
                foreach ($indexedGrid as $index => $rowData) {
                    $currentRow = $indexedOld[$index];
                    if (
                        isset($currentRow['col_id_' . $unique]) &&
                        $currentRow['col_id_' . $unique] !== $rowData['col_id_' . $unique]
                    ) {
                        $importer->logger->log(sprintf(
                            '"%s" does not match "%s", appending Grid row.',
                            $currentRow['col_id_' . $unique],
                            $rowData['col_id_' . $unique]
                        ));
                        $grid = array_merge($old, $grid);
                    }
                }
            } elseif (!empty($old)) {
                $importField->importer->logger->log('Appending new row(s) to Grid');
                $grid = array_merge($old, $grid);
            }
        }

        return ['rows' => $grid];
    }

    private function _rebuild_grid_data($entry_id, $importer, $field_id)
    {
        $where = [
            'entry_id' => $entry_id,
        ];

        // -------------------------------------------
        //  'datagrab_rebuild_simple_grid_query' hook
        //
        if ($importer->extensions->active_hook('datagrab_rebuild_simple_grid_query')) {
            $importer->logger->log('Calling datagrab_rebuild_simple_grid_query() hook.');
            $rows = $importer->extensions->call('datagrab_rebuild_simple_grid_query', $where, $field_id);
        } else {
            $entry = ee('Model')->get('ChannelEntry', $entry_id)->first();

            if ($entry->{'field_id_' . $field_id}) {
                $rows = json_decode($entry->{'field_id_' . $field_id}, true);
            }
        }

        return $rows;
    }

    private function getGridColumns(string|int $fieldIdentifier, array $fieldSettings = []): array
    {
        $colTypes = array_column($fieldSettings['columns'] ?? [], 'col_type');

        // it's a nested field, in fluid, grid, or bloqs
        if (!empty(array_filter($colTypes))) {
            return $fieldSettings['columns'];
        }

        $channelField = ee('Model')->get('ChannelField');

        if (is_numeric($fieldIdentifier)) {
            $channelField->filter('field_id', $fieldIdentifier);
        } else {
            $channelField->filter('field_name', $fieldIdentifier);
        }

        $field = $channelField->first();

        return $field?->field_settings['columns'] ?? [];
    }

    /**
     * Grid fields require the parent path to be set in the configuration and we validate just
     * the parent, not each column. It's up to the parent field, e.g. Fluid, Grid, or Bloqs, to
     * extract and handle the Grid sub data.
     */
    public function validatePath(string $path, array $settings): string|bool
    {
        $columns = $settings['columns'] ?? [];
        $value = $settings['value'] ?? '';

        if (empty($columns) || !$value) {
            return false;
        }

        $keyPatternMatch = preg_replace('/(?<=\/){n}(?=\/)/', '\d+', $value ?? '');
        $preparedPattern = preg_replace('~(?<!\\\)/~', '\/', $keyPatternMatch);
        preg_match('/'. $preparedPattern .'$/', $path, $matches);

        if (!empty(array_filter($matches))) {
            return $matches[0];
        }

        return false;
    }
}
