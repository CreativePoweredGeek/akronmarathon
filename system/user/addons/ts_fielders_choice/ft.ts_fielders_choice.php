<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

require_once __DIR__ . '/Service/bootstrap.php';

use TucsonSentinel\TsFieldersChoice\Service\CategoryChoiceLists;

/**
 * TS Fielder's Choice — category picker for Fluid (and channel) fields.
 *
 * Stores exp_channel_data.field_id_X as a single cat_id. Editors see names, not IDs.
 */
class Ts_fielders_choice_ft extends EE_Fieldtype
{
    public $info = [
        'name'    => 'TS Fielder\'s Choice',
        'version' => '1.0.2',
    ];

    public $has_array_data = false;

    public $can_be_cloned = true;

    public $supportedEvaluationRules = ['matches', 'notMatches', 'isEmpty', 'isNotEmpty'];

    public function install()
    {
        return [];
    }

    public function uninstall()
    {
        return true;
    }

    public function accepts_content_type($name)
    {
        return in_array($name, ['channel', 'fluid_field', 'grid'], true);
    }

    public function display_field($data)
    {
        return $this->_renderField($data);
    }

    public function grid_display_field($data)
    {
        return $this->_renderField($data);
    }

    /**
     * @param mixed $data
     */
    private function _renderField($data)
    {
        ee()->lang->loadfile('ts_fielders_choice');
        ts_fielders_choice_load_services();

        $value   = $this->_normalizeStoredValue($data);
        $choices = CategoryChoiceLists::choicesForSettings($this->settings, $value);

        if ($choices === []) {
            $groupIds = CategoryChoiceLists::categoryGroupIdsForSettings($this->settings);

            if ($groupIds === []) {
                return '<p class="meta-info">' . lang('fc_no_categories_config') . '</p>';
            }

            return '<p class="meta-info">' . lang('fc_no_categories_found') . '</p>';
        }

        if ($this->_useNativeSelect()) {
            return $this->_renderNativeSelect($choices, $value);
        }

        return ee('View')->make('ee:_shared/form/fields/dropdown')->render([
            'field_name'       => $this->field_name,
            'choices'          => $choices,
            'value'            => $value > 0 ? (string) $value : '',
            'empty_text'       => lang('fc_choose_category'),
            'field_disabled'   => (bool) $this->get_setting('field_disabled'),
            'ignoreSectionLabel' => true,
        ]);
    }

    /**
     * React CP dropdowns are only re-initialized for core fieldtype "select" on Fluid add/clone.
     *
     * @param array<int|string, string> $choices
     */
    private function _renderNativeSelect(array $choices, $value)
    {
        $extra = '';

        if ($this->get_setting('field_disabled')) {
            $extra = ' disabled="disabled"';
        }

        return form_dropdown(
            $this->field_name,
            ['' => lang('fc_choose_category')] + $choices,
            $value > 0 ? (string) $value : '',
            $extra
        );
    }

    /**
     * Use a plain select where EE does not hydrate data-dropdown-react (Fluid/Grid).
     */
    private function _useNativeSelect()
    {
        if (REQ !== 'CP') {
            return true;
        }

        if (in_array($this->content_type(), ['fluid_field', 'grid'], true)) {
            return true;
        }

        // Nested Fluid blocks still report content_type "channel" on FieldFacade.
        if (strpos((string) $this->field_name, '[fields]') !== false) {
            return true;
        }

        return ! empty($this->settings['fluid_field_data_id']);
    }

    public function validate($data)
    {
        ee()->lang->loadfile('ts_fielders_choice');
        ts_fielders_choice_load_services();

        $catId = $this->_normalizeStoredValue($data);

        if ($catId <= 0) {
            return true;
        }

        if (! CategoryChoiceLists::isAllowedCategoryId($this->settings, $catId)) {
            return lang('fc_invalid_selection');
        }

        return true;
    }

    /**
     * @param mixed $data
     */
    public function save($data)
    {
        $catId = $this->_normalizeStoredValue($data);

        return $catId > 0 ? (string) $catId : '';
    }

    public function display_settings($data)
    {
        ee()->lang->loadfile('ts_fielders_choice');
        ts_fielders_choice_load_services();

        $data = array_merge($this->_settingsDefaults(), $this->_extractSettingsFromData($data));

        $settings = [
            [
                'title' => 'fc_source_mode',
                'desc'  => 'fc_source_mode_desc',
                'fields' => [
                    'fc_source_mode' => [
                        'type'    => 'radio',
                        'choices' => [
                            'category_group' => lang('fc_mode_category_group'),
                            'channel'        => lang('fc_mode_channel'),
                        ],
                        'value'   => $data['fc_source_mode'],
                    ],
                ],
            ],
            [
                'title' => 'fc_category_group',
                'desc'  => 'fc_category_group_desc',
                'fields' => [
                    'fc_category_group_id' => [
                        'type'    => 'dropdown',
                        'choices' => CategoryChoiceLists::categoryGroupChoices(),
                        'value'   => $data['fc_category_group_id'],
                    ],
                ],
            ],
            [
                'title' => 'fc_channel',
                'desc'  => 'fc_channel_desc',
                'fields' => [
                    'fc_channel_id' => [
                        'type'    => 'dropdown',
                        'choices' => CategoryChoiceLists::channelChoices(),
                        'value'   => $data['fc_channel_id'],
                    ],
                ],
            ],
        ];

        $group = 'field_options_ts_fielders_choice';

        if ($this->content_type() === 'grid') {
            return ['field_options' => $settings];
        }

        return [
            $group => [
                'label'    => 'field_options',
                'group'    => 'ts_fielders_choice',
                'settings' => $settings,
            ],
        ];
    }

    /**
     * Persist field options into field_settings (ChannelField::set calls this).
     *
     * @param array<string, mixed> $data POST (+ existing values on edit)
     * @return array<string, mixed>
     */
    public function save_settings($data)
    {
        $settings = $this->_extractSettingsFromData($data);

        return array_merge($this->_settingsDefaults(), $settings);
    }

    public function validate_settings($data)
    {
        ee()->lang->loadfile('ts_fielders_choice');

        $settings = array_merge($this->_settingsDefaults(), $this->_extractSettingsFromData($data));
        $mode     = (string) $settings['fc_source_mode'];

        if ($mode === 'channel') {
            if ((int) $settings['fc_channel_id'] <= 0) {
                return lang('fc_settings_incomplete');
            }

            return true;
        }

        if ((int) $settings['fc_category_group_id'] <= 0) {
            return lang('fc_settings_incomplete');
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function _settingsDefaults()
    {
        return [
            'fc_source_mode'       => 'category_group',
            'fc_category_group_id' => '',
            'fc_channel_id'        => '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function _extractSettingsFromData(array $data)
    {
        $prefix = 'ts_fielders_choice_';
        $out    = [];

        foreach (array_keys($this->_settingsDefaults()) as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $data[$key];
            } elseif (array_key_exists($prefix . $key, $data)) {
                $out[$key] = $data[$prefix . $key];
            }
        }

        if (isset($out['fc_source_mode'])) {
            $out['fc_source_mode'] = ($out['fc_source_mode'] === 'channel') ? 'channel' : 'category_group';
        }

        if (isset($out['fc_category_group_id'])) {
            $out['fc_category_group_id'] = (string) (int) $out['fc_category_group_id'];
        }

        if (isset($out['fc_channel_id'])) {
            $out['fc_channel_id'] = (string) (int) $out['fc_channel_id'];
        }

        return $out;
    }

    /**
     * @param mixed $data Stored cat_id
     */
    public function replace_tag($data, $params = [], $tagdata = false)
    {
        ts_fielders_choice_load_services();

        $catId = $this->_normalizeStoredValue($data);

        if ($catId <= 0) {
            return '';
        }

        $format = isset($params['format']) ? (string) $params['format'] : 'id';

        switch ($format) {
            case 'name':
                $row = CategoryChoiceLists::categoryRow($catId);

                return $row ? $row['cat_name'] : '';

            case 'url_title':
                $row = CategoryChoiceLists::categoryRow($catId);

                return $row ? $row['cat_url_title'] : '';

            case 'group_id':
                $row = CategoryChoiceLists::categoryRow($catId);

                return $row ? (string) $row['group_id'] : '';

            case 'id':
            default:
                return (string) $catId;
        }
    }

    /**
     * @param mixed $data
     */
    private function _normalizeStoredValue($data)
    {
        if (is_array($data)) {
            $data = reset($data);
        }

        return (int) $data;
    }
}
