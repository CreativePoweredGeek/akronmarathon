<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\App;
use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;
use BoldMinded\DataGrab\Traits\FileUploadDestinations;

/**
 * DataGrab Simple Table fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_simple_table extends AbstractFieldType
{
    use FileUploadDestinations;

    public const HAS_SUB_ITEMS = true;

    protected string $docUrl = 'https://docs.boldminded.com/datagrab/docs/field-types/simple-grids-and-tables';

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
            ],
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
                    'simple_table',
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
        string $contentType = 'channel',
        Importer|null $importer = null
    ): array {
        $fieldColumns = array_fill(1, $fieldSettings['max_columns'] ?? 10, 'Column %d Value');

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

        foreach ($fieldColumns as $colId => $label) {
            $fieldOptions[] = [
                'title' => sprintf($label, $colId),
                'desc' => '',
                'fields' => [
                    $fieldName . '[columns][' . $colId . '][value]' => [
                        'type' => 'dropdown',
                        'choices' => $data['data_fields'],
                        'value' => $savedFieldValues['columns'][$colId]['value'] ?? '',
                    ]
                ]
            ];
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

    public function save_configuration(Importer $importer, string $fieldName = '', array $customFieldSettings = [])
    {
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
        // $fields contains a list of grid columns mapped to data elements
        // eg, $fields[3] => 5 means map data element 5 to grid column 3
        $fields = $importField->fieldImportConfig['columns'] ?? [];
        $grid = [];

        // Loop over columns
        foreach ($fields as $colId => $column) {
            // Loop over data items
            if (
                isset($column['value']) &&
                $importField->importer->dataType->initialise_sub_item()
            ) {
                $subItem = $importField->importer->dataType->get_sub_item(
                    $importField->importItem,
                    $column['value'],
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

                    //if ($rowNum === 1 && $colId === 'heading_row' && $subItem) {
                    //    $grid[$rowId]['col_heading_row'] = $subItem;
                    //    $rowNum++;
                    //    $rowId = 'new_row_' . $rowNum;
                    //    continue;
                    //}

                    $grid[$rowId]['col_id_' . $colId] = $subItem;

                    //if ($key && array_key_exists($key.'@heading_row', $importField->importItem)) {
                    //    $grid[$rowId]['col_heading_row'] = $subItem;
                    //} else {
                    //    $grid[$rowId]['col_id_' . $colId] = $subItem;
                    //}

                    $subItem = $importField->importer->dataType->get_sub_item(
                        $importField->importItem,
                        $column['value'],
                        $importField->importer->settings,
                        $importField->fieldName,
                        $column
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
            if (isset($importField->importer->settings['cf'][$importField->fieldName]['unique'])) {
                $unique = $importField->importer->settings['cf'][$importField->fieldName]['unique'];
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
                        $importField->importer->logger->log(sprintf(
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
        //  'datagrab_rebuild_simple_table_query' hook
        //
        if ($importer->extensions->active_hook('datagrab_rebuild_simple_table_query')) {
            $importer->logger->log('Calling datagrab_rebuild_simple_table_query() hook.');
            $rows = $importer->extensions->call('datagrab_rebuild_simple_table_query', $where, $field_id);
        } else {
            $entry = ee('Model')->get('ChannelEntry', $entry_id)->first();

            if ($entry->{'field_id_' . $field_id}) {
                $rows = json_decode($entry->{'field_id_' . $field_id}, true);
            }
        }

        return $rows;
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
