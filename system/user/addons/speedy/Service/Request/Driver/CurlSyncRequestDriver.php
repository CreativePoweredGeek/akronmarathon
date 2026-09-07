<?php

namespace BoldMinded\Speedy\Service\Request\Driver;

use BoldMinded\Speedy\Service\Supported\SupportedValidator;

class CurlSyncRequestDriver extends AbstractCurlRequestDriver
{
    /**
     * @return bool
     */
    public static function isSupported()
    {
        return self::getSupportValidator()->isSupported();
    }

    /**
     * @return \BoldMinded\Speedy\Service\Supported\SupportedValidator
     */
    public static function getSupportValidator()
    {
        return SupportedValidator::make()
            ->checkConfigNotEquals('speedy_refresh_curl', 'no')
            ->checkFunctionUsable('curl_init');
    }

    /**
     * @param string $url
     * @return bool
     */
    public function get($url)
    {
        $ch = $this->buildCurlHandle($url);

        if ($ch === false) {
            return false;
        }

        @curl_setopt($ch, CURLOPT_HEADER, true); // we want headers
        @curl_setopt($ch, CURLOPT_NOBODY, true); // we don't need body

        curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 400;
    }
}
