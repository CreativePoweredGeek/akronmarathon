<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;

/**
 * DataGrab Grid fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_grid extends AbstractFieldType
{
    public const HAS_SUB_ITEMS = true;

    protected string $docUrl = 'https://docs.boldminded.com/datagrab/docs/field-types/grid';

    private array $supportedFieldTypes = [
        'ansel',
        'date',
        'file',
        'relationship',
        'simple_grid',
        'simple_table',
    ];

    /**
     * Register a setting so it can be saved
     */
    public function register_setting(string $fieldName): array
    {
        return [
            $fieldName => [
                'value',
                'columns',
                'unique',
                'fieldType',
            ]
        ];
    }

    private function getColumnsByFieldName(string $fieldName): array
    {
        // Find columns for this grid
        ee()->db->select('col_id, col_type, col_label, col_required, col_settings');
        ee()->db->from('exp_grid_columns g');
        ee()->db->join('exp_channel_fields c', 'g.field_id = c.field_id');
        ee()->db->where('c.field_name', $fieldName);
        ee()->db->order_by('col_order ASC');

        $query = ee()->db->get();

        return $query->result_array();
    }

    private function getColumnsByFieldId(int $fieldId): array
    {
        // Find columns for this grid
        ee()->db->select('col_id, col_type, col_label, col_required, col_settings');
        ee()->db->from('exp_grid_columns g');
        ee()->db->join('exp_channel_fields c', 'g.field_id = c.field_id');
        ee()->db->where('c.field_id', $fieldId);
        ee()->db->order_by('col_order ASC');

        $query = ee()->db->get();

        return $query->result_array();
    }

    public function display_configuration(Importer $importer, string $fieldName, string $fieldLabel, string $fieldType, bool $fieldRequired = false, array $data = []): array
    {
        $config = [];
        $config['label'] = $this->displayLabel($fieldLabel, $fieldName, $fieldRequired, 'grid');
        $fieldSettings = $this->getSavedFieldValues($data, $fieldName);

        $fieldSets = ee('View')
            ->make('ee:_shared/form/section')
            ->render([
                'name' => 'fieldset_group',
                'settings' => $this->getFormFields(
                    $fieldName,
                    $fieldSettings,
                    $data,
                    $fieldSettings,
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
        // Are we in a Fluid field?
        if (preg_match('/\[groups\]\[(\d+)\]\[fields\]\[(\d+)\]/', $fieldName, $matches)) {
            $fieldId = $matches[2] ?? 0;
            $gridColumns = $this->getColumnsByFieldId($fieldId);
        } else {
            $gridColumns = $this->getColumnsByFieldName($fieldName);
        }

        $savedColumnValues = $savedFieldValues['columns'] ?? [];
        $fieldOptions = [];
        $wrapOpen = '';
        $wrapClose = '';

        if (in_array($contentType, ['fluid_field'])) {
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

            $wrapOpen = '<div class="datagrab-nested-config-option">';
            $wrapClose = '</div>';
        }

        foreach ($gridColumns as $row) {
            $colType = $row['col_type'];
            $colId = $row['col_id'];

            if (in_array($colType, $this->supportedFieldTypes)) {
                $handler = $importer->getLoader()->loadFieldTypeHandler($colType);
                $handler->fieldPrefix = $fieldName . '[columns]';

                $subFormFields = $handler->getFormFields(
                    $handler->createFieldName($colId),
                    json_decode($row['col_settings'] ?? '', true),
                    $data,
                    $savedColumnValues[$colId] ?? [],
                    'grid'
                );

                $fieldOptions[] = $wrapOpen . ee('View')
                    ->make('ee:_shared/form/section')
                    ->render([
                        'name' => $row['col_label'],
                        'settings' => $subFormFields,
                    ]) . $wrapClose;
            } else {
                $fieldOptions[] = $wrapOpen . ee('View')
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
                                        'value' => $savedColumnValues[$colId]['value'] ?? '',
                                    ]
                                ]
                            ]
                        ]
                    ]) . $wrapClose;
            }
        }

        $column_options = [
            '0' => 'Keep existing rows and append new',
            '-1' => 'Delete all existing rows',
        ];
        $sub_options = [];
        foreach ($gridColumns as $row) {
            $sub_options[$row['col_id']] = $row['col_label'];
        }
        $column_options['Update the row if this column matches:'] = $sub_options;

        $fieldOptions[] = $wrapOpen .ee('View')
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
                                'choices' => $column_options,
                                'value' => $savedFieldValues['unique'] ?? '',
                            ],
                        ]
                    ]
                ]
            ]) . $wrapClose;

        $fieldOptions[] = [
            'fields' => [
                $fieldName . '[fieldType]' => [
                    'type' => 'hidden',
                    'value' => 'grid',
                ],
            ]
        ];

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
        $values = array_filter(array_column($columns, 'value'));

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
        // Find columns for this grid
        $query = ee()->db
            ->select('col_id, col_type, col_label, col_settings')
            ->from('exp_grid_columns g')
            ->where('field_id', $importField->fieldId)
            ->get();

        $gridColumns = $query->result_array();

        // $fields contains a list of grid columns mapped to data elements
        // eg, $fields[3] => 5 means map data element 5 to grid column 3
        $fields = $importField->fieldImportConfig['columns'] ?? [];
        $grid = [];

        // Loop over columns
        foreach ($gridColumns as $column) {
            $colId = $column['col_id'];
            $colType = $column['col_type'];
            $colSettings = json_decode($column['col_settings'] ?? '', true);
            // Loop over data items
            if (
                isset($fields[$colId]['value']) &&
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
                            fieldSettings: $colSettings,
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
                // @todo somewhere in here, if using Publisher, the rows are not imported correctly. Unsure why.
                // It seems to work fine if not using the matching/unique value option.
                $indexedGrid = array_values($grid);
                $indexedOld = array_values($old);
                foreach ($indexedGrid as $index => $rowData) {
                    $currentRow = $indexedOld[$index] ?? [];
                    if (
                        isset($currentRow['col_id_' . $unique]) &&
                        $currentRow['col_id_' . $unique] !== $rowData['col_id_' . $unique]

                        // @todo try to match on lang id and status here too, see if that makes a diff
                        // && $currentRow['publisher_lang_id'] === $rowData['publisher_lang_id']
                        // && $currentRow['publisher_status'] === $rowData['publisher_status']
                        // This doesn't work b/c rowData does not have publisher_ columns in them

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

        if (empty($grid)) {
            return [];
        }

        return $grid;
    }

    private function _rebuild_grid_data($entry_id, $importer, $field_id)
    {
        $where = [
            'entry_id' => $entry_id,
        ];

        // -------------------------------------------
        //  'datagrab_rebuild_grid_query' hook
        //
        if ($importer->extensions->active_hook('datagrab_rebuild_grid_query')) {
            $importer->logger->log('Calling datagrab_rebuild_grid_query() hook.');
            $query = $importer->extensions->call('datagrab_rebuild_grid_query', $where, $field_id);
        } else {
            ee()->db->select('*');
            ee()->db->from('exp_channel_grid_field_' . $field_id);
            ee()->db->where('entry_id', $entry_id);
            ee()->db->order_by('row_order ASC');
            $query = ee()->db->get();
        }
        //
        // -------------------------------------------

        $grid = [];
        foreach ($query->result_array() as $row) {
            $row_id = $row['row_id'];
            unset($row['row_id']);
            unset($row['entry_id']);
            unset($row['row_order']);

            $grid['row_id_' . $row_id] = $row;
        }

        return $grid;
    }

    /**
     * Grid fields require the parent path to be set in the configuration and we validate just
     * the parent, not each column. It's up to the parent field, e.g. Fluid in this case, to
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
