<?php

namespace BoldMinded\DataGrab\Service\ContentType;

use BoldMinded\DataGrab\Queue\Jobs\DeleteFile;
use BoldMinded\DataGrab\Queue\Jobs\ImportFile;
use BoldMinded\DataGrab\Traits\FileUploadDestinations;

class FileContentType implements ContentTypeInterface
{
    use FileUploadDestinations;

    public function getName(): string
    {
        return 'file';
    }

    public function getImportJobClass(): string
    {
        return ImportFile::class;
    }

    public function getDeleteJobClass(): string
    {
        return DeleteFile::class;
    }

    public function getCustomFieldPrefix(): string
    {
        return 'field_id_';
    }

    public function getFieldModelName(): string
    {
        return 'ChannelField';
    }

    /**
     * The file import path maps a single synthetic "import_file" field rather
     * than a set of user defined custom fields.
     */
    public function getCustomFields(array $settings = []): array
    {
        return [];
    }

    public function getCustomFieldSettings(string $fieldName): array
    {
        $field = ee('Model')
            ->get('ChannelField')
            ->filter('field_name', $fieldName)
            ->first();

        if (!$field) {
            return [];
        }

        return $field->field_settings ?? [];
    }

    public function getRecordsToDelete(array $importedIds, array $settings = []): array
    {
        $directory = $this->getUploadDirectory($settings['import']['file_directory'] ?? 0);

        if (!$directory) {
            return [];
        }

        return ee('Model')->get('File')
            ->filter('upload_location_id', $directory->upload_location_id)
            ->filter('model_type', 'File')
            ->filter('file_id', 'NOT IN', $importedIds)
            ->all()
            ->pluck('file_id');
    }

    public function supportsCategories(): bool
    {
        return true;
    }
}
