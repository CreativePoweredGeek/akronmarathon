<?php

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\App;
use BoldMinded\DataGrab\Service\Importer;

class DataGrabStructure extends AbstractModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'structure';
    }

    public function getDisplayName(): string
    {
        return 'Structure';
    }

    public function displayConfiguration(Importer $importer, array $data = []): array
    {
        return $this->getFormFields($data['data_fields']);
    }

    private function getFormFields(array $data = []): array
    {
        $options = [
            '' => 'Create or Update', // Default, also backwards compatible
            'create' => 'Create Only',
            'update' => 'Update Only',
        ];

        $fieldOptions[] = [
            'title' => 'Execution Time',
            'desc' => 'When should DataGrab perform Structure updates?',
            'fields' => [
                $this->getName() .'[execute_on_action]' => [
                    'type' => 'dropdown',
                    'choices' => $options,
                    'value' => $this->getSettingValue('execute_on_action'),
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'URL',
            'desc' => 'Leave blank to generate the Struture URL automatically.',
            'fields' => [
                $this->getName() .'[url]' => [
                    'type' => 'dropdown',
                    'choices' => $data,
                    'value' => $this->getSettingValue('url'),
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Template',
            'desc' => 'Leave blank to use the channel\'s default template.',
            'fields' => [
                $this->getName() .'[template]' => [
                    'type' => 'dropdown',
                    'choices' => $data,
                    'value' => $this->getSettingValue('template'),
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Parent Entry ID',
            'desc' => 'Leave blank to not set a parent Entry ID.',
            'fields' => [
                $this->getName() .'[parent_id]' => [
                    'type' => 'dropdown',
                    'choices' => $data,
                    'value' => $this->getSettingValue('parent_id'),
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Parent Entry Title',
            'desc' => 'Leave blank to not set a parent Entry ID. If set this will override the Parent Entry ID setting, 
                and if the value of this field matches an existing entry, that entry will be set to the parent of the imported item.',
            'fields' => [
                $this->getName() .'[parent_title]' => [
                    'type' => 'dropdown',
                    'choices' => $data,
                    'value' => $this->getSettingValue('parent_title'),
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function saveConfiguration(Importer $importer): array
    {
        $data = ee()->input->post($this->getName());

        return [
            'execute_on_action' => $data['execute_on_action'] ?? '',
            'url' => $data['url'] ?? '',
            'template' => $data['template'] ?? '',
            'parent_id' => $data['parent_id'] ?? '',
            'parent_title' => $data['parent_title'] ?? '',
        ];
    }

    public function handle(Importer $importer, array &$data = [], array $item = [], array $custom_fields = [], string $action = '')
    {
        $onAction = $this->getSettingValue('execute_on_action');

        // We have a specific execution time, and now is not the time.
        if ($onAction !== $action && $onAction !== '') {
            return;
        }

        if ($importer->db->table_exists('exp_structure_channels')) {
            // If the structure module tables exists, try and get template id
            $importer->db->select('template_id');
            $importer->db->from('exp_structure_channels');
            $importer->db->where('channel_id', $importer->channelDefaults['channel_id']);
            $importer->db->where('type !=', 'unmanaged');

            /** @var CI_DB_result $query */
            $query = $importer->db->get();

            if ($query->num_rows() > 0) {
                $row = $query->row_array();

                $data['cp_call'] = true;

                $parentTitle = $importer->dataType->get_item($item, $this->getSettingValue('parent_title'));
                $parentId = $importer->dataType->get_item($item, $this->getSettingValue('parent_id'));
                $templateId = $importer->dataType->get_item($item, $this->getSettingValue('template_id'));
                $url = $importer->dataType->get_item($item, $this->getSettingValue('url'));

                if ($templateId) {
                    $data['structure__template_id'] = $templateId;
                } else {
                    $data['structure__template_id'] = $row['template_id'];
                }

                if ($url) {
                    $data['structure__uri'] = ee('Format')
                        ->make('Text', $url)
                        ->urlSlug()
                        ->compile();
                } else {
                    $data['structure__uri'] = ee('Format')->make('Text', $data['title'])
                        ->urlSlug()
                        ->compile();
                }

                if ($parentTitle) {
                    $entry = ee('Model')->get('ChannelEntry')
                        ->filter('title', $parentTitle)
                        ->first();

                    if ($entry) {
                        $data['structure__parent_id'] = $entry->entry_id;
                    }
                } elseif ($parentId) {
                    $data['structure__parent_id'] = $parentId;
                } else {
                    $data['structure__parent_id'] = App::isGteEE7() ? '' : 0;
                }

                // Eek! Workaround Structure 'bug' that expects post data
                $_POST['channel_id'] = $importer->channelDefaults['channel_id'];
                $_POST['template_id'] = $data['structure__template_id'];
                $_POST['parent_id'] = $data['structure__parent_id'];

                // Structure uses config variable to get site_pages
                // This only gets updated on page load (ie, once at the start
                // of the import) so we have to keep updating it here...
                $importer->db->select('site_pages');
                $importer->db->where('site_id', $importer->config->item('site_id'));
                $query = $importer->db->get('sites');
                $site_pages = unserialize(base64_decode($query->row('site_pages')));
                $importer->config->config['site_pages'] = $site_pages;
            }
        }
    }
}
