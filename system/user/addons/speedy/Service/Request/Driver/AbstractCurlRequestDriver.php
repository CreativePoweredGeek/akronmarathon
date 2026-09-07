<?php

namespace BoldMinded\Speedy\Service\Request\Driver;

use BoldMinded\Speedy\Service\Request\RequestDriver;

abstract class AbstractCurlRequestDriver implements RequestDriver
{
    /**
     * @param string $url
     * @return resource|bool
     */
    protected function buildCurlHandle($url)
    {
        $ch = @curl_init($url);

        if ($ch === false) {
            return false;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, false);
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cache-Control: no-cache']);

        return $ch;
    }
}
