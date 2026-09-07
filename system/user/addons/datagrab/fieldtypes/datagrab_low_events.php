<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;

/**
 * DataGrab Low Events fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_low_events extends AbstractFieldType
{
    protected string $docUrl = 'https://docs.boldminded.com/datagrab/docs/field-types/low-events';

    private static $preparedData = [];

    public function register_setting(string $fieldName): array
    {

        return [
            $fieldName => [
                'start_date',
                'start_time',
                'end_date',
                'end_time',
                'all_day',
            ]
        ];
    }

    public function display_configuration(Importer $importer, string $fieldName, string $fieldLabel, string $fieldType, bool $fieldRequired = false, array $data = []): array
    {
        $config = [];
        $config['label'] = $this->displayLabel($fieldLabel, $fieldName, $fieldRequired, 'calendar');
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
            'title' => 'Start Date',
            'desc' => '',
            'fields' => [
                $fieldName . '[start_date][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['start_date']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Start Time',
            'desc' => '',
            'fields' => [
                $fieldName . '[start_time][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['start_time']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'End Date',
            'desc' => '',
            'fields' => [
                $fieldName . '[end_date][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['end_date']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'End Time',
            'desc' => '',
            'fields' => [
                $fieldName . '[end_time][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['end_time']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'All Day',
            'desc' => 'Boolean value, e.g. 0/1, y/n, yes/no, true/false',
            'fields' => [
                $fieldName . '[all_day][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['all_day']['value'] ?? '',
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function hasFieldValue(array $settings = []): string
    {
        if (
            $settings['start_date']['value'] ?? false ||
            $settings['start_time']['value'] ?? false ||
            $settings['end_date']['value'] ?? false ||
            $settings['end_time']['value'] ?? false ||
            $settings['all_day']['value'] ?? false
        ) {
            return 'y'; // any value will do, doesn't matter what.
        }

        return '';
    }

    public function preparePostData(ImportField $importField)
    {
        $event = [];

        $config = $importField->fieldImportConfig;
        $startDate = $config['start_date']['value'] ?? '';
        $startTime = $config['start_time']['value'] ?? '';
        $endDate = $config['end_date']['value'] ?? '';
        $endTime = $config['end_time']['value'] ?? '';
        $allDay = $config['all_day']['value'] ?? '';

        if ($startDate) {
            $event['start_date'] = $this->formatDate($importField->importer->dataType->get_item($importField->importItem, $startDate));
        }
        if ($startTime) {
            $event['start_time'] = $this->formatDate($importField->importer->dataType->get_item($importField->importItem, $startTime), 'H:i');
        }
        if ($endDate) {
            $event['end_date'] = $this->formatDate($importField->importer->dataType->get_item($importField->importItem, $endDate));
        }
        if ($endTime) {
            $event['end_time'] = $this->formatDate($importField->importer->dataType->get_item($importField->importItem, $endTime), 'H:i');
        }
        // Only if we have date or times elsewhere do we want to add this
        if ($allDay && !empty($event)) {
            // Accept any bool value, but then force it to 'y' so Low Events validation is happy
            $isAllDay = get_bool_from_string($importField->importer->dataType->get_item($importField->importItem, $allDay));
            $event['all_day'] = $isAllDay ? 'y' : 'n';
        }

        // Send back null if no event data, then Low Events returns early when saving
        if (empty($event)) {
            $event = null;
        }

        self::$preparedData = $event;

        return $event;
    }

    public function finalPostData(ImportField $importField)
    {
        // Somewhere along the way this array is saved to exp_channel_data as a json object,
        // but for add-ons, such as Publisher, which store data in a separate table it also
        // needs the value as a json object, thus we encode it here sooner in the import process.
        // Low Events also decodes this in it's save() function.
        if (self::$preparedData === null) {
            return null;
        }

        return json_encode(self::$preparedData);
    }

    private function formatDate(string $dateString, string $responseFormat = 'Y-m-d'): string
    {
        $timestamp = strtotime(str_replace('-', '/', $dateString));

        if ($timestamp === false) {
            return '';
        }

        $date = (new DateTimeImmutable())->setTimestamp($timestamp);

        return $date->format($responseFormat);
    }
}
