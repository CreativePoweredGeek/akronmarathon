<?php

namespace BoldMinded\DataGrab\Service;

use SplTempFileObject;

final class PreflightCsv
{
    public static function check(string $raw, string $delimiter = ',', int $sampleRows = 500): array
    {
        $issues = [];

        // 1) Detect BOM & encoding
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $issues[] = ["level" => "info", "code" => "bom_detected", "msg" => "UTF-8 BOM detected at start of file."];
            $raw = substr($raw, 3);
        }

        $detected = mb_detect_encoding($raw, ['UTF-8','UTF-16LE','UTF-16BE','ISO-8859-1','Windows-1252'], true) ?: 'UNKNOWN';
        if ($detected !== 'UTF-8' && $detected !== 'UNKNOWN') {
            $issues[] = ["level" => "warn", "code" => "encoding_mismatch", "msg" => "File appears to be $detected, not UTF-8."];
            $raw = @iconv($detected, 'UTF-8//TRANSLIT', $raw) ?: $raw;
        }

        // 2) Invalid UTF-8 or control characters
        if (!preg_match('//u', $raw)) {
            $issues[] = ["level" => "error", "code" => "invalid_utf8", "msg" => "Invalid UTF-8 byte sequence detected."];
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $raw)) {
            $issues[] = ["level" => "warn", "code" => "control_chars", "msg" => "File contains ASCII control characters."];
        }
        if (preg_match('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{00A0}]/u', $raw)) {
            $issues[] = ["level" => "warn", "code" => "invisible_chars", "msg" => "Zero-width or non-breaking space detected."];
        }

        // 3) Analyze structure via SplTempFileObject (memory safe)
        $f = new SplTempFileObject();
        $f->fwrite($raw);
        $f->rewind();

        $expectedCols = null;
        $rowNum = 0;

        while (!$f->eof() && $rowNum < $sampleRows) {
            $line = trim($f->fgets());
            if ($line === '') continue; // skip blanks
            $rowNum++;

            // Count quotes (odd number = probably unescaped)
            $quoteCount = substr_count($line, '"');
            if ($quoteCount % 2 !== 0) {
                $issues[] = ["level" => "error", "code" => "unescaped_quote", "line" => $rowNum, "msg" => "Possible unescaped quotes detected."];
            }

            // Rough parse (to detect column count inconsistencies)
            $cols = str_getcsv($line, $delimiter);
            $count = count($cols);

            if ($expectedCols === null) {
                $expectedCols = $count;
            } elseif ($count !== $expectedCols) {
                $issues[] = ["level" => "warn", "code" => "col_mismatch", "line" => $rowNum, "msg" => "Expected $expectedCols columns, found $count."];
            }

            // Look for embedded newlines
            if (preg_match("/\"[^\"]*[\r\n][^\"]*\"/", $line)) {
                $issues[] = ["level" => "warn", "code" => "embedded_newline", "line" => $rowNum, "msg" => "Embedded newline inside quoted field."];
            }

            // Control chars in cells
            foreach ($cols as $i => $cell) {
                if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $cell)) {
                    $issues[] = ["level" => "warn", "code" => "cell_control_chars", "line" => $rowNum, "col" => $i + 1, "msg" => "Control characters found in cell."];
                }
                if (preg_match('/\x{FFFD}/u', $cell)) {
                    $issues[] = ["level" => "warn", "code" => "replacement_char", "line" => $rowNum, "col" => $i + 1, "msg" => "Contains replacement char (�)."];
                }
            }
        }

        if ($expectedCols === null) {
            $issues[] = ["level" => "error", "code" => "empty_file", "msg" => "File appears to be empty or contains no valid rows."];
        }

        return $issues;
    }
}
