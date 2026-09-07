<?php

namespace BoldMinded\Speedy\Service\Drivers\Configuration;

use BoldMinded\Speedy\Model\DriverConfiguration;

class MemcachedSettingsForm implements SettingsFormInterface
{
    /** @var array */
    private $upload_errors;

    /**
     * @param \BoldMinded\Speedy\Model\DriverConfiguration $configuration
     * @return array
     */
    public function getSections(DriverConfiguration $configuration)
    {
        $settings = $configuration->settings;
        $grid = $this->getServersGrid($settings);

        ee()->load->helper('array');

        return [
            [
                [
                    'title'  => 'speedy_memcached_persistent',
                    'fields' => [
                        'persistent' => [
                            'type'  => 'yes_no',
                            'value' => element('persistent', $settings, 'n'),
                        ],
                    ],
                ],
                [
                    'title'  => 'speedy_memcached_servers',
                    'grid'   => true,
                    'wide'   => true,
                    'fields' => [
                        'servers' => [
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
        // Retrieve and validate text fields
        $settings = [
            'persistent' => ee()->input->post('persistent'),
        ];

        $result = $this->validateArray($settings, [
            'persistent' => 'enum[y,n]',
        ]);

        if (!$result->isValid()) {
            $this->upload_errors = $result->renderErrors();
        }

        // Retrieve and validate servers
        $servers = ee()->input->post('servers');
        $validate = [];

        if (is_array($servers) && isset($servers['rows']) && is_array($servers['rows'])) {
            foreach ($servers['rows'] as $row_id => $columns) {
                $settings['servers'][] = $columns;
                $validate[$row_id] = $columns;
            }
        }

        foreach ($validate as $row_id => $model) {
            if ($model['weight'] === '') {
                $model['weight'] = 0;
            }

            $result = $this->validateArray($model, [
                'host' => 'required',
                'port' => 'required',
                'weight' => 'required',
            ]);

            if (!$result->isValid()) {
                $this->upload_errors['servers'][$row_id] = $result->renderErrors();
            }
        }

        $configuration->settings = $settings;

        return empty($this->upload_errors);
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        $errors = $this->upload_errors;

        // We need to replace grid errors with a dummy string to avoid errors
        if (isset($errors['servers'])) {
            $errors['servers'] = 'dummy_value';
        }

        return $errors;
    }

    /**
     * @param array $settings
     * @return \ExpressionEngine\Library\CP\GridInput
     */
    private function getServersGrid(array $settings)
    {
        $grid = ee('CP/GridInput', ['field_name' => 'servers', 'reorder' => false]);
        $grid->loadAssets();
        $grid->setColumns([
            'speedy_memcached_servers_host',
            'speedy_memcached_servers_port',
            'speedy_memcached_servers_weight',
        ]);
        $grid->setNoResultsText('speedy_memcached_servers_no_results', 'speedy_memcached_servers_add_btn');
        $grid->setBlankRow($this->getGridRow());

        $validation_data = ee()->input->post('servers');
        $servers = [];

        // If we're coming back on a validation error, load the Grid from
        // the POST data
        if (!empty($validation_data)) {
            foreach ($validation_data['rows'] as $row_id => $columns) {
                $servers[$row_id] = [
                    // Fix this, multiple new rows won't namespace right
                    'host'   => $columns['host'],
                    'port'   => $columns['port'],
                    'weight' => $columns['weight'],
                ];
            }

            if (isset($this->upload_errors['servers'])) {
                foreach ($this->upload_errors['servers'] as $row_id => $columns) {
                    $servers[$row_id]['errors'] = array_map('strip_tags', $columns);
                }
            }
        } elseif (isset($settings['servers']) && is_array($settings['servers'])) {
            $servers = $settings['servers'];
        }

        if (!empty($servers)) {
            $data = [];

            foreach ($servers as $i => $server) {
                $data[] = [
                    'attrs'   => ['row_id' => $i],
                    'columns' => $this->getGridRow($server),
                ];
            }

            $grid->setData($data);
        }

        return $grid;
    }

    /**
     * @param array $server
     * @return array
     */
    private function getGridRow($server = [])
    {
        $defaults = [
            'host'   => '',
            'port'   => '',
            'weight' => '',
        ];

        $server = array_merge($defaults, $server);
        $server = array_map('form_prep', $server);

        return [
            [
                'html'  => form_input('host', $server['host']),
                'error' => $this->getGridFieldError($server, 'host'),
            ],
            [
                'html'  => form_input('port', $server['port']),
                'error' => $this->getGridFieldError($server, 'port'),
            ],
            [
                'html'  => form_input('weight', $server['weight']),
                'error' => $this->getGridFieldError($server, 'weight'),
            ],
        ];
    }

    /**
     * @param array  $server
     * @param string $column
     * @return array|null
     */
    private function getGridFieldError($server, $column)
    {
        if (isset($server['errors'][$column])) {
            return $server['errors'][$column];
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
