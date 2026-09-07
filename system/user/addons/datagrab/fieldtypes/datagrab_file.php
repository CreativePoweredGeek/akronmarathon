<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\FileMeta;
use BoldMinded\DataGrab\Service\Importer;
use BoldMinded\DataGrab\Traits\FileUploadDestinations;

/**
 * DataGrab File fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_file extends AbstractFieldType
{
    use FileUploadDestinations;

    protected string $docUrl = 'https://docs.boldminded.com/datagrab/docs/field-types/file';

    public function register_setting(string $fieldName): array
    {
        return [
            $fieldName => [
                'value',
                'filedir',
                'fetch',
                'makesubdir',
                'replace',
                'file_credit',
                'file_description',
                'file_location',
            ],
        ];
    }

    public function display_configuration(Importer $importer, string $fieldName, string $fieldLabel, string $fieldType, bool $fieldRequired = false, array $data = []): array
    {
        $config = [];
        $config['label'] = $this->displayLabel($fieldLabel, $fieldName, $fieldRequired, 'file');
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
        $defaultChosenDir = $savedFieldValues['filedir'] ??
            $fieldSettings['allowed_directories'] ?? 0;

        $uploadFolderOptions = $this->buildFileUploadDropdown(
            $fieldName . '[filedir]',
            $defaultChosenDir
        );

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

        if ($data['importType'] === 'file') {
            $fieldOptions[] = [
                'title' => 'Upload Folder',
                'desc' => '',
                'fields' => [
                    $fieldName . '[filedir]' => [
                        'type' => 'hidden',
                        'value' => $data['default_settings']['import']['file_directory'] ?? '',
                    ],
                    $fieldName . '[filedir_label]' => [
                        'type' => 'html',
                        'content' => '<em>Upload folder managed by import settings.</em>',
                    ]
                ]
            ];
        } else {
            $fieldOptions[] = [
                'title' => 'Upload Folder',
                'desc' => '',
                'fields' => [
                    $fieldName . '[filedir]' => [
                        'type' => 'html',
                        'content' => $uploadFolderOptions,
                    ]
                ]
            ];
        }

        $fieldOptions[] = [
            'title' => 'Fetch files from urls',
            'desc' => 'Fetch and download URLs from remote servers?',
            'fields' => [
                $fieldName . '[fetch]' => [
                    'type' => 'dropdown',
                    'choices' => ['No', 'Yes'],
                    'value' => $savedFieldValues['fetch'] ?? 0,
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Create sub-directories',
            'desc' => 'Copy the sub-directory path of a remote file and re-create it locally?',
            'fields' => [
                $fieldName . '[makesubdir]' => [
                    'type' => 'dropdown',
                    'choices' => ['No', 'Yes'],
                    'value' => $savedFieldValues['makesubdir'] ?? 0,
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Replace Existing',
            'desc' => '',
            'fields' => [
                $fieldName . '[replace]' => [
                    'type' => 'dropdown',
                    'choices' => ['No', 'Yes'],
                    'value' => $savedFieldValues['replace'] ?? 0,
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'File Description',
            'desc' => '',
            'fields' => [
                $fieldName . '[file_description]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['file_description'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'File Credit',
            'desc' => '',
            'fields' => [
                $fieldName . '[file_credit]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['file_credit'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'File Location',
            'desc' => '',
            'fields' => [
                $fieldName . '[file_location]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['file_location'] ?? '',
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function preparePostData(ImportField $importField): string
    {
        // Fetch file from data
        if ($importField->propertyValue !== '') {
            $credit = '';
            $creditPath = $importField->fieldImportConfig['file_credit'] ?? '';

            if ($creditPath) {
                $credit = $importField->importer->dataType->get_item(
                    $importField->importItem,
                    $creditPath
                );
            }

            $description = '';
            $descriptionPath = $importField->fieldImportConfig['file_description'] ?? '';

            if ($descriptionPath) {
                $description = $importField->importer->dataType->get_item(
                    $importField->importItem,
                    $descriptionPath
                );
            }

            $location = '';
            $locationPath = $importField->fieldImportConfig['file_location'] ?? '';

            if ($locationPath) {
                $location = $importField->importer->dataType->get_item(
                    $importField->importItem,
                    $locationPath
                );
            }

            $filename = $importField->importer->getFile(
                $importField->propertyValue,
                $importField->fieldImportConfig['filedir'],
                $importField->fieldImportConfig['fetch'] == 1,
                $importField->fieldImportConfig['makesubdir'] == 1,
                $importField->fieldImportConfig['replace'] == 1,
                new FileMeta(
                    credit: $credit,
                    description: $description,
                    location: $location
                ),
            );

            if ($filename) {
                return $filename;
            }
        }

        return '';
    }
}
