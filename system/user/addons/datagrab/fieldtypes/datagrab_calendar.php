<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;

/**
 * DataGrab Calendar fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_calendar extends AbstractFieldType
{
    public function register_setting(string $fieldName): array
    {
        return [
            $fieldName => [
                'start_time',
                'end_time',
                'field',
            ]
        ];
    }

    public function hasFieldValue(array $settings = []): string
    {
        return $settings['start_time']['value'] ?? '';
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
    ): array {
        ee()->db->select('id as calendar_id, name as title');
        ee()->db->from('calendar_calendars');
        $query = ee()->db->get();

        $calendars = array_column($query->result_array(), 'title', 'calendar_id');

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
            'title' => 'Add to Calendar',
            'desc' => '',
            'fields' => [
                $fieldName . '[field][value]' => [
                    'type' => 'dropdown',
                    'choices' => $calendars,
                    'value' => $savedFieldValues['field']['value'] ?? '',
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

        $fieldOptions[] = [
            'title' => 'Repeats',
            'desc' => '',
            'fields' => [
                $fieldName . '[repeats][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['repeats']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Interval',
            'desc' => '',
            'fields' => [
                $fieldName . '[interval][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['interval']['value'] ?? '1',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Frequency',
            'desc' => 'Defaults to daily',
            'fields' => [
                $fieldName . '[frequency][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['frequency']['value'] ?? 'daily',
                ]
            ]
        ];

        $note = ee('CP/Alert')->makeInline()
            ->asTip()
            ->cannotClose()
            ->addToBody('Due to the complexity of the frequency selector, the values must be a JSON object. 
                    We have tried to illustrate possible values for the objects, but for further assistance on all the 
                    possible values, we suggest reaching out to the Solspace support team. These values mimic the POST data
                    when saving an event in an entry. If you are using the JSON import format, make sure to encode the values as a string,
                    e.g. <code>"{&bsol;"byday&bsol;": [&bsol;"SU&bsol;", &bsol;"TH&bsol;"]}"</code>')
            ->render();

        $fieldOptions[] = [
            'fields' => [
                'note' => [
                    'type' => 'html',
                    'content' => $note,
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Weekly (frequency)',
            'desc' => 'Value must be an encoded JSON object as a string, e.g. <code>{"byday": ["SU", "TH"]}</code>',
            'fields' => [
                $fieldName . '[weekly][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['weekly']['value'] ?? '',
                ],
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Monthly (frequency)',
            'desc' => 'Value must be an encoded JSON object as a string, e.g. <code>{"bymonthdayorbyday":"byday","bydayinterval":"3","byday":["WE","TH"]}</code>',
            'fields' => [
                $fieldName . '[monthly][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['monthly']['value'] ?? '',
                ],
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Yearly (frequency)',
            'desc' => 'Value must be an encoded JSON object as a string, e.g. <code>{"isbyday":"1","bydayinterval":"4","byday":["WE"],"bymonth":["8"]}</code>',
            'fields' => [
                $fieldName . '[yearly][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['monthly']['value'] ?? '',
                ],
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Until',
            'desc' => '',
            'fields' => [
                $fieldName . '[until][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['until']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Exclude',
            'desc' => 'Exclude dates from calendar',
            'fields' => [
                $fieldName . '[exclude][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['exclude']['value'] ?? '',
                ],
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Include',
            'desc' => 'Include dates from calendar. Only applicable when repeat is enabled, and frequency is "dates".',
            'fields' => [
                $fieldName . '[include][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['include']['value'] ?? '',
                ],
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Separator',
            'desc' => 'When using Exclude or Include dates, how are they separated in your import file?',
            'fields' => [
                $fieldName . '[separator][value]' => [
                    'type' => 'dropdown',
                    'choices' => [',' => ',', '|' => '|'],
                    'value' => $savedFieldValues['separator']['value'] ?? ',',
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function finalPostData(ImportField $importField)
    {
        /*
            array (
              'calendar_id' => '1',
              'start_day' => '01/01/2026',
              'start_time' => '12:01 am',
              'all_day' => '1',
              'end_day' => '01/02/2026',
              'end_time' => '11:59 pm',
              'repeats' => '1',
              'interval' => '3',
              'freq' => 'daily',
              'monthly' =>
                  array (
                    'bymonthdayorbyday' => 'bymonthday',
                    'bydayinterval' => '1',
                  ),
              'yearly' =>
                  array (
                    'bydayinterval' => '1',
                  ),
              'until' => '01/31/2026',
              'exclude' =>
                  array (
                    0 => '03/18/2025',
                    1 => '03/20/2025',
                  ),
              'field_id' => 17,
            )

            array (
              'calendar_id' => '1',
              'start_day' => '10/21/2025',
              'start_time' => '',
              'all_day' => '1',
              'end_day' => '05/31/2026',
              'end_time' => '',
              'repeats' => '1',
              'interval' => '1',
              'freq' => 'weekly',
              'weekly' =>
                  array (
                    'byday' =>
                    array (
                      0 => 'SU',
                      1 => 'TH',
                    ),
                  ),
              'monthly' =>
                  array (
                    'bymonthdayorbyday' => 'bymonthday',
                    'bydayinterval' => '1',
                  ),
              'yearly' =>
                  array (
                    'bydayinterval' => '1',
                  ),
              'until' => '04/30/2026',
            )
         */

        $startTime = $importField->fieldImportConfig['start_time']['value'] ?? '';
        $endTime = $importField->fieldImportConfig['end_time']['value'] ?? '';
        $calendarId = $importField->fieldImportConfig['field']['value'] ?? '';
        $allDay = $importField->fieldImportConfig['all_day']['value'] ?? '';
        $repeats = $importField->fieldImportConfig['repeats']['value'] ?? '';
        $until = $importField->fieldImportConfig['until']['value'] ?? '';
        $interval = $importField->fieldImportConfig['interval']['value'] ?? '';
        $frequency = $importField->fieldImportConfig['frequency']['value'] ?? '';
        $include = $importField->fieldImportConfig['include']['value'] ?? '';
        $exclude = $importField->fieldImportConfig['exclude']['value'] ?? '';
        $separator = $importField->fieldImportConfig['separator']['value'] ?? '';

        // Complex rules
        $weekly = $importField->fieldImportConfig['weekly']['value'] ?? '';
        $monthly = $importField->fieldImportConfig['monthly']['value'] ?? '';
        $yearly = $importField->fieldImportConfig['yearly']['value'] ?? '';

        if ($startTime) {
            try {
                $excludeDates = $importField->importer->dataType->get_item($importField->importItem, $exclude);
                $excludeDates = $excludeDates ? array_map(function($date) {
                    return $this->formatDate($date);
                }, explode($separator, $excludeDates)) : '';

                $includeDates = $importField->importer->dataType->get_item($importField->importItem, $include);
                $includeDates = $includeDates ? array_map(function($date) {
                    return $this->formatDate($date);
                }, explode($separator, $includeDates)) : '';

                if (!empty($excludeDates) && (!$repeats || !$interval || !$frequency)) {
                    $importField->importer->logger->log('Exclude dates provided, but missing one or more of: Repeats (bool), Interval (int), Frequency (string)');
                }

                if (!empty($include) && $frequency !== '' && $frequency !== 'dates') {
                    $importField->importer->logger->log('Include dates provided, but frequency is invalid.');
                }

                $data = [
                    'calendar_id' => $calendarId,
                    'start_day' => $this->formatDate($importField->importer->dataType->get_item($importField->importItem, $startTime)),
                    'start_time' => $this->formatDate($importField->importer->dataType->get_item($importField->importItem, $startTime), 'h:i a'),
                    'all_day' => get_bool_from_string($importField->importer->dataType->get_item($importField->importItem, $allDay)),
                    'end_day' => $this->formatDate($importField->importer->dataType->get_item($importField->importItem, $endTime)),
                    'end_time' => $this->formatDate($importField->importer->dataType->get_item($importField->importItem, $endTime), 'h:i a'),
                    'repeats' => get_bool_from_string($importField->importer->dataType->get_item($importField->importItem, $repeats)),
                    'interval' => $importField->importer->dataType->get_item($importField->importItem, $interval),
                    'freq' => $importField->importer->dataType->get_item($importField->importItem, $frequency),
                    'until' => $this->formatDate($importField->importer->dataType->get_item($importField->importItem, $until)),
                    'exclude' => $excludeDates,
                    'dates' => $includeDates,

                    // Complex rules
                    'weekly' => json_decode($importField->importer->dataType->get_item($importField->importItem, $weekly), true),
                    'monthly' => json_decode($importField->importer->dataType->get_item($importField->importItem, $monthly), true),
                    'yearly' => json_decode($importField->importer->dataType->get_item($importField->importItem, $yearly), true),
                ];
            } catch (\Exception $e) {
                $importField->importer->logger->log('Possible invalid date or time: ' . $e->getMessage());
            }

            return $data;
        }

        return [];
    }

    private function formatDate(string $dateString, string $responseFormat = 'm/d/Y'): string
    {
        // Assume it's already a timestamp
        if (is_numeric($dateString)) {
            $timestamp = $dateString;

            // Handle timestamps in milliseconds
            if (strlen($timestamp) === 13) {
                $timestamp = $timestamp / 1000;
            }
        } else {
            $timestamp = strtotime(str_replace('-', '/', $dateString));
        }

        if ($timestamp === false) {
            return '';
        }

        $date = (new DateTimeImmutable())->setTimestamp($timestamp);

        return $date->format($responseFormat);
    }
}
