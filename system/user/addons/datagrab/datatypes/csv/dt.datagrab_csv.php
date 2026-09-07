<?php

use BoldMinded\DataGrab\DataTypes\AbstractDataType;
use BoldMinded\DataGrab\Dependency\League\Csv\Reader;
use BoldMinded\DataGrab\Service\PreflightCsv;

/**
 * DataGrab CSV import class
 *
 * Allows CSV imports
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 */
class Datagrab_csv extends AbstractDataType
{
    public string $type = 'CSV';

    public array $datatype_info = [
        'name' => 'CSV',
        'version' => '1.0',
        'description' => 'Import data from a CSV file',
        'allow_subloop' => true,
        'allow_multiple_fields' => true
    ];

    public array $settings = [
        'filename' => '',
        'delimiter' => '',
        'encloser' => '',
        'skip' => 0,
    ];

    private $iterator = 0;

    private array $columns = [];

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

    public function fetch(string $data = '')
    {
        // Open CSV file and save handle
        try {
            if ($data !== '') {
                $csv = $data;
            } else {
                if (!$this->getFilename()) {
                    $this->addError('You must supply a filename/url.');
                    return -1;
                }

                $content = $this->curlFetch($this->getFilename(), $this->settings['importId']);
                $content = mb_convert_encoding($content, 'UTF-8', 'auto');
                $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // Trim BOM if present

                $issues = PreflightCsv::check($content, $this->getDelimiter());

                if (!empty($issues)) {
                    foreach ($issues as $issue) {
                        if (isset($issue['line'])) {
                            $message = sprintf(
                                'Line %d: %s',
                                $issue['line'] ,
                                $issue['msg']
                            );
                        } else {
                            $message = $issue['msg'];
                        }

                        $this->addWarning($message);
                    }
                }

                $csv = Reader::createFromString($content);

                $csv->setDelimiter($this->getDelimiter());

                $encloser = $this->getEncloser();
                if ($encloser) {
                    $csv->setEnclosure($encloser);
                }

                if ($this->settings['skip']) {
                    $csv->setHeaderOffset(0);
                }
            }
        } catch (Exception $exception) {
            $this->addError($exception->getMessage());
            return -1;
        }

        if ($csv === false) {
            $this->addError('Failed to read from file.');
            return -1;
        }

        $this->columns = $csv->getHeader();

        // Make headers unique
        $counts = [];
        $madeUnique = false;

        foreach ($this->columns as &$column) {
            if ($column === '' || $column === null) {
                $column = '_blank';
                $madeUnique = true;
            }

            $counts[$column] = ($counts[$column] ?? 0) + 1;

            if ($counts[$column] > 1) {
                $column .= '_' . $counts[$column];
                $madeUnique = true;
            }
        }

        if ($madeUnique) {
            $this->addWarning('1 or more duplicate or missing column headings were found.
                A unique value has been added in order to facilitate the import.');
        }

        try {
            $csv_array = $csv->getRecords($this->columns);
        } catch (\Exception $exception) {
            $csv_array = [];
            $this->addError($exception->getMessage());
        }

        foreach ($csv_array as $row) {
            $this->items[] = $row;
            $this->itemsFlat[] = $row;
        }

        if (empty($this->items)) {
            $this->addError('No items were found. Please check file type, url/path to the file.');
            return -1;
        }

        return 1;
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

    public function fetch_columns(): array
    {
        try {
            $this->fetch();

            // Loop through fields, adding Column # and truncating any long labels
            $titles = [];
            $count = 0;

            foreach ($this->columns as $title) {
                if (strlen($title) > 32) {
                    $title = substr($title, 0, 32) . "...";
                }
                $titles[$title] = "Column " . ++$count . " - eg, " . $title;
            }

            return $titles;

        } catch (Error $error) {
            $this->addError($error->getMessage());
        }

        return [];
    }

    public function get_item(array $item, string $key): string
    {
        if (isset($item[$key])) {
            return trim(stripcslashes($item[$key]));
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

        // Find item and split into subitems
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
