<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;

/**
 * DataGrab cartthrob_order_items fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_cartthrob_order_items extends AbstractFieldType
{
    private $defaultValues = [
        'title' => null,
        'quantity' => 0,
        'price' => 0,
        'price_plus_tax' => 0,
        'weight' => 0,
        'shipping' => 0,
        'no_tax' => 0,
        'no_shipping' => 0,
        'extra' => null,
    ];

    private $settingNames = [
        'title',
        'quantity',
        'price',
        'price_plus_tax',
        'weight',
        'shipping',
        'no_tax',
        'no_shipping',
        'extra',
    ];

    public function register_setting(string $fieldName)
    {
        return [
            $fieldName => $this->settingNames,
        ];
    }

    public function display_configuration(
        Importer $importer,
        string $fieldName,
        string $fieldLabel,
        string $fieldType,
        bool $fieldRequired = false,
        array $data = []
    ): array
    {
        $config = [];
        $config['label'] = $this->displayLabel($fieldLabel, $fieldName, $fieldRequired, 'cartthrob');
        $fieldSettings = $data['field_settings'][$fieldName] ?? [];

        $fieldSets = ee('View')
            ->make('ee:_shared/form/section')
            ->render([
                'name' => 'fieldset_group',
                'settings' => $this->getFormFields(
                    $fieldName,
                    $fieldSettings,
                    $data,
                    $this->getSavedFieldValues($data, $fieldName),
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
    ): array
    {
        $fieldOptions[] = [
            'title' => 'Title',
            'desc' => '',
            'fields' => [
                $fieldName . '[title][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['title']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Price',
            'desc' => '',
            'fields' => [
                $fieldName . '[price][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['price']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Price Plus Tax',
            'desc' => '',
            'fields' => [
                $fieldName . '[price_plus_tax][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['price_plus_tax']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Weight',
            'desc' => '',
            'fields' => [
                $fieldName . '[weight][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['weight']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Shipping',
            'desc' => '',
            'fields' => [
                $fieldName . '[shipping][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['shipping']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'No shipping',
            'desc' => '',
            'fields' => [
                $fieldName . '[no_shipping][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['no_shipping']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'No Tax',
            'desc' => '',
            'fields' => [
                $fieldName . '[no_tax][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['no_tax']['value'] ?? '',
                ]
            ]
        ];

        $extraExample = '

<quantity>3</quantity>
<price>$100.00</price>
<extra><![CDATA[
  {
      "discount": 1,
      "price_plus_tax": "$20",
      "product_color": "Blue",
      "product_code": "WIDGET123"
  }
]]></extra>';

        $fieldOptions[] = [
            'title' => 'Extra',
            'desc' => 'Must be a valid JSON string. For example:</p><pre>' . htmlentities($extraExample) .'</pre></div>',
            'fields' => [
                $fieldName . '[extra][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['extra']['value'] ?? '',
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function hasFieldValue(array $settings = []): string
    {
        $values = array_filter(array_column($settings, 'value'));

        if (!empty($values)) {
            // Doesn't really matter which, so get the first
            return $settings[current($values)]['value'];
        }

        return '';
    }

    public function finalPostData(
        ImportField $importField
    ): array
    {
        // Initialise data
        $data['field_id_' . $importField->fieldId] = [];
        $first_row = 0;

        // Can the current datatype handle sub-loops (eg, XML)?
        if ($importField->importer->dataType->datatype_info['allow_subloop']) {
            // Check this field can be a sub-loop
            $count = $first_row;

            if ($importField->importer->dataType->initialise_sub_item()) {
                // Loop over sub items
                while ($subitem = $importField->importer->dataType->get_sub_item(
                    $importField->importItem,
                    $importField->propertyName,
                    $importField->importer->settings,
                    $importField->fieldName,
                )) {
                    $row = [
                        'entry_id' => $subitem
                    ];

                    $data['field_id_' . $importField->fieldId][$count++] = $row;
                }
            }

            foreach ($this->settingNames as $settingName) {
                $count = $first_row;
                $data['field_id_' . $importField->fieldId][$count][$settingName] = '';

                if ($importField->importer->dataType->initialise_sub_item()) {

                    $subitem = $importField->importer->dataType->get_sub_item(
                        $importField->importItem,
                        $settingName,
                        $importField->importer->settings,
                        $importField->fieldName,
                    );

                    $defaultValue = $this->defaultValues[$settingName] ?? null;

                    if (!$subitem && $defaultValue !== null) {
                        $data['field_id_' . $importField->fieldId][$count++][$settingName] = $defaultValue;
                    } else {
                        while ($subitem !== false) {
                            if ($settingName === 'extra') {
                                $extra = json_decode($subitem, true);

                                foreach ($extra as $extraKey => $extraValue) {
                                    $data['field_id_' . $importField->fieldId][$count][$extraKey] = $extraValue;
                                }
                            } else {
                                $data['field_id_' . $importField->fieldId][$count++][$settingName] = $subitem;
                            }

                            $subitem = $importField->importer->dataType->get_sub_item(
                                $importField->importItem,
                                $settingName,
                                $importField->importer->settings,
                                $importField->fieldName,
                            );
                        }
                    }
                }
            }
        }

        return $data;
    }

    public function rebuild_post_data(
        Importer $importer,
        int $fieldId = 0,
        array &$data = [],
        array $entryData = []
    ) {
        $entry = ee('Model')
            ->get('ChannelEntry')
            ->filter('entry_id', $entryData['entry_id'])
            ->first();

        if ($entry) {
            $fieldValues = $entry->getValues();
            $orderItemsFieldValue = $fieldValues['field_id_' . $fieldId] ?? '';

            if ($orderItemsFieldValue) {
                $data['field_id_' . $fieldId] = unserialize(base64_decode($orderItemsFieldValue));
            }
        }
    }
}
