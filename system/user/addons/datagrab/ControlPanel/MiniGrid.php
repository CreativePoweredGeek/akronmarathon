<?php


namespace BoldMinded\DataGrab\ControlPanel;

use ExpressionEngine\Library\CP\GridInput;

class MiniGrid
{
    public function makeAuthSettingsGrid(array $settings = [], array|null $form_errors = null): GridInput
    {
        /** @var \ExpressionEngine\Library\CP\GridInput $grid */
        $grid = ee('CP/GridInput', [
            'field_name'    => 'auth_grid',
            'reorder'       => false,
            // 'grid_max_rows' => 3,
        ]);
        $grid->loadAssets();
        $grid->setColumns(['dg_ep_auth_name', 'dg_ep_auth_value']);
        $grid->setNoResultsText('datagrab_no_endpoints', 'datagrab_add_endpoint');
        $grid->setBlankRow($this->makeGridRow());

        $grid_data = [];
        $validationData = ee('Request')->post('auth_grid');

        $rows = $settings['auth_grid']['rows'] ?? [];

        if (!empty($validationData)) {
            foreach ($validationData['rows'] as $row_id => $columns) {
                $row_errors = [];
                if (isset($form_errors['auth_grid'][$row_id])) {
                    $row_errors = array_map('strip_items', $form_errors['auth_grid'][$row_id]);
                }

                $grid_data[$row_id] = [
                    'attrs'   => ['row_id' => str_replace('row_id_', '', $row_id)],
                    'columns' => $this->makeGridRow([
                        'auth_name' => $columns['auth_name'],
                        'auth_value' => $columns['auth_value'],
                    ], $row_errors),
                ];
            }
        } elseif (count($rows) > 0) {
            foreach ($rows as $index => $row) {
                $grid_data[] = [
                    'attrs'   => ['row_id' => $index],
                    'columns' => $this->makeGridRow([
                        'auth_name' => $row['auth_name'],
                        'auth_value' => $row['auth_value'],
                    ]),
                ];
            }
        }

        if (count($grid_data)) {
            $grid->setData($grid_data);
        }

        return $grid;
    }

    private function makeGridRow(array $values = [], array $row_errors = []): array
    {
        return [
            'auth_name' => [
                'html'  => form_input('auth_name', form_prep($values['auth_name'] ?? ''), 'spellcheck="false"'),
                'error' => isset($row_errors['auth_name']) ? $row_errors['auth_name'] : null,
            ],
            'auth_value' => [
                'html'  => form_input('auth_value', form_prep($values['auth_value'] ?? ''), 'spellcheck="false"'),
                'error' => isset($row_errors['auth_value']) ? $row_errors['auth_value'] : null,
            ],
        ];
    }

    public function cleanRowData(array $settings = []): array
    {
        $cleaned = [];

        if (isset($settings['rows'])) {
            $rows = $settings['rows'];
            $newRows = [];
            $maxRowId = $this->findMaxColumnId($rows);

            foreach ($rows as $id => $column) {
                if (substr($id,  0, 8) === 'new_row_') {
                    $maxRowId++;
                    $numericId = $maxRowId;
                } else {
                    $numericId = (int) str_replace('row_id_', '', $id);
                }

                $newRows[$numericId] = $column;
            }

            $cleaned['rows'] = $newRows;
        }

        return $cleaned;
    }

    /**
     * Since we aren't using an auto-incrementing table to save our columns we need to mimic such behavior.
     */
    private function findMaxColumnId(array $columns = []): int
    {
        $max = 0;

        foreach ($columns as $id => $column) {
            if (substr($id,  0, 8) === 'new_row_') {
                continue;
            }
            $numericId = (int) str_replace('row_id_', '', $id);
            if ($numericId > $max) {
                $max = $numericId;
            }
        }

        return $max;
    }
}
