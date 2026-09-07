<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;

/**
 * DataGrab Multiselect fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_multi_select extends AbstractFieldType
{
    protected string $fieldDescription = 'Multiple values must be comma or pipe delimited.';

    public function finalPostData(ImportField $importField)
    {
        $values = [];

        if ($importField->importer->dataType->initialise_sub_item()) {
            // Loop over sub items
            while ($subitem = $importField->importer->dataType->get_sub_item(
                $importField->importItem, $importField->propertyName, $importField->fieldSettings, $importField->fieldName)
            ) {
                $subitem = str_replace('|', ',', $subitem);

                foreach (explode(',', $subitem) as $item) {
                    $values[] = trim($item);
                }
            }

            return $values;
        }

        $subitem = $importField->importer->dataType->get_item($importField->importItem, $importField->fieldName);
        $subitem = str_replace('|', ',', $subitem);

        return array_map('trim', explode(',', $subitem));
    }
}
