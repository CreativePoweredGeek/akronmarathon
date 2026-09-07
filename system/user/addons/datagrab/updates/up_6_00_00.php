<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_6_00_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        // If the column exists, then all this has already completed.
        // Don't accidentally try the updates again.
        if ($this->legacySettingsExist()) {
            return;
        }

        $this->backupSettings();
        $this->migrateImportTypes();
        $this->updateSettings();
    }

    private function legacySettingsExist(): bool
    {
        return ee('db')->field_exists('settings_legacy', 'datagrab');
    }

    private function backupSettings()
    {
        if (!$this->legacySettingsExist()) {
            ee()->load->dbforge();
            ee()->dbforge->add_column('datagrab', [
                'settings_legacy' => [
                    'type' => 'text',
                    'null' => true,
                ],
            ]);
        }

        $imports = ee('db')->get('datagrab')->result_array();

        if (empty($imports)) {
            return;
        }

        foreach ($imports as $import) {
            ee('db')
                ->update('datagrab', [
                    'settings_legacy' => $import['settings']
                ], [
                    'id' => $import['id']
                ]);
        }
    }

    private function migrateImportTypes()
    {
        $imports = ee('db')->get('datagrab')->result_array();

        if (empty($imports)) {
            return;
        }

        foreach ($imports as $import) {
            $settings = unserialize($import['settings']);
            $settings['import']['type'] = $settings['import']['type'] . '_legacy';

            ee('db')
                ->update('datagrab', [
                    'settings' => json_encode($settings)
                ], [
                    'id' => $import['id']
                ]);
        }
    }

    private function updateSettings()
    {
        // Fetch import settings
        $imports = ee('db')->get('datagrab')->result_array();

        if (empty($imports)) {
            return;
        }

        foreach ($imports as $import) {
            $settings = json_decode($import['settings'], true);
            $channelId = $settings['import']['channel'] ?? null;

            if (!$channelId) {
                continue;
            }

            $customFieldSettings = $settings['cf'];
            $channel = ee('Model')->get('Channel', (int) $channelId)->first();

            foreach ($channel->getAllCustomFields() as $field) {
                if (!isset($customFieldSettings[$field->field_name])) {
                    continue;
                }

                $fieldType = $field->field_type;
                $fieldName = $field->field_name;

                $mapping = $this->getMapping($fieldType);

                if (count($mapping) === 0) {
                    continue;
                }

                $this->transpose($fieldName, $customFieldSettings, $mapping);

                $settings['cf'] = $customFieldSettings;
            }

            ee('db')
                ->update('datagrab', [
                    'settings' => json_encode($settings)
                ], [
                    'id' => $import['id']
                ]);
        }
    }

    private function getMapping(string $fieldType): array
    {
        $mappings = [
            'calendar' => [
                '_calendar_start_time' => 'start_time',
                '_calendar_end_time' => 'end_time',
                '_calendar_field' => 'field',
            ],
            'file' => [
                '_filedir' => 'filedir',
                '_fetch' => 'fetch',
                '_makesubdir' => 'makesubdir',
                '_replace' => 'replace',
            ],
            'fluid' => [
                '_fields' => 'fields',
                '_unique' => 'unique',
                '_upload_dir' => 'DELETE',
                '_fetch_url' => 'DELETE',
                '_makesubdir' => 'DELETE',
            ],
            'grid' => [
                '_columns' => 'columns',
                '_unique' => 'unique',
            ],
            'low_events' => [
                '_low_events_start_date' => 'start_date',
                '_low_events_start_time' => 'start_time',
                '_low_events_end_date' => 'end_date',
                '_low_events_end_time' => 'end_time',
                '_low_events_all_day' => 'all_day',
            ],
            'relationship' => [
                '_relationship_field' => 'relationship_field',
            ],
            'simple_grid' => [
                '_columns' => 'columns',
                '_unique' => 'unique',
                '_extra1' => 'DELETE',
                '_extra2' => 'DELETE',
                '_extra3' => 'DELETE',
            ],
            'simple_table' => [
                '_columns' => 'columns',
                '_unique' => 'unique',
                '_extra1' => 'DELETE',
                '_extra2' => 'DELETE',
            ],
        ];

        return $mappings[$fieldType] ?? [];
    }

    private function transpose(string $fieldName, array &$settings, array $mapping)
    {
        $value = $settings[$fieldName] ?? '';
        unset($settings[$fieldName]);
        $settings[$fieldName]['value'] = $value;

        foreach ($mapping as $oldKey => $newKey) {
            // Just remove it, it requires manual intervention to update the field settings.
            if ($newKey === 'DELETE') {
                unset($settings[$fieldName . $oldKey]);
                continue;
            }

            // We have something to actually map, so we can automate this part.
            if (isset($settings[$fieldName . $oldKey])) {
                $settings[$fieldName][$newKey] = $settings[$fieldName . $oldKey];
                unset($settings[$fieldName . $oldKey]);
            }
        }
    }
}
