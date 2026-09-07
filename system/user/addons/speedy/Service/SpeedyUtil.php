<?php

namespace BoldMinded\Speedy\Service;

final class SpeedyUtil
{
    private function __construct() { }

    /**
     * @param string $known_string
     * @param string $user_string
     * @return bool
     */
    public static function hashEquals($known_string, $user_string)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($known_string, $user_string);
        }

        if (strlen($known_string) != strlen($user_string)) {
            return false;
        } else {
            $res = $known_string ^ $user_string;
            $ret = 0;
            for ($i = strlen($res) - 1; $i >= 0; $i--) {
                $ret |= ord($res[$i]);
            }
            return !$ret;
        }
    }
}
