<?php

namespace BoldMinded\DataGrab\Service\ContentType;

use BoldMinded\DataGrab\Queue\Jobs\DeleteEntry;
use BoldMinded\DataGrab\Queue\Jobs\ImportEntry;

class ChannelContentType implements ContentTypeInterface
{
    public function getName(): string
    {
        return 'entry';
    }

    public function getImportJobClass(): string
    {
        return ImportEntry::class;
    }

    public function getDeleteJobClass(): string
    {
        return DeleteEntry::class;
    }

    public function getCustomFieldPrefix(): string
    {
        return 'field_id_';
    }

    public function getFieldModelName(): string
    {
        return 'ChannelField';
    }

    public function getCustomFields(array $settings = []): array
    {
        $channelId = $settings['import']['channel'] ?? 0;
        $channel = ee('Model')->get('Channel', (int) $channelId)->first();

        if (!$channel) {
            return [];
        }

        $customFields = [];

        foreach ($channel->getAllCustomFields() as $row) {
            $customFields[$row->field_name]['id'] = $row->field_id;
            $customFields[$row->field_name]['format'] = $row->field_fmt;
            $customFields[$row->field_name]['type'] = $row->field_type;
            $customFields[$row->field_name]['name'] = $row->field_name;
        }

        return $customFields;
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
        $channelId = $settings['import']['channel'] ?? 0;

        $query = ee()->db
            ->select('entry_id')
            ->where_not_in('entry_id', $importedIds)
            ->where('channel_id = ', $channelId)
            ->get('channel_titles');

        return array_column($query->result_array(), 'entry_id');
    }

    public function supportsCategories(): bool
    {
        return true;
    }
}
