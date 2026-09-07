<?php

namespace BoldMinded\DataGrab\Service;
final class PreflightXml
{
    public static function check(string $raw): array
    {
        $issues = [];

        // 1) Sniff BOM & declared encoding
        $bom = [
            'UTF-8' => "\xEF\xBB\xBF",
            'UTF-16LE' => "\xFF\xFE",
            'UTF-16BE' => "\xFE\xFF",
        ];
        foreach ($bom as $name => $sig) {
            if (strncmp($raw, $sig, strlen($sig)) === 0) {
                $issues[] = ["level" => "info", "code" => "bom_detected", "msg" => "Byte Order Mark detected ($name)."];
                // strip BOM for consistency
                $raw = substr($raw, strlen($sig));
                break;
            }
        }

        $declared = null;
        if (preg_match('/<\?xml[^>]*encoding=["\']([^"\']+)["\']/i', $raw, $m)) {
            $declared = strtoupper($m[1]);
        }

        // 2) Detect actual encoding & normalize to UTF-8 (without data loss if possible)
        $detected = mb_detect_encoding($raw, ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UNKNOWN';
        if ($declared && $detected !== 'UNKNOWN' && strtoupper($detected) !== strtoupper($declared)) {
            $issues[] = ["level" => "warn", "code" => "encoding_mismatch", "msg" => "Declared encoding $declared but content looks like $detected."];
        }

        $utf8 = $raw;
        if ($detected !== 'UTF-8' && $detected !== 'UNKNOWN') {
            $converted = @iconv($detected, 'UTF-8//TRANSLIT', $raw);
            if ($converted === false) {
                $issues[] = ["level" => "error", "code" => "convert_fail", "msg" => "Failed converting from $detected to UTF-8."];
            } else {
                $utf8 = $converted;
                $issues[] = ["level" => "info", "code" => "converted", "msg" => "Converted from $detected to UTF-8 for preflight."];
            }
        }

        // 3) Validate well-formedness with libxml (captures line/column)
        // LIBXML_PARSEHUGE is intentionally omitted so libxml's built-in limits
        // continue to defend against entity-expansion / oversized-document DoS.
        libxml_use_internal_errors(true);
        libxml_set_external_entity_loader(static fn() => null);
        $ok = simplexml_load_string($utf8, 'SimpleXMLElement', LIBXML_NONET);
        foreach (libxml_get_errors() as $err) {
            $issues[] = ["level" => "error", "code" => "xml_wellformed", "line" => $err->line, "col" => $err->column, "msg" => trim($err->message)];
        }
        libxml_clear_errors();

        // 4) Scan lines for suspicious bytes/chars
        $lines = preg_split("/\r\n|\r|\n/", $utf8);
        foreach ($lines as $i => $line) {
            $lineNo = $i + 1;

            // a) invalid UTF-8 (quick check)
            if (!preg_match('//u', $line)) {
                $issues[] = ["level" => "error", "code" => "invalid_utf8", "line" => $lineNo, "msg" => "Invalid UTF-8 byte sequence on this line."];
            }

            // b) disallowed control chars (except \t \n \r)
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $line)) {
                $issues[] = ["level" => "warn", "code" => "control_chars", "line" => $lineNo, "msg" => "Contains ASCII control characters (C0)."];
            }

            // c) C1 controls \x80–\x9F (often Windows-1252 artifacts)
            if (preg_match('/[\x{0080}-\x{009F}]/u', $line)) {
                $issues[] = ["level" => "warn", "code" => "c1_controls", "line" => $lineNo, "msg" => "Contains C1 controls (likely Windows-1252 smart quotes, etc.)."];
            }

            // d) zero-width & NBSP & BOM
            if (preg_match('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{00A0}]/u', $line)) {
                $issues[] = ["level" => "warn", "code" => "invisible_chars", "line" => $lineNo, "msg" => "Contains zero-width or non-breaking spaces."];
            }

            // e) replacement char
            if (preg_match('/\x{FFFD}/u', $line)) {
                $issues[] = ["level" => "warn", "code" => "replacement_char", "line" => $lineNo, "msg" => "Contains � replacement character (prior corruption)."];
            }

            // f) CDATA with suspicious content
            if (strpos($line, '<![CDATA[') !== false && preg_match('/]]>/', $line)) {
                $issues[] = ["level" => "warn", "code" => "cdata_close", "line" => $lineNo, "msg" => "Found \"]]>\" near CDATA; ensure CDATA isn’t prematurely closed."];
            }
        }

        // 5) Optional: NFC normalization preview
        if (class_exists(\Normalizer::class)) {
            $nfc = \Normalizer::normalize($utf8, \Normalizer::FORM_C);
            if ($nfc !== null && $nfc !== $utf8) {
                $issues[] = ["level" => "info", "code" => "normalized_nfc", "msg" => "Content would change under Unicode NFC normalization (combining marks, accents)."];
            }
        }

        return $issues;
    }
}
