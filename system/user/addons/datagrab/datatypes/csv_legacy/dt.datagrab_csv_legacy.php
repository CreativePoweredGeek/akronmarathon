<?php

use BoldMinded\DataGrab\DataTypes\AbstractDataTypeLegacy;

/**
 * DataGrab CSV import class
 *
 * Allows CSV imports
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_csv_legacy extends AbstractDataTypeLegacy
{
    public $type = 'CSV';

    public $datatype_info = [
        'name' => 'CSV (Legacy)',
        'version' => '1.0',
        'description' => 'Import data from a CSV file',
        'allow_subloop' => true,
        'allow_multiple_fields' => true
    ];

    public $settings = [
        'filename' => '',
        'delimiter' => '',
        'encloser' => '',
        'skip' => 0,
    ];

    public $sub_item_ptr;

    private $iterator = 0;

    /**
     * @param array $values
     * @return array[]
     */
    public function settings_form(array $values = []): array
    {
        return [
            [
                'title' => 'Filename or URL',
                'desc' => lang('datagrab_filename_instructions'),
                'fields' => [
                    'filename' => [
                        'required' => true,
                        'type' => 'text',
                        'value' => $this->get_value($values, 'filename') ?: '{base_url}/my-file.csv',
                    ]
                ]
            ],
            [
                'title' => 'Delimiter',
                'desc' => 'The character used to separate fields in the file.',
                'fields' => [
                    'delimiter' => [
                        'required' => true,
                        'type' => 'select',
                        'choices' => [
                            ',' => ',',
                            '|' => '|',
                            ';' => ';',
                            'SPACE' => 'Space',
                            'TAB' => 'Tab',
                        ],
                        'value' => $this->get_value($values, 'delimiter') ?: ',',
                    ]
                ]
            ],
            [
                'title' => 'Encloser',
                'desc' => 'If in doubt, or if the data has no encloser, use the default "',
                'fields' => [
                    'encloser' => [
                        'type' => 'text',
                        'value' => $this->get_value($values, 'encloser') ?: '',
                    ]
                ]
            ],
            [
                'title' => 'Use first row as titles',
                'desc' => 'Select this if the first row of the file contains titles and should not be imported.',
                'fields' => [
                    'skip' => [
                        'required' => true,
                        'type' => 'toggle',
                        'value' => $this->get_value($values, 'skip') ?: 1,
                    ]
                ]
            ]
        ];
    }

    public function getItems(): array
    {
        return $this->getCsv($this->handle);
    }

    public function fetch(string $data = '')
    {
        if (!$this->getFilename()) {
            $this->addError('You must supply a filename/url.');
            return -1;
        }

        // Open CSV file and save handle
        try {
            // Not sure this ini_set is needed anymore, but keep it for backwards compatiblity
            if (defined('PHP_MAJOR_VERSION') && PHP_MAJOR_VERSION < 8) {
                ini_set('auto_detect_line_endings', true);
            }

            $verifyPeer = bool_config_item('datagrab_verify_peer');

            $this->handle = fopen($this->getFilename(), 'r', false, stream_context_create([
                'ssl' => [
                    'verify_peer' => $verifyPeer,
                    'verify_peer_name' => $verifyPeer,
                ],
            ]));
        } catch (Exception $exception) {
            return -1;
        }

        if ($this->handle === false) {
            $this->addError('Cannot open the file/url: ' . $this->getFilename());
            return -1;
        }

        return $this->handle;
    }

    public function total_rows_real()
    {
        $rowCount=0;

        $verifyPeer = bool_config_item('datagrab_verify_peer');

        if (($fp = fopen($this->getFilename(), 'r', false, stream_context_create([
                'ssl' => [
                    'verify_peer' => $verifyPeer,
                    'verify_peer_name' => $verifyPeer,
                ],
            ]))) !== false
        ) {
            while (!feof($fp)) {
                $data = $this->getCsv($fp);

                if (empty($data) || $this->shouldSkip()) {
                    continue; //empty row
                }

                $rowCount++;
            }

            fclose($fp);
        }

        $this->iterator = 0;

        return $rowCount;
    }

    private function getDelimiter(): string
    {
        $delimiter = $this->settings['delimiter'] ?? ',';

        if ($delimiter === 'TAB' || $delimiter === '\t') {
            return "\t";
        }

        if ($delimiter === 'SPACE') {
            return " ";
        }

        return $delimiter;
    }

    private function getEncloser()
    {
        return $this->settings['encloser'] ?? null;
    }

    private function getCsv($handle)
    {
        $encloser = $this->getEncloser();
        $delimiter = $this->getDelimiter();

        try {
            if (!$encloser) {
                return fgetcsv($handle, 0, $delimiter);
            }

            return fgetcsv($handle, 0, $delimiter, $encloser);
        } catch (\Error $error) {
            $this->addError($error->getMessage());
        }
    }

    private function shouldSkip(): bool
    {
        // When configuring an import don't skip any rows.
        if ($this->isConfigMode) {
            return false;
        }

        $this->iterator++;
        $skip = $this->settings['skip'] ?? 0;

        if ($skip > 0 && $this->iterator <= $skip) {
            return true;
        }

        return false;
    }

    public function next()
    {
        // Get next line of CSV file
        $item = $this->getCsv($this->handle);

        if ($this->shouldSkip()) {
            return true;
        }

        // Bug in fgetcsv, if the first character of a field is a special character it goes missing
        // $line = fgets($this->handle, 10000);
        // $item = $this->_csvstring_to_array($line, $this->settings["delimiter"], $this->settings["encloser"]);

        // Make sure empty rows are not used
        if (is_array($item) && count($item) == 1 && empty($item[0])) {
            return false;
        }

        return $item;
    }

    public function fetch_columns(): array
    {
        try {
            $this->fetch();
            $columns = $this->next();

            // Loop through fields, adding Column # and truncating any long labels
            $titles = array();
            $count = 0;

            if (is_array($columns)) {
                foreach ($columns as $title) {
                    if (strlen($title) > 32) {
                        $title = substr($title, 0, 32) . "...";
                    }
                    $titles[] = "Column " . ++$count . " - eg, " . $title;
                }
            }

            return $titles;

        } catch (Error $error) {
            $this->addError($error->getMessage());
        }

        return [];
    }

    public function initialise_sub_item()
    {
        if ($this->datatype_info['allow_subloop'] !== true) {
            return false;
        }

        $this->sub_item_ptr = 0;
        return true;
    }

    public function get_item($items, $id): string
    {
        if (isset($items[$id])) {
            return trim(stripcslashes($items[$id]));
        }

        return '';
    }

    public function get_sub_item(array $item, string $key, array $config = [], string $field = '', array $column = [])
    {
        // Find delimiter (if set)
        $delimiter = ",";
        if (isset($config["cf"][$field . "_delimiter"])) {
            $delimiter = $config["cf"][$field . "_delimiter"];
        }

        // Find item and split into sub items
        $item = $this->get_item($item, $key);

        // Added for https://boldminded.com/support/ticket/2686
        $ignoreSubItems = ['text', 'textarea', 'rte', 'wygwam'];

        if (array_key_exists('col_type', $column) && in_array($column['col_type'], $ignoreSubItems)) {
            $subItems = [$item];
        } else {
            $subItems = $this->_csvstring_to_array($item, $delimiter, "'");
        }

        $noElements = count($subItems);

        // Return false if there are no items to return
        $this->sub_item_ptr++;
        if (!$noElements || $this->sub_item_ptr > $noElements) {
            return false;
        }

        // Return sub item
        return trim($subItems[$this->sub_item_ptr - 1]);
    }

    private function _csvstring_to_array($data, $delimiter = ',', $enclosure = '"', $newline = "\n")
    {
        // CDATA is an XML thing, but borrow it so users can import multi-line text blocks in CSV files too.
        // This is a fallback catchall if the col_type does not match above.
        if (preg_match('/<!\\[CDATA\\[(.*?)\\]\\]>/us', $data, $matches)) {
            return [$matches[1]];
        }

        $pos = $last_pos = -1;
        $end = strlen($data);
        $row = 0;
        $quote_open = false;
        $trim_quote = false;

        $return = array();

        // Create a continuous loop
        for ($i = -1; ; ++$i) {
            ++$pos;
            // Get the positions
            $comma_pos = strpos($data, $delimiter, $pos);
            $quote_pos = strpos($data, $enclosure, $pos);
            $newline_pos = strpos($data, $newline, $pos);

            // Which one comes first?
            $pos = min(
                ($comma_pos === false) ? $end : $comma_pos,
                ($quote_pos === false) ? $end : $quote_pos,
                ($newline_pos === false) ? $end : $newline_pos
            );

            // Cache it
            $char = (isset($data[$pos])) ? $data[$pos] : null;
            $done = ($pos == $end);

            // Is it a special character?
            if ($done || $char == $delimiter || $char == $newline) {

                // Ignore it as we're still in a quote
                if ($quote_open && !$done) {
                    continue;
                }

                $length = $pos - ++$last_pos;

                // Is the last thing a quote?
                if ($trim_quote) {
                    // Well then get rid of it
                    --$length;
                }

                // Get all the contents of this column
                $return[$row][] =
                    ($length > 0) ?
                    str_replace($enclosure . $enclosure, $enclosure, substr($data, $last_pos, $length)) :
                    '';

                // And we're done
                if ($done) {
                    break;
                }

                // Save the last position
                $last_pos = $pos;

                // Next row?
                if ($char == $newline) {
                    ++$row;
                }

                $trim_quote = false;
            } // Our quote?
            else if ($char == $enclosure) {

                // Toggle it
                if ($quote_open === false) {
                    // It's an opening quote
                    $quote_open = true;
                    $trim_quote = false;

                    // Trim this opening quote?
                    if ($last_pos + 1 == $pos) {
                        ++$last_pos;
                    }

                } else {
                    // It's a closing quote
                    $quote_open = false;

                    // Trim the last quote?
                    $trim_quote = true;
                }

            }

        }

        return $return[0];
    }
}
