<?php

namespace BoldMinded\Speedy\Service\Drivers\Configuration;

use BoldMinded\Speedy\Model\DriverConfiguration;

class StaticSettingsForm implements SettingsFormInterface
{
    /** @var array */
    private $formErrors;

    /**
     * @param \BoldMinded\Speedy\Model\DriverConfiguration $configuration
     * @return array
     */
    public function getSections(DriverConfiguration $configuration)
    {
        $settings = $configuration->settings;
        $grid = $this->getIgnoresGrid($settings);

        ee()->load->helper('array');

        return [
            [
                [
                    'title'  => 'speedy_static_ignores',
                    'desc'   => 'speedy_static_ignores_desc',
                    'grid'   => true,
                    'wide'   => true,
                    'fields' => [
                        'ignore_urls' => [
                            'type'    => 'html',
                            'content' => ee()->load->view('_shared/table', $grid->viewData(), true),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param \BoldMinded\Speedy\Model\DriverConfiguration $configuration
     * @return bool
     */
    public function validate(DriverConfiguration $configuration)
    {
        // Retrieve and validate ignore urls
        $urls = ee()->input->post('ignore_urls');
        $validate = [];

        if (is_array($urls) && isset($urls['rows']) && is_array($urls['rows'])) {
            foreach ($urls['rows'] as $row_id => $columns) {
                $settings['ignore_urls'][] = $columns;
                $validate[$row_id] = $columns;
            }
        }

        foreach ($validate as $row_id => $model) {
            $result = $this->validateArray($model, [
                'url' => 'required',
            ]);

            if (!$result->isValid()) {
                $this->formErrors['ignore_urls'][$row_id] = $result->renderErrors();
            }
        }

        $configuration->settings = $settings;

        return empty($this->formErrors);
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        $errors = $this->formErrors;

        // We need to replace grid errors with a dummy string to avoid errors
        if (isset($errors['ignore_urls'])) {
            $errors['ignore_urls'] = 'dummy_value';
        }

        return $errors;
    }

    /**
     * @param array $settings
     * @return \ExpressionEngine\Library\CP\GridInput
     */
    private function getIgnoresGrid(array $settings)
    {
        $grid = ee('CP/GridInput', ['field_name' => 'ignore_urls', 'reorder' => false]);
        $grid->loadAssets();
        $grid->setColumns([
            'speedy_static_ignore_url',
        ]);
        $grid->setNoResultsText('speedy_static_ignore_url_no_results', 'speedy_static_ignore_url_add_btn');
        $grid->setBlankRow($this->getIgnoreGridRow());

        $validation_data = ee()->input->post('ignore_urls');
        $urls = [];

        // If we're coming back on a validation error, load the Grid from the POST data
        if (!empty($validation_data)) {
            foreach ($validation_data['rows'] as $row_id => $columns) {
                $urls[$row_id] = [
                    // Fix this, multiple new rows won't namespace right
                    'url' => $columns['url'],
                ];
            }

            if (isset($this->formErrors['ignore_urls'])) {
                foreach ($this->formErrors['ignore_urls'] as $row_id => $columns) {
                    $urls[$row_id]['errors'] = array_map('strip_tags', $columns);
                }
            }
        } elseif (isset($settings['ignore_urls']) && is_array($settings['ignore_urls'])) {
            $urls = $settings['ignore_urls'];
        }

        if (!empty($urls)) {
            $data = [];

            foreach ($urls as $i => $url) {
                $data[] = [
                    'attrs'   => ['row_id' => $i],
                    'columns' => $this->getIgnoreGridRow($url),
                ];
            }

            $grid->setData($data);
        }

        return $grid;
    }

    /**
     * @param array $url
     * @return array
     */
    private function getIgnoreGridRow($url = [])
    {
        $defaults = [
            'url' => '',
        ];

        $url = array_merge($defaults, $url);
        $url = array_map('form_prep', $url);

        return [
            [
                'html'  => form_input('url', $url['url']),
                'error' => $this->getGridFieldError($url, 'url'),
            ],
        ];
    }

    /**
     * @param array $field
     * @param string $column
     * @return array|null
     */
    private function getGridFieldError($field, $column)
    {
        if (isset($field['errors'][$column])) {
            return $field['errors'][$column];
        }

        return null;
    }

    /**
     * @param array $data
     * @param array $rules
     * @return \ExpressionEngine\Service\Validation\Result
     */
    private function validateArray(array $data, array $rules)
    {
        /** @var \ExpressionEngine\Service\Validation\Validator */
        $validator = ee('Validation')->make($rules);

        return $validator->validate($data);
    }
}
