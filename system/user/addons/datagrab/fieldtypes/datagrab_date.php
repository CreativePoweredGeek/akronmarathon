<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;

/**
 * DataGrab Date fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_date extends AbstractFieldType
{
    public function register_setting(string $fieldName): array
    {
        return [
            $fieldName => [
                'value',
                'offset',
                'localized',
            ],
        ];
    }

    public function display_configuration(Importer $importer, string $fieldName, string $fieldLabel, string $fieldType, bool $fieldRequired = false, array $data = []): array
    {
        $config = [];
        $config['label'] = $this->displayLabel($fieldLabel, $fieldName, $fieldRequired, 'date');
        $fieldSettings = $data['field_settings'][$fieldName] ?? [];
        $savedFieldValues = $this->getSavedFieldValues($data, $fieldName);

        $fieldSets = ee('View')
            ->make('ee:_shared/form/section')
            ->render([
                'name' => 'fieldset_group',
                'settings' => $this->getFormFields(
                    $fieldName,
                    $fieldSettings,
                    $data,
                    $savedFieldValues,
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
            'title' => 'Time Offset',
            'desc' => 'Add an offset in seconds to the imported date value. For example, to add 1 hour, enter 3600. To subtract 1 hour, enter -3600.',
            'fields' => [
                $fieldName . '[offset]' => [
                    'type' => 'text',
                    'value' => $savedFieldValues['offset'] ?? '',
                ]
            ]
        ];

        // Grid Date columns don't offer or display the "Localized / Fixed" radio options.
        if ($contentType !== 'grid') {
            $fieldOptions[] = [
                'title' => 'Localized',
                'desc' => '',
                'fields' => [
                    $fieldName . '[localized]' => [
                        'type' => 'dropdown',
                        'choices' => ['No', 'Yes'],
                        'value' => $savedFieldValues['localized'] ?? 0,
                    ]
                ]
            ];
        }

        return $fieldOptions;
    }

    public function preparePostData(ImportField $importField): string
    {
        $data = '';
        $localize = $importField->fieldSettings['localize'] ?? false;
        $offset = $importField->fieldImportConfig['offset'] ?? 0;

        if ($importField->propertyValue !== '') {
            $data = $importField->importer->parseDate($importField->propertyValue);

            if ($localize !== true) {
                $timezone = ee()->config->item('default_site_timezone') ?: 'America/New_York';

                $dt = new DateTime('@' . $data); // force UTC
                $dt->setTimezone(new DateTimeZone($timezone));

                $data = $dt->getTimestamp();
            }

            if ($offset !== 0) {
                $data += (int) $offset;
            }
        }

        return $data;
    }
}
