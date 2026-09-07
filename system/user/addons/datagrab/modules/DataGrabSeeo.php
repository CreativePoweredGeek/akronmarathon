<?php

use BoldMinded\DataGrab\Service\Importer;

class DataGrabSeeo extends AbstractModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'seeo';
    }

    public function getDisplayName(): string
    {
        return 'SEEO';
    }

    public function displayConfiguration(Importer $importer, array $data = []): array
    {
        return $this->getFormFields($data['data_fields']);
    }

    private function getSeeoFields()
    {
        return [
            'seeo__title' => [
                'title' => 'Title',
                'callback' => null,
            ],
            'seeo__description' => [
                'title' => 'Description',
                'callback' => null,
            ],
            'seeo__keywords' => [
                'title' => 'Keywords',
                'callback' => null,
            ],
            'seeo__author' => [
                'title' => 'Author',
                'callback' => null,
            ],
            'seeo__canonical_url' => [
                'title' => 'Canonical URL',
                'callback' => null,
            ],
            'seeo__robots' => [
                'title' => 'Robots',
                'callback' => null,
            ],
            'seeo__meta_select' => [
                'title' => 'Meta Select',
                'callback' => null,
            ],
            'seeo__copy_og' => [
                'title' => 'Copy Open Graph from Entry',
                'callback' => function ($value) {
                    if ($value === '') {
                        return 'default';
                    }

                    return !in_array(strtolower($value), ['default', '-', 'yes', 'no'], true) ? 'default' : $value;
                },
            ],
            'seeo__og_title' => [
                'title' => 'Open Graph Title',
                'callback' => null,
            ],
            'seeo__og_description' => [
                'title' => 'Open Graph Description',
                'callback' => null,
            ],
            'seeo__og_type' => [
                'title' => 'Open Graph Type',
                'callback' => null,
            ],
            'seeo__og_url' => [
                'title' => 'Open Graph URL',
                'callback' => null,
            ],
            'seeo__og_image' => [
                'title' => 'Open Graph Image',
                'callback' => null,
            ],
            'seeo__copy_twitter' => [
                'title' => 'Copy Twitter from Entry',
                'callback' => function ($value) {
                    if ($value === '') {
                        return 'default';
                    }

                    return !in_array(strtolower($value), ['default', '-', 'yes', 'no'], true) ? 'default' : $value;
                },
            ],
            'seeo__twitter_title' => [
                'title' => 'Twitter Title',
                'callback' => null,
            ],
            'seeo__twitter_description' => [
                'title' => 'Twitter Description',
                'callback' => null,
            ],
            'seeo__twitter_content_type' => [
                'title' => 'Twitter Content Type',
                'callback' => null,
            ],
            'seeo__twitter_image' => [
                'title' => 'Twitter Image',
                'callback' => null,
            ],
            'seeo__sitemap_priority' => [
                'title' => 'Sitemap Priority',
                'callback' => null,
            ],
            'seeo__sitemap_change_frequency' => [
                'title' => 'Sitemap Change Frequency',
                'callback' => null,
            ],
            'seeo__sitemap_include' => [
                'title' => 'Include in Sitemap',
                'callback' => null,
            ],
        ];
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

        foreach ($this->getSeeoFields() as $fieldName => $fieldData) {
            $fieldOptions[] = [
                'title' => $fieldData['title'],
                'fields' => [
                    $this->getName() .'['. $fieldName .']' => [
                        'type' => 'dropdown',
                        'choices' => $data,
                        'value' => $this->getSettingValue($fieldName),
                    ]
                ]
            ];
        }

        return $fieldOptions;
    }

    public function saveConfiguration(Importer $importer): array
    {
        $data = ee()->input->post($this->getName());
        $config = [
            'execute_on_action' => $data['execute_on_action'] ?? '',
        ];

        foreach ($this->getSeeoFields() as $fieldName => $fieldData) {
            $config[$fieldName] = $data[$fieldName] ?? '';
        }

        return $config;
    }

    public function handle(Importer $importer, array &$data = [], array $item = [], array $custom_fields = [], string $action = '')
    {
        $onAction = $this->getSettingValue('execute_on_action');

        // We have a specific execution time, and now is not the time.
        if ($onAction !== $action && $onAction !== '') {
            return;
        }

        // Not 100% sure I understand this, but core EE is checking for this field and it has been in DG's core for a long time.
        $data["cp_call"] = true;

        foreach ($this->getSeeoFields() as $fieldName => $fieldData) {
            $value = $importer->dataType->get_item($item, $this->getSettingValue($fieldName));

            if ($fieldData['callback'] !== null && is_callable($fieldData['callback'])) {
                //$value = call_user_func($fieldData['callback'], $value);
            }

            $data[$fieldName] = $value;
        }
    }
}
