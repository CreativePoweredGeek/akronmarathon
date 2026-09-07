<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;

/**
 * DataGrab cartthrob_price_quantity_thresholds fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_cartthrob_price_quantity_thresholds extends AbstractFieldType
{
    public function register_setting(string $fieldName)
    {
        return [
            $fieldName => [
                'low',
                'high',
            ]
        ];
    }

    public function display_configuration(
        Importer $importer,
        string $fieldName,
        string $fieldLabel,
        string $fieldType,
        bool $fieldRequired = false,
        array $data = []
    ): array
    {
        $config = [];
        $config['label'] = $this->displayLabel($fieldLabel, $fieldName, $fieldRequired, 'cartthrob');
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
    ): array
    {
        $fieldOptions[] = [
            'title' => 'Price',
            'desc' => '',
            'fields' => [
                $fieldName . '[price][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['price']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Low',
            'desc' => '',
            'fields' => [
                $fieldName . '[low][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['low']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'High',
            'desc' => '',
            'fields' => [
                $fieldName . '[high][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['high']['value'] ?? '',
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function hasFieldValue(array $settings = []): string
    {
        $values = array_filter(array_column($settings, 'value'));

        if (!empty($values)) {
            // Doesn't really matter which, so get the first
            return $settings[current($values)]['value'];
        }

        return '';
    }

    /*
        [field_id_72] => Array
        (
            [0] => Array
                (
                    [from_quantity] => 1
                    [up_to_quantity] => 3
                    [price] => 12
                )

            [1] => Array
                (
                    [from_quantity] => 4
                    [up_to_quantity] => 10
                    [price] => 10
                )

            [2] => Array
                (
                    [from_quantity] => 11
                    [up_to_quantity] => 100
                    [price] => 9
                )
        )
   */
    public function finalPostData(
        ImportField $importField
    ): array
    {
        // Is this an update?
        if ($importField->entryId) {
            // If so, is this the first update of this import?
            if (in_array($importField->entryId, $importField->importer->entries)) {
                $existing_data = [
                    'entry_id' => $importField->entryId
                ];

                $data = $this->rebuildPostData(
                    $importField->importer,
                    $importField->fieldId,
                    $importField->importer->entryData,
                    $existing_data
                );

                $first_row = count($data);
            } else {
                // Initialise data
                $data['field_id_' . $importField->fieldId] = [];
                $first_row = 0;
            }
        } else {
            // Initialise data
            $data['field_id_' . $importField->fieldId] = [];
            $first_row = 0;
        }

        // Can the current datatype handle sub-loops (eg, XML)?
        if ($importField->importer->dataType->initialise_sub_item()) {
            // Check this field can be a sub-loop
            $count = $first_row;

            // Loop over sub items
            while ($subitem = $importField->importer->dataType->get_sub_item(
                $importField->importItem,
                $importField->propertyName,
                $importField->importer->settings,
                $importField->fieldName,
            )) {
                $row = [
                    'price' => $subitem
                ];
                $data['field_id_' . $importField->fieldId][$count++] = $row;
            }

            //$count = $first_row;
            //
            //if ($importField->importer->dataType->initialise_sub_item()) {
            //    while ($subitem = $importField->importer->dataType->get_sub_item(
            //        $item,
            //        $importField->importer->settings["cf"][$fieldName . "_cartthrob_low"],
            //        $importField->importer->settings,
            //        $fieldName
            //    )) {
            //        $data["field_id_" . $fieldId][$count++]["from_quantity"] = $subitem;
            //    }
            //}
            //
            //$count = $first_row;
            //
            //if ($importField->importer->dataType->initialise_sub_item()) {
            //    while ($subitem = $importer->dataType->get_sub_item(
            //        $item, $importer->settings["cf"][$fieldName . "_cartthrob_high"], $importer->settings, $fieldName)) {
            //        $data["field_id_" . $fieldId][$count++]["up_to_quantity"] = $subitem;
            //    }
            //}
        }

        return $data;
    }

    public function rebuildPostData(
        Importer $importer,
        int      $fieldId = 0,
        array    $existingData = [],
        array    $entryData = []
    ) {
        ee()->db->select("field_id_" . $fieldId);
        ee()->db->where("entry_id", $entryData["entry_id"]);
        $query = ee()->db->get("exp_channel_data");
        if ($query->num_rows() > 0) {
            $row = $query->row_array();
            $existingData["field_id_" . $fieldId] = unserialize(base64_decode($row["field_id_" . $fieldId]));
        }

        return $existingData;
    }
}
