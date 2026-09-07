<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;

/**
 * DataGrab Toggle fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_toggle extends AbstractFieldType
{
    public function finalPostData(ImportField $importField)
    {
        $value = $importField->importer->dataType->get_item($importField->importItem, $importField->propertyName);
        $returnValue = 0;

        if (in_array($value, ['y', 'yes', 'true', 1, '1', 'on'])) {
            $returnValue = 1;
        }

        return $returnValue;
    }
}
