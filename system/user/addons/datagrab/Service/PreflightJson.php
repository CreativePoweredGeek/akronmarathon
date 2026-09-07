<?php

namespace BoldMinded\DataGrab\Service;

final class PreflightJson
{
    public static function check(string $raw): array
    {
        $issues = [];

        // 1) Detect BOM / encoding mismatch
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $issues[] = ["level" => "info", "code" => "bom_detected", "msg" => "Byte Order Mark (BOM) detected at start of file."];
            $raw = substr($raw, 3);
        }

        $detected = mb_detect_encoding($raw, ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UNKNOWN';
        if ($detected !== 'UTF-8' && $detected !== 'UNKNOWN') {
            $issues[] = ["level" => "warn", "code" => "encoding_mismatch", "msg" => "File appears to be $detected, not UTF-8."];
            $raw = @iconv($detected, 'UTF-8//TRANSLIT', $raw) ?: $raw;
        }

        // 2) Invalid UTF-8 bytes
        if (!preg_match('//u', $raw)) {
            $issues[] = ["level" => "error", "code" => "invalid_utf8", "msg" => "Invalid UTF-8 byte sequence found."];
        }

        // 3) Disallowed control characters
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $raw)) {
            $issues[] = ["level" => "warn", "code" => "control_chars", "msg" => "Contains control characters (ASCII 0–31,127)."];
        }

        // 4) Invisible characters
        if (preg_match('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{00A0}]/u', $raw)) {
            $issues[] = ["level" => "warn", "code" => "invisible_chars", "msg" => "Contains zero-width or non-breaking space characters."];
        }

        // 5) Replacement character
        if (preg_match('/\x{FFFD}/u', $raw)) {
            $issues[] = ["level" => "warn", "code" => "replacement_char", "msg" => "Contains � replacement character (indicates corruption)."];
        }

        // 6) Try to decode JSON
        $data = json_decode($raw, true, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $issues[] = [
                "level" => "error",
                "code" => "json_decode_error",
                "msg" => json_last_error_msg()
            ];
        } else {
            // 7) Optional structural checks
            $issues = array_merge($issues, self::checkJsonStructure($data));
        }

        return $issues;
    }

    private static function checkJsonStructure($data, string $path = '$'): array
    {
        $issues = [];

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $subPath = is_string($key)
                    ? sprintf('%s.%s', $path, $key)
                    : sprintf('%s[%d]', $path, $key);

                if (is_string($value)) {
                    // look for suspicious characters
                    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
                        $issues[] = ["level" => "warn", "code" => "control_chars", "path" => $subPath, "msg" => "String contains control characters."];
                    }
                    if (strlen($value) > 10000) {
                        $issues[] = ["level" => "info", "code" => "long_string", "path" => $subPath, "msg" => "Very long string (>10k chars)."];
                    }
                } elseif (is_array($value) || is_object($value)) {
                    $issues = array_merge($issues, self::checkJsonStructure($value, $subPath));
                }
            }
        } elseif (is_object($data)) {
            $issues = array_merge($issues, self::checkJsonStructure((array)$data, $path));
        }

        return $issues;
    }
}
