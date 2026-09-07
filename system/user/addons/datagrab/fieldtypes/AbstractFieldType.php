<?php

namespace BoldMinded\DataGrab\FieldTypes;

use BoldMinded\DataGrab\Dependency\Litzinger\Basee\RequestCache;
use BoldMinded\DataGrab\Service\Importer;

/**
 * DataGrab Fieldtype Class
 *
 * Provides methods to interact with EE fieldtypes
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 **/

abstract class AbstractFieldType
{
    protected string $docUrl = '';
    protected string $fieldDescription ='';
    protected string $fieldPrefix = '';

    protected array $channelFields = [];

    public const HAS_SUB_ITEMS = false;

    public function __construct()
    {
        /** @var RequestCache $cache */
        $cache = ee('datagrab:Cache');
        $cacheResult = $cache->get('allChannelFields');

        if ($cacheResult) {
            $this->channelFields = $cacheResult;
        } else {
            $this->channelFields = ee('Model')
                ->get('ChannelField')
                ->all(true)
                ->getDictionary('field_name', 'field_id');

            $cache->set('allChannelFields', $this->channelFields);
        }
    }

    protected function displayLabel(
        string $label,
        string $name,
        bool $required = false,
        string $type = '',
    ): string
    {
        $displayName = $name ? sprintf(
            '<span class="app-badge label-app-badge" data-content_type="channel">
                <span class="txt-only">{%s}</span>
            </span>',
            $name
        ) : '';

        $required = $required ? 'fieldset-required' : '';
        $editUrl = '';

        // File imports will display additional Description, Credit, and Location fields, but they are
        // not real custom fields, just fudged in the interface to represent the File properties.
        if (isset($this->channelFields[$name])) {
            $editUrl = ee('CP/URL')->make(sprintf('fields/edit/%d', $this->channelFields[$name]))->compile();
        }

        $docs = $this->docUrl ? sprintf(
            '<em><a href="%s" target="_blank">Edit</a> | <a href="%s" target="_blank">Docs</a></em>',
            $editUrl,
            $this->docUrl,
        ) : sprintf('<em><a href="%s" target="_blank">Edit</a></em>', $editUrl);

        $iconImg = '';

        // Disabled, not sure how useful this is. Also seems to slow down the page load just a bit by
        // making numerous extra http requests.
        //$addon = ee('Addon')->get($type);
        //$iconUrl = $addon?->getIconUrl();
        //if ($iconUrl) {
        //    if (substr($iconUrl, -4) === '.svg') {
        //        $icon = file_get_contents($iconUrl);
        //    } else {
        //        $icon = sprintf('<img src="%s" alt="" width="50">', $iconUrl);
        //    }
        //
        //    $iconImg = '<div class="datagrab-addon-icon">'. $icon .'</div>';
        //}

        return sprintf('
            <div class="datagrab-field-wrapper %s">
                <div class="field-instruct datagrab-field-instruct">
                    <label>%s</label>
                    %s %s
                </div>
            </div>%s',
            $required,
            $label,
            $displayName,
            $docs,
            $iconImg,
        );
    }

    /**
     * Fetch a list of configuration settings that this field type can use
     *
     * @param string $fieldName the field name
     * @return array of configuration setting names
     */
    public function register_setting(string $fieldName)
    {
        return [
            $fieldName => [
                'value',
            ],
        ];
    }

    protected function getSavedFieldValues(array $data, string $fieldName): array
    {
        // If legacy field values (pre v6)
        if (
            isset($data['default_settings']['cf'][$fieldName])
            && is_string($data['default_settings']['cf'][$fieldName])
        ) {
            $savedFieldValues = [
                'value' => $data['default_settings']['cf'][$fieldName],
            ];
        } else {
            // If 'cf' does not exist then the import is new and has not been saved yet.
            $savedFieldValues = $data['default_settings']['cf'][$fieldName] ?? [];
        }

        return $savedFieldValues;
    }

    /**
     * Generate the form elements to configure this field.
     *
     * This is only called directly from mcp.datagrab.php for non-complex field types.
     * When display_configuration is called on complex field types such as Grid or Fluid
     * they will not call display_configuration again for each individual field type
     * contained in the complex type. Instead they negotiate the settings and values of
     * fields it contains and then calls getFormFields directly.
     *
     * @param Importer $importer         The DataGrab model object
     * @param string   $fieldName  the field's name
     * @param string   $fieldLabel the field's label
     * @param string   $fieldType  the field's type
     * @param bool     $fieldRequired
     * @param array    $data       array of data that can be used to select from
     * @return array containing form's label and elements
     */
    public function display_configuration(
        Importer $importer,
        string   $fieldName,
        string   $fieldLabel,
        string   $fieldType,
        bool     $fieldRequired = false,
        array    $data = []
    ): array {
        $type = $data['field_types'][$fieldName] ?? '';
        $config = [];
        $config['label'] = $this->displayLabel($fieldLabel, $fieldName, $fieldRequired, $type);
        $fieldSettings = $data['field_settings'][$fieldName] ?? [];
        $savedFieldValues = $this->getSavedFieldValues($data, $fieldName);

        $fieldSets = ee('View')
            ->make('ee:_shared/form/section')
            ->render([
                'name' => 'fieldset_group',
                'settings' => $this->getFormFields(
                    $fieldName,
                    $fieldSettings,
                    $data,
                    $savedFieldValues ?? [],
                )
            ]);

        $config['value'] = $fieldSets;

        return $config;
    }

    public function getFormFields(
        string $fieldName,
        array $fieldSettings,
        array $data = [],
        array $savedFieldValues = [],
        string $contentType = 'channel',
    ): array {
        $fieldOptions[] = [
            'title' => 'Import Value',
            'desc' => $this->fieldDescription,
            'fields' => [
                $fieldName . '[value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['value'] ?? '',
                ]
            ]
        ];

        return $fieldOptions;
    }

    /**
     * When saving an import config provide opportunity for the fieldtype eto modify its settings based on what
     * is being saved. For example, Grid fields with no import settings shouldn't save a "1" in the POST array,
     * which then triggers Datagrab_grid->final_post_data() clear out all Grid fields in the entry, even if no
     * the user isn't intending on importing any data into it.
     *
     * @param Importer $importer
     * @param string   $fieldName
     * @param array    $customFieldSettings
     * @return array
     */
    public function save_configuration(
        Importer $importer,
        string   $fieldName,
        array    $customFieldSettings = []
    ) {
        // If a field type needs to do something special, they'll need to overload this method.
        return $customFieldSettings;
    }

    protected function createFieldName(string $name): string
    {
        if (!$this->fieldPrefix) {
            return $name;
        }

        return sprintf('%s[%s]', $this->fieldPrefix, $name);
    }

    /**
     * Every field in the import config should save at minimum a 'value' setting which indicates
     * which source data field to use for the import. If it's blank, then it wasn't configured
     * as an importable field, so no data will be imported.
     */
    public function hasFieldValue(array $settings = []): string
    {
        return $settings['value'] ?? '';
    }
}
