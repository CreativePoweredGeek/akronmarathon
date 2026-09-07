<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;
use Store\Model\Product;
use Store\Model\Stock;
use Store\Model\StockOption;

/**
 * DataGrab exp-resso Store fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_store extends AbstractFieldType
{
    public function register_setting(string $fieldName): array
    {
        return [
            $fieldName => [
                'price',
                'sku',
                'width',
                'height',
                'length',
                'weight',
                'handling_surcharge',
                'free_shipping',
                'stock_level',
                'stock_limit',
                'min_order_qty',
                'modifiers',
                'stock',
                'generate_skus',
            ],
        ];
    }

    /*
    Example expected data

    {
        "title": "Product #1",
        "price": "100.00",
        "length": "20",
        "width": "10",
        "height": "5",
        "weight": "2",
        "handling": "3.00",
        "free_shipping": "1",
        "modifiers": [
          {
            "type": "var",
            "name": "Small",
            "instructions": "Foobar",
            "options": [
              {
                "name": "cyan",
                "price": "-11.00"
              },
              {
                "name": "magenta",
                "price": "-21.00"
              }
            ]
          },
          {
            "type": "var",
            "name": "Medium",
            "instructions": "Fizzbazz",
            "options": [
              {
                "name": "yellow",
                "price": "+5.00"
              },
              {
                "name": "black",
                "price": "+10.00"
              }
            ]
          }
        ],
        "stock": [
          {
            "sku": "cyan-yellow",
            "track_stock": "0",
            "stock_level": "30",
            "min_order_qty": "1"
          },
          {
            "sku": "cyan-black",
            "track_stock": "0",
            "stock_level": "20",
            "min_order_qty": "2"
          },
          {
            "sku": "magenta-yellow",
            "track_stock": "0",
            "stock_level": "10",
            "min_order_qty": "3"
          },
          {
            "sku": "magenta-black",
            "track_stock": "0",
            "stock_level": "5",
            "min_order_qty": "4"
          }
        ]
      }

     */

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
        $config['label'] = $this->displayLabel($fieldLabel, $fieldName, $fieldRequired, 'store');
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
            'title' => 'Width',
            'desc' => '',
            'fields' => [
                $fieldName . '[width][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['width']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Length',
            'desc' => '',
            'fields' => [
                $fieldName . '[length][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['length']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Height',
            'desc' => '',
            'fields' => [
                $fieldName . '[height][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['height']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Handling surcharge',
            'desc' => '',
            'fields' => [
                $fieldName . '[handling_surcharge][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['handling_surcharge']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Free shipping',
            'desc' => '',
            'fields' => [
                $fieldName . '[free_shipping][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['free_shipping']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Modifiers',
            'desc' => '',
            'fields' => [
                $fieldName . '[modifiers][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['modifiers']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Stock',
            'desc' => '',
            'fields' => [
                $fieldName . '[stock][value]' => [
                    'type' => 'dropdown',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['stock']['value'] ?? '',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Auto-Generate SKUs',
            'desc' => 'If no stock is defined should DataGrab auto-generate SKUs based on modifier option names?',
            'fields' => [
                $fieldName . '[generate_skus][value]' => [
                    'type' => 'toggle',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['generate_skus']['value'] ?? 'n',
                ]
            ]
        ];

        $fieldOptions[] = [
            'title' => 'Reset Stock',
            'desc' => 'Should DataGrab reset stock if updating an existing product? This will delete all 
                information from the Stock table on the product and reset it to the given values in the import file.
                If you want to update product data, but keep existing stock levels, leave this disabled.',
            'fields' => [
                $fieldName . '[reset_stock][value]' => [
                    'type' => 'toggle',
                    'choices' => $data['data_fields'],
                    'value' => $savedFieldValues['reset_stock']['value'] ?? 'n',
                ]
            ]
        ];

        return $fieldOptions;
    }

    public function hasFieldValue(array $settings = []): string
    {
        foreach ($settings as $setting) {
            if (isset($setting['value']) && $setting['value'] !== '') {
                // Doesn't really matter which, so get the first
                return $setting['value'];
            }
        }

        return '';
    }


    /**
     * Build stock rows from modifiers, mirroring the JS new_stock_options logic from Store's core files.
     * The only way Store saves the store_stock_options table is from the EE publish page, b/c the JS
     * uses the form values and adds a bunch of hidden inputs to the page. This method simulates those
     * hidden inputs so we can call save_product_stock to accurately save a product as if it was saved
     * from within EE's publish page.
     */
    private function buildStockFromModifiers(
        array $modifiers,
    ): array
    {
        // Step 1: build the Cartesian product of options, but store mod + opt info.
        $newStockOptions = [[]]; // like JS: start with one empty row

        foreach ($modifiers as $modifier) {
            $previousStockOptions = $newStockOptions;
            $newStockOptions = [];

            foreach ($previousStockOptions as $previousRow) {
                foreach ($modifier['options'] as $option) {
                    // Add a new "dimension" containing mod + opt IDs + name
                    $newRow = $previousRow;
                    $newRow[] = [
                        'product_mod_id' => $modifier['product_mod_id'],
                        'product_opt_id' => $option['product_opt_id'],
                        'opt_name' => $option['opt_name'],
                    ];

                    $newStockOptions[] = $newRow;
                }
            }
        }

        $importedStock = $_POST['store_product_field']['stock'] ?? [];
        foreach ($importedStock as $stock) {
            $reIndexedCurrentStock[$stock['sku']] = $stock;
        }

        // Step 2: convert each combination into a stock row.
        $stockRows = [];

        foreach ($newStockOptions as $combo) {
            // SKU from opt_name, e.g. "cyan-yellow-black"
            $skuParts = array_column($combo, 'opt_name');
            $stockSku = implode('-', $skuParts);

            // stock_options: strip down to mod/opt IDs
            $stockOptions = array_map(static function (array $item): array {
                return [
                    'product_mod_id' => $item['product_mod_id'],
                    'product_opt_id' => $item['product_opt_id'],
                ];
            }, $combo);

            $currentStockItem = $reIndexedCurrentStock[$stockSku] ?? [];

            $stockRows[] = [
                'sku' => $stockSku,
                'track_stock' => $currentStockItem['track_stock'] ?? 0,
                'stock_level' => $currentStockItem['stock_level'] ?? 0,
                'min_order_qty' => $currentStockItem['min_order_qty'] ?? 1,
                'stock_options' => $stockOptions,
            ];
        }

        return $stockRows;
    }

    public function postProcessEntry(
        ImportField $importField,
        array $data
    ): array
    {
        $product = Product::with([
            'modifiers' => function ($query) {
                $query->orderBy('mod_order');
            },
            'modifiers.options' => function ($query) {
                $query->orderBy('opt_order');
            },
            'stock',
            'stock.stockOptions',
        ])->find($importField->entryId);

        if(!$product) {
            return $data;
        }

        // Jump through some hoops to construct the attributes in a way that the save_product_stock method expects
        $modifiersArray = $product->modifiers->toArray();
        $modifiers = [];
        foreach ($modifiersArray as $modifier) {
            $modifiers[$modifier['product_mod_id']] = $modifier;

            foreach ($modifier['options'] as $index => $option) {
                unset($modifier['options'][$index]);
                $modifiers[$modifier['product_mod_id']]['options'][$option['product_opt_id']]['product_opt_id'] = $option['product_opt_id'];
            }
        }

        $product->update_stock = $this->buildStockFromModifiers($modifiersArray);
        $product->modifiers = $modifiers;

        ee()->store->products->save_product_stock($product);

        return $data;
    }

    public function finalPostData(
        ImportField $importField
    ): array
    {
        $config = $importField->fieldImportConfig;

        $price = $config['price']['value'] ?? '';
        $weight = $config['weight']['value'] ?? '';
        $width = $config['width']['value'] ?? '';
        $length = $config['length']['value'] ?? '';
        $height = $config['height']['value'] ?? '';
        $handlingSurcharge = $config['handling_surcharge']['value'] ?? '';
        $modifiers = $config['modifiers']['value'] ?? '';
        $stock = $config['stock']['value'] ?? '';
        $resetStock = get_bool_from_string($config['reset_stock']['value'] ?? '');
        $generateSkus = get_bool_from_string($config['generate_skus']['value'] ?? '');
        $freeShipping = $config['free_shipping']['value'] ?? '';

        $data = [];

        if ($price) {
            $data['price'] = preg_replace(
                '/([^0-9\\.])/i',
                '',
                $importField->importer->dataType->get_item($importField->importItem, $price)
            );
        }

        if ($width) {
            $data['width'] =
                $this->toNumber($importField->importer->dataType->get_item($importField->importItem, $width));
        }

        if ($height) {
            $data['height'] =
                $this->toNumber($importField->importer->dataType->get_item($importField->importItem, $height));
        }

        if ($length) {
            $data['length'] =
                $this->toNumber($importField->importer->dataType->get_item($importField->importItem, $length));
        }

        if ($weight) {
            $data['weight'] =
                $this->toNumber($importField->importer->dataType->get_item($importField->importItem, $weight));
        }

        if ($handlingSurcharge) {
            $data['handling'] =
                $this->toNumber($importField->importer->dataType->get_item($importField->importItem, $handlingSurcharge));
        }

        if ($freeShipping) {
            $data['free_shipping'] =
                $this->toNumber($importField->importer->dataType->get_item($importField->importItem, $freeShipping));
        }

        $alreadyUpdated = !in_array($importField->entryId, $importField->importer->entries);

        if (
            $importField->entryId
            && $alreadyUpdated
            && $resetStock
        ) {
            ee()->db->where('entry_id', $importField->entryId);
            ee()->db->delete('exp_store_stock');
        }

        ee()->db->select('*');
        ee()->db->where('entry_id', $importField->entryId);
        $query = ee()->db->get('exp_store_stock');

        // Capture existing stock data. If Reset Stock is enabled it'll get updated below.
        $count = 0;
        if ($query->num_rows()) {
            foreach ($query->result_array() as $row) {
                $data['stock'][$count]['id'] = $row['id'];
                $data['stock'][$count]['sku'] = $row['sku'];
                $data['stock'][$count]['stock_level'] = $row['stock_level'];
                $data['stock'][$count]['min_order_qty'] = $row['min_order_qty'];
                $data['stock'][$count]['track_stock'] = $row['track_stock'];
                $count++;
            }
        }

        // Set up array to store modifiers
        $modOrder = 0;

        if ($modifiers) {
            // Initialise loop over modifiers
            if ($importField->importer->dataType->initialise_sub_item()) {

                $modifiersItemPath = implode('/', array_slice(explode('/', $modifiers), 0, -1));

                // Loop over sub items
                while ($modifierSubItem = $importField->importer->dataType->get_sub_item(
                    $importField->importItem,
                    $modifiersItemPath,
                    $importField->importer->settings,
                    $importField->fieldName,
                )) {
                    $modifierOptionId = $importField->importer->dataType->get_item(
                        $modifierSubItem,
                        'id'
                    ) ?: $modOrder;

                    $modifierOrder = $importField->importer->dataType->get_item(
                        $modifierSubItem,
                        'order'
                    ) ?: $modOrder;

                    $modifierOrder = (int) $modifierOrder;

                    $modifierOptionType = $importField->importer->dataType->get_item(
                        $modifierSubItem,
                        'type'
                    );

                    // Get modifier's name/title
                    $modifierOptionName = $importField->importer->dataType->get_item(
                        $modifierSubItem,
                        'name'
                    );

                    $modifierOptionInstructions = $importField->importer->dataType->get_item(
                        $modifierSubItem,
                        'instructions'
                    );

                    $modifier = [
                        'mod_order' => $modifierOrder,
                        'mod_type' => $modifierOptionType ?? 'var', // default for now
                        'mod_name' => $modifierOptionName,
                        'mod_instructions' => $modifierOptionInstructions
                    ];

                    $existingModifier = ee('db')
                        ->from('store_products AS p')
                        ->join('store_product_modifiers AS m', 'p.entry_id = m.entry_id')
                        ->where([
                            'p.entry_id' => $importField->entryId,
                            'm.mod_type' => $modifierOptionType,
                            'm.mod_name' => $modifierOptionName,
                        ])
                        ->get();

                    // Assume modifier name and type is unique. Store does not seem to enforce this,
                    // but I can't imagine how it would work otherwise.
                    if ($existingModifier->num_rows() === 1) {
                        $modifier['product_mod_id'] = $existingModifier->row('product_mod_id');
                    }

                    $modOrder++;

                    $modifierOptionsCollection = [];

                    // Capture parent loop location
                    $parentSubItemPtr = $importField->importer->dataType->sub_item_ptr;

                    // Set up and loop over options
                    $importField->importer->dataType->initialise_sub_item();

                    while ($modifierOption = $importField->importer->dataType->get_sub_item(
                        $modifierSubItem,
                        'options',
                        $importField->importer->settings,
                        $importField->fieldName,
                    )) {
                        $optionOrder = $importField->importer->dataType->get_item(
                            $modifierOption,
                            'order'
                        );

                        $optionName = $importField->importer->dataType->get_item(
                            $modifierOption,
                            'name'
                        );

                        $optionPriceMod = $importField->importer->dataType->get_item(
                            $modifierOption,
                            'price'
                        );

                        $modifierOptions = [
                            'opt_order' => $optionOrder ?: $modOrder++,
                            'opt_name' => $optionName,
                            'opt_price_mod' => $optionPriceMod,
                        ];

                        if (isset($modifier['product_mod_id'])) {
                            // Changing the name of the option will result in a new option added instead of updating
                            // the existing. This will effectively allow updating of the price modifier on repeat
                            // imports of the same data.
                            $existingOption = ee('db')
                                ->from('store_product_modifiers AS m')
                                ->join('store_product_options AS o', 'm.product_mod_id = o.product_mod_id')
                                ->where([
                                    'o.product_mod_id' => $modifier['product_mod_id'],
                                    'o.opt_name' => $optionName,
                                ])
                                ->get();

                            if ($existingOption->num_rows() === 1) {
                                $modifierOptions['product_opt_id'] = $existingOption->row('product_opt_id');
                            }
                        }

                        $modifierOptionsCollection[] = $modifierOptions;
                    }

                    $modifier['options'] = $modifierOptionsCollection;

                    // Reset parent loop location
                    $importField->importer->dataType->sub_item_ptr = $parentSubItemPtr;

                    $modifiersCollection[$modifierOptionId] = $modifier;
                }

                if (!empty($modifiersCollection)) {
                    $data['modifiers'] = $modifiersCollection;

                    $stockCollection = [];

                    $stockItemPath = implode('/', array_slice(explode('/', $stock), 0, -1));

                    $importField->importer->dataType->initialise_sub_item();

                    while ($stockSubItem = $importField->importer->dataType->get_sub_item(
                        $importField->importItem,
                        $stockItemPath,
                        $importField->importer->settings,
                        $importField->fieldName,
                    )) {
                        $stockSku = $importField->importer->dataType->get_item(
                            $stockSubItem,
                            'sku'
                        );

                        $stockTrack = $importField->importer->dataType->get_item(
                            $stockSubItem,
                            'track_stock'
                        );

                        $stockLevel = $importField->importer->dataType->get_item(
                            $stockSubItem,
                            'stock_level'
                        );

                        $stockMinQty = $importField->importer->dataType->get_item(
                            $stockSubItem,
                            'min_order_qty'
                        );

                        $stockCollection[] = [
                            'sku' => $stockSku,
                            'track_stock' => $stockTrack,
                            'stock_level' => $stockLevel,
                            'min_order_qty' => $stockMinQty,
                        ];
                    }

                    if (
                        empty($stockCollection)
                        && $generateSkus
                    ) {
                        $generatedSkus = $this->generateSkuCombinations($modifiersCollection);

                        foreach ($generatedSkus as $generatedSku) {
                            $stockCollection[] = [
                                'sku' => $generatedSku,
                                'track_stock' => 0,
                                'stock_level' => 0,
                                'min_order_qty' => 0,
                            ];
                        }
                    }

                    if (
                        (
                            $importField->entryId
                            && $resetStock
                            && $alreadyUpdated
                        )
                        || !$importField->entryId
                    ) {
                        $data['stock'] = $stockCollection;
                    }
                }
            }
        }

        // What Store really needs to save the data b/c ft.store.php is looking at the POST array.
        $_POST['store_product_field'] = $data;

        return $data;
    }

    function generateSkuCombinations(
        array $modifiers = []
    ): array
    {
        // Extract only opt_name lists by mod group
        $groups = array_map(function ($mod) {
            return array_column($mod['options'], 'opt_name');
        }, $modifiers);

        // Start with an empty product
        $result = [[]];

        // Build the Cartesian product
        foreach ($groups as $group) {
            $new = [];

            foreach ($result as $combo) {
                foreach ($group as $opt) {
                    $new[] = array_merge($combo, [$opt]);
                }
            }

            $result = $new;
        }

        // Convert each combination array into a SKU string
        return array_map(fn($parts) => strtolower(implode('-', $parts)), $result);
    }

    private function toNumber($number)
    {
        return preg_replace('/([^0-9\\.])/i', '', $number);
    }

    public function rebuild_post_data(
        Importer $importer,
        int $fieldId = 0,
        array &$data = [],
        array $entryData = []
    ) {
        // Version 2
        $data['field_id_' . $fieldId] = 'store';
        $_POST['store_product_field'] = array(
            'price' => '',
            'stock' => array(
                array(
                    'sku' => '',
                    'min_order_qty' => ''
                )
            ),
            'weight' => '',
            'length' => '',
            'width' => '',
            'height' => '',
            'handling' => '',
            'free_shipping' => ''
        );

        ee()->db->from('exp_store_products');
        ee()->db->join('exp_store_stock', 'exp_store_products.entry_id = exp_store_stock.entry_id');
        ee()->db->where('exp_store_products.entry_id', $entryData['entry_id']);

        $query = ee()->db->get();

        if ($query->num_rows() > 0) {
            $row = $query->row_array();

            $_POST['store_product_field'] = array(
                'price' => '',
                'stock' => array(
                    array(
                        'sku' => '',
                        'min_order_qty' => ''
                    )
                ),
                'weight' => '',
                'length' => '',
                'width' => '',
                'height' => '',
                'handling' => '',
                'free_shipping' => ''
            );

            $_POST['store_product_field'] = array(
                'price' => $row['price'],
                'stock' => array(
                    array(
                        'sku' => $row['sku'],
                        'min_order_qty' => $row['min_order_qty'],
                        'track_stock' => $row['track_stock'],
                        'stock_level' => $row['stock_level']
                    )
                ),
                'weight' => $row['weight'],
                'length' => $row['length'],
                'width' => $row['width'],
                'height' => $row['height'],
                'handling' => $row['handling'],
                'free_shipping' => $row['free_shipping']
            );

        }
    }
}
