<?php

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use BoldMinded\DataGrab\FieldTypes\ImportField;
use BoldMinded\DataGrab\Service\Importer;

/**
 * DataGrab Assets fieldtype class
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_assets extends AbstractFieldType
{
    public function finalPostData(ImportField $importField)
    {
        $files = [];

        if ($importField->importer->dataType->initialise_sub_item()) {
            while ($subitem = $importField->importer->dataType->get_sub_item(
                $importField->importItem,
                $importField->propertyName,
                $importField->importer->settings,
                $importField->fieldName,
            )) {
                if (preg_match('/{filedir_([0-9]+)}/', $subitem, $matches)) {
                    $file = [
                        'filedir' => $matches[1],
                        'filename' => str_replace($matches[0], '', $subitem)
                    ];

                    ee()->db->select('file_id');
                    ee()->db->where('file_name', $file['filename']);
                    ee()->db->where('filedir_id', $file['filedir']);

                    $query = ee()->db->get('exp_assets_files');

                    if ($query->num_rows() > 0) {
                        $row = $query->row_array();
                        $files[] = $row['file_id'];
                    }
                } else {
                    ee()->db->select('file_id');
                    ee()->db->where('file_name', $subitem);
                    $query = ee()->db->get('exp_assets_files');

                    if ($query->num_rows() > 0) {
                        $row = $query->row_array();
                        $files[] = $row['file_id'];
                    }
                }
            }
        }

        return $files;
    }

    public function rebuildPostData(
        Importer $importer,
        int      $fieldId = 0,
        array    $existingData = []
    ) {
        $returnData = [];

        $where = [
            'entry_id' => $existingData['entry_id'],
            'field_id' => $fieldId
        ];

        // -------------------------------------------
        //  'datagrab_rebuild_assets_query' hook
        //
        if ($importer->extensions->active_hook('datagrab_rebuild_assets_query')) {
            $importer->logger->log('Calling datagrab_rebuild_assets_query() hook.');
            $query = $importer->extensions->call('datagrab_rebuild_assets_query', $where);
        } else {
            ee()->db->select('file_id');
            ee()->db->from('exp_assets_selections');
            ee()->db->where($where);
            ee()->db->order_by('sort_order');
            $query = ee()->db->get();
        }
        //
        // -------------------------------------------

        foreach ($query->result_array() as $row) {
            $returnData[] = $row['file_id'];
        }

        return $returnData;
    }
}
