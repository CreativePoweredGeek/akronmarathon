<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;
use BoldMinded\DataGrab\Traits\FileUploadDestinations;

/**
 * DataGrab Ansel fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_ansel extends AbstractFieldType
{
    use FileUploadDestinations;

    protected string $docUrl = 'https://docs.boldminded.com/datagrab/docs/field-types/ansel';

    public function register_setting(string $fieldName): array
    {
        return [
            $fieldName => [
                'value',
                'filedir',
                'fetch',
                'makesubdir',
                'replace_existing',
                'delete_existing',
                'multi_delimiter',
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
    ): array {
        $config = [];
        $config['label'] = $this->displayLabel($fieldLabel, $fieldName, $fieldRequired, 'ansel');

        $anselField = ee('Model')->get('ChannelField')->filter('field_name', $fieldName)->first();
        $fieldSettings = $anselField->field_settings ?? [];

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
            $fieldSettings['upload_directory'] ?? 0;

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

        $fieldOptions[] = [
            'title' => 'Upload Folder',
            'desc' => 'This defaults to the Ansel field\'s settings. Change with caution.',
            'fields' => [
                $fieldName . '[filedir]' => [
                    'type' => 'html',
                    'content' => $uploadFolderOptions,
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Fetch files from urls',
            'desc' => 'Fetch and download URLs from remote servers?',
            'fields' => [
                $fieldName . '[fetch]' => [
                    'type' => 'dropdown',
                    'choices' => ['No', 'Yes'],
                    'value' => $savedFieldValues['fetch'] ?? '',
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
            'title'  => 'Replace existing images <b class="st-warning">caution</b>',
            'desc'   => 'If the imported image already exists on the file system, replace it with a new version? The file names will remain the same.',
            'fields' => [
                $fieldName . '[replace_existing]' => [
                    'type' => 'dropdown',
                    'choices' => ['No', 'Yes'],
                    'value' => $savedFieldValues['replace_existing'] ?? '',
                ],
            ],
        ];

        $fieldOptions[] = [
            'title'  => 'Remove existing images <b class="st-warning">caution</b>',
            'desc'   => 'If the imported image already exists in this field, remove it? This is very destructive. You will lose any crops you have saved for the image, which essentially resets the field with new images.',
            'fields' => [
                $fieldName . '[delete_existing]' => [
                    'type' => 'dropdown',
                    'choices' => ['No', 'Yes'],
                    'value' => $savedFieldValues['delete_existing'] ?? '',
                ],
            ],
        ];

        $fieldOptions[] = [
            'title'  => 'Multi-file delimiter',
            'desc'   => 'If you want to import multiple images into a single Ansel field separate them by one of the following characters.',
            'fields' => [
                $fieldName . '[multi_delimiter]' => [
                    'type' => 'dropdown',
                    'choices' => [',' => ',', '|' => '|', ';' => ';',],
                    'value' => $savedFieldValues['multi_delimiter'] ?? '',
                ],
            ],
        ];

        return $fieldOptions;
    }

    public function preparePostData(ImportField $importField): array
    {
        $data = [];
        $deleteExisting = $importField->fieldImportConfig['delete_existing'] == 1;
        $delimiter = $importField->fieldImportConfig['multi_delimiter'] ?: ',';

        $itemsToImport = [];
        if ($importField->propertyValue) {
            if (strpos($importField->propertyValue, $delimiter) !== false) {
                $itemsToImport = explode($delimiter, $importField->propertyValue);
            } else {
                $itemsToImport = [$importField->propertyValue];
            }
        }

        $existingFiles = ee('db')
            ->where([
                'content_id' => $importField->entryId,
                'content_type' => 'channel',
                'field_id' => $importField->fieldId,
            ])
            ->get('ansel_images')
            ->result_array();

        $originalFileIds = array_column($existingFiles, 'original_file_id');

        // Optionally delete all existing images in the field.
        // This will delete all crops, so it is very destructive.
        if ($deleteExisting && $importField->entryId) {
            //$anselRowsToDelete = array_column($toDelete, 'id');
            $fileRowsToDelete = array_column($existingFiles, 'file_id');

            // Delete old uploads and saves
            $allDeleteIds = array_merge($fileRowsToDelete, $originalFileIds);

            if (count($allDeleteIds) > 0) {
                ee('Model')->get('File')
                    ->filter('file_id', 'IN', $allDeleteIds)
                    ->delete();
            }
        }

        if (count($itemsToImport) > 0) {
            $files = [];

            foreach ($itemsToImport as $item) {
                $fileName = $importField->importer->getFile(
                    $item,
                    $importField->fieldImportConfig['filedir'],
                    $importField->fieldImportConfig['fetch'] == 1,
                    $importField->fieldImportConfig['makesubdir'] ?: false,
                    $importField->fieldImportConfig['replace_existing'] ?: false,
                );

                preg_match('/{file:(\d+):url}/', $fileName, $matches);
                $fileId = $matches[1] ? (int) $matches[1] : null;

                if ($fileId === null) {
                    $importField->importer->logger->log(
                        'Could not determine file id. DataGrab only supports Ansel uploads when ExpressionEngine\'s file compatibility mode is off.'
                    );
                    continue;
                }

                if (!$deleteExisting && in_array($fileId, $originalFileIds)) {
                    $importField->importer->logger->log(sprintf(
                        'File already exists, nothing to re-save in Ansel. %s',
                        $item
                    ));
                    continue;
                }

                /** @var \ExpressionEngine\Model\File\File $file */
                $file = ee('Model')->get('File', $fileId)->first();

                $fileTitle = $file->getRawProperty('title');
                $fileTitleParts = $this->getFileNameParts($fileTitle);

                $files[] = [
                    'source_id' => $importField->importer->settings['import']['channel'],
                    'ansel_image_id' => '',
                    'ansel_image_delete' => '',
                    'source_file_id' => $matches[1],
                    'original_location_type' => 'ee',
                    'upload_location_id' => $file->upload_location_id,
                    'upload_location_type' => 'ee',
                    'filename' => $fileTitleParts[0],
                    'extension' => $fileTitleParts[1],
                    'file_location' => '',
                    'x' => 0,
                    'y' => 0,
                    'width' => $file->get__width(),
                    'height' => $file->get__height(),
                    'order' => 0,
                    'title' => '',
                    'description' => '',
                    'cover' => 'n',
                ];
            }

            if (count($files) > 0) {
                $data = $files;
            }
        }

        return $data;
    }

    private function getFileNameParts(string $fileName): array
    {
        $pos = strrpos($fileName, '.');

        if ($pos === false) {
            return [$fileName];
        }

        return [
            substr($fileName, 0, $pos),
            substr($fileName, $pos + 1)
        ];
    }
}
