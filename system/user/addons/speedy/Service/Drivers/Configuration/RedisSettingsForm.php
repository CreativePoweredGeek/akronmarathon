<?php

namespace BoldMinded\Speedy\Service\Drivers\Configuration;

use BoldMinded\Speedy\Model\DriverConfiguration;

class RedisSettingsForm implements SettingsFormInterface
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
        $serversGrid = $this->getServersGrid($settings);
        $ignoresGrid = $this->getIgnoresGrid($settings);

        ee()->load->helper('array');

        return [
            [
                [
                    'title'  => 'speedy_redis_static',
                    'desc' => 'speedy_redis_static_desc',
                    'fields' => [
                        'static' => [
                            'type'  => 'yes_no',
                            'value' => element('static', $settings, 'n'),
                        ],
                    ],
                ],
                [
                    'title'  => 'speedy_redis_servers',
                    'grid'   => true,
                    'wide'   => true,
                    'fields' => [
                        'servers' => [
                            'type'    => 'html',
                            'content' => ee()->load->view('_shared/table', $serversGrid->viewData(), true),
                        ],
                    ],
                ],
                [
                    'title'  => 'speedy_redis_ignores',
                    'desc'   => 'speedy_redis_ignores_desc',
                    'grid'   => true,
                    'wide'   => true,
                    'fields' => [
                        'ignore_urls' => [
                            'type'    => 'html',
                            'content' => ee()->load->view('_shared/table', $ignoresGrid->viewData(), true),
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
            'static' => ee()->input->post('static'),
        ];

        $result = $this->validateArray($settings, [
            'static' => 'enum[y,n]',
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

        $urls = ee()->input->post('ignore_urls');

        // Ignore URLs are optional for Redis
        if (is_array($urls) && isset($urls['rows']) && is_array($urls['rows'])) {
            foreach ($urls['rows'] as $row_id => $columns) {
                $settings['ignore_urls'][] = $columns;
            }
        }

        foreach ($validate as $row_id => $model) {
            if ($model['timeout'] === '') {
                $model['timeout'] = 0;
            }

            $result = $this->validateArray($model, [
                'host' => 'required',
                'port' => 'required',
                'timeout' => 'required',
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
        $grid = ee('CP/GridInput', ['field_name' => 'servers', 'grid_max_rows' => 1, 'reorder' => false]);
        $grid->loadAssets();
        $grid->setColumns([
            'speedy_redis_servers_host',
            'speedy_redis_servers_port',
            'speedy_redis_servers_timeout',
            'speedy_redis_servers_password',
            'speedy_redis_servers_database',
        ]);
        $grid->setNoResultsText('speedy_redis_servers_no_results', 'speedy_redis_servers_add_btn');
        $grid->setBlankRow($this->getServerGridRow());

        $validation_data = ee()->input->post('servers');
        $servers = [];

        // If we're coming back on a validation error, load the Grid from the POST data
        if (!empty($validation_data)) {
            foreach ($validation_data['rows'] as $row_id => $columns) {
                $servers[$row_id] = [
                    // Fix this, multiple new rows won't namespace right
                    'host' => $columns['host'],
                    'port' => $columns['port'],
                    'timeout' => $columns['timeout'],
                    'password' => $columns['password'],
                    'database' => $columns['database'],
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
                    'columns' => $this->getServerGridRow($server),
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
    private function getServerGridRow($server = [])
    {
        $defaults = [
            'host' => '',
            'port' => '',
            'timeout' => 0,
            'password' => '',
            'database' => 1,
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
                'html'  => form_input('timeout', $server['timeout']),
                'error' => $this->getGridFieldError($server, 'timeout'),
            ],
            [
                'html'  => form_input('password', $server['password']),
                'error' => $this->getGridFieldError($server, 'password'),
            ],
            [
                'html'  => form_input('database', $server['database']),
                'error' => $this->getGridFieldError($server, 'database'),
            ],
        ];
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

            foreach ($urls as $i => $server) {
                $data[] = [
                    'attrs'   => ['row_id' => $i],
                    'columns' => $this->getIgnoreGridRow($server),
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
