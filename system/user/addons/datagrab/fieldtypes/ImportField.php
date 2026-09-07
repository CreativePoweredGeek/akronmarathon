<?php

namespace BoldMinded\DataGrab\FieldTypes;

use BoldMinded\DataGrab\Service\Importer;

class ImportField
{
    public function __construct(
        public Importer $importer,
        public array    $importItem = [],
        public string   $propertyName = '',
        public string   $propertyValue = '',
        public array    $fieldImportConfig = [], // The config values saved in the import for the field
        public array    $fieldSettings = [], // The atom, column, or field settings
        public int      $entryId = 0,
        public string   $fieldName = '',
        public int      $fieldId = 0,
        public string   $contentType = 'channel',
    ) {
    }
}
