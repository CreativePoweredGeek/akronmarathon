<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;

/**
 * DataGrab Tag fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_tag extends AbstractFieldType
{
    protected string $docUrl = 'https://docs.boldminded.com/datagrab/docs/field-types/tag';

    protected string $fieldDescription = 'Multiple values must be comma or pipe delimited.';

    public function preparePostData(ImportField $importField)
    {
        // Can the current datatype handle sub-loops (eg, XML)?
        if ($importField->importer->dataType->initialise_sub_item()) {
            $values = [];

            // Loop over sub items
            while ($subitem = $importField->importer->dataType->get_sub_item(
                $importField->importItem,
                $importField->fieldImportConfig['value'],
                $importField->fieldSettings,
                $importField->fieldName
            )) {
                $subitem = str_replace('|', ',', $subitem);

                foreach (explode(',', $subitem) as $item) {
                    $values[] = trim($item);
                }
            }

            return implode("\n", $values);
        }

        $value = $importField->importer->dataType->get_item(
            $importField->importItem,
            $importField->fieldImportConfig['value'],
            $importField->fieldSettings,
            $importField->fieldName
        );

        return implode("\n", $value);
    }
}
