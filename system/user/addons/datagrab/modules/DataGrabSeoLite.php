<?php

use BoldMinded\DataGrab\Service\Importer;

class DataGrabSeoLite extends AbstractModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'seo_lite';
    }

    public function getDisplayName(): string
    {
        return 'SEO Lite';
    }

    public function displayConfiguration(Importer $importer, array $data = []): array
    {
        return $this->getFormFields($data['data_fields']);
    }

    private function getSeoLiteFields()
    {
        return  [
            'seo_lite_title' => ' Title',
            'seo_lite_keywords' => ' Description',
            'seo_lite_description' => ' Keywords',
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

        foreach ($this->getSeoLiteFields() as $fieldName => $fieldTitle) {
            $fieldOptions[] = [
                'title' => $fieldTitle,
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

        foreach ($this->getSeoLiteFields() as $fieldName => $fieldTitle) {
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

        if (!$this->getSettingValue('seo_lite_title')) {
            return;
        }

        // Not 100% sure I understand this, but core EE is checking for this field and it has been in DG's core for a long time.
        $data["cp_call"] = true;

        foreach ($this->getSeoLiteFields() as $fieldName => $fieldTitle) {
            $data['seo_lite__' . $fieldName] = $importer->dataType->get_item($item, $this->getSettingValue($fieldName));
        }
    }
}
