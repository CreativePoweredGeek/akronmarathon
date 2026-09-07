<?php

use BoldMinded\DataGrab\Service\Importer;

/**
 * Why does anyone still use this module?!
 */
class DataGrabPages extends AbstractModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'pages';
    }

    public function getDisplayName(): string
    {
        return 'Pages';
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
            'title' => 'Add as page?',
            'desc' => 'Check this box if you want to add the imported entries as Pages.',
            'fields' => [
                $this->getName() .'[pages]' => [
                    'type' => 'dropdown',
                    'choices' => ['' => 'No', 'y' => 'Yes'],
                    'value' => $this->getSettingValue('url'),
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Page URL',
            'desc' => 'Leave blank to generate the page url title automatically.',
            'fields' => [
                $this->getName() .'[pages_url]' => [
                    'type' => 'dropdown',
                    'choices' => $data,
                    'value' => $this->getSettingValue('pages_url'),
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Page Template',
            'desc' => 'Leave blank to use the channel\'s default Page template.',
            'fields' => [
                $this->getName() .'[pages_template]' => [
                    'type' => 'dropdown',
                    'choices' => $data,
                    'value' => $this->getSettingValue('pages_template'),
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
            'pages' => $data['pages'] ?? '',
            'pages_url' => $data['pages_url'] ?? '',
            'pages_template' => $data['pages_template'] ?? '',
        ];
    }

    public function handle(Importer $importer, array &$data = [], array $item = [], array $custom_fields = [], string $action = '')
    {
        $onAction = $this->getSettingValue('execute_on_action');

        // We have a specific execution time, and now is not the time.
        if ($onAction !== $action && $onAction !== '') {
            return;
        }

        if (!$this->getSettingValue('pages')) {
            return;
        }

        // Not 100% sure I understand this, but core EE is checking for this field and it has been in importer's core for a long time.
        $data["cp_call"] = true;

        if ($this->getSettingValue('pages_url') === '') {
            $entryUrlTitle = ee('Format')->make('Text', $data["title"])->urlSlug()->compile();
            $data["pages__pages_uri"] = $entryUrlTitle;
            $_POST["pages__pages_uri"] = $entryUrlTitle;
        } else {
            $data["pages__pages_uri"] = $importer->dataType->get_item($item, $this->getSettingValue('pages_url'));
            $_POST["pages__pages_uri"] = $importer->dataType->get_item($item, $this->getSettingValue('pages_url'));
        }

        $importer->db->select("configuration_value");
        $importer->db->where("configuration_name", "template_channel_" . $importer->channelDefaults["channel_id"]);
        $query = $importer->db->get("exp_pages_configuration");

        if ($query->num_rows() > 0) {
            $row = $query->row_array();
            $default_template = $row["configuration_value"];
        } else {
            $importer->db->select("exp_templates.template_id");
            $importer->db->from("exp_templates");
            $importer->db->join("exp_template_groups", "exp_template_groups.group_id = exp_templates.group_id");
            $importer->db->where("is_site_default", "y");
            $importer->db->where("template_name", "index");
            $query = $importer->db->get();
            $row = $query->row_array();
            $default_template = $row["template_id"] ?? 1;
        }

        if ($this->getSettingValue('pages_template')) {
            $template = $importer->dataType->get_item($item, $this->getSettingValue('pages_template'));
            $template_segments = explode("/", $template);

            if (count($template_segments) == 2) {
                $importer->db->select("exp_templates.template_id");
                $importer->db->from("exp_templates");
                $importer->db->join("exp_template_groups", "exp_template_groups.group_id = exp_templates.group_id");
                $importer->db->where("group_name", $template_segments[0]);
                $importer->db->where("template_name", $template_segments[1]);
                $query = $importer->db->get();
                if ($query->num_rows() > 0) {
                    $row = $query->row_array();
                    $default_template = $row["template_id"];
                }
            }
        }

        $data["pages__pages_template_id"] = $default_template;
        $_POST["pages__pages_template_id"] = $default_template;
    }
}
