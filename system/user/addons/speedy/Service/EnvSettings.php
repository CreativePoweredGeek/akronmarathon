<?php

namespace BoldMinded\Speedy\Service;

class EnvSettings
{
    /**
     * Given any array of settings, check the values for an existing pattern
     * in the .env.php file to override the setting with. For example, if the
     * setting value is saved as $REDIS_HOST, it will match the REDIS_HOST=whatever
     * item in the .env.php file and override the setting value with that of the
     * value in .env.php
     */
    public static function override(array $settings = []): array
    {
        foreach ($settings as &$value) {
            if ($value && preg_match('/^\$(\w+)?/', $value)) {
                $value = $_ENV[(substr($value, 1))] ?? '';
            }
        }

        return $settings;
    }
}
