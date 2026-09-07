<?php

namespace BoldMinded\Speedy\Service\Request;

interface RequestDriver
{
    const USER_AGENT = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36';

    /**
     * @return bool
     */
    public static function isSupported();

    /**
     * @param string $url
     * @return bool
     */
    public function get($url);
}
