<?php

namespace BoldMinded\Speedy\Service\Request\Driver;

use BoldMinded\Speedy\Service\Supported\SupportedValidator;

class StreamAsyncRequestDriver extends AbstractStreamRequestDriver
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
        @ini_set('allow_url_fopen', true);

        return SupportedValidator::make()
            ->checkConfigNotEquals('speedy_refresh_async', 'no')
            ->checkFunctionUsable('stream_socket_client')
            ->checkIniEnabled('allow_url_fopen');
    }

    /**
     * @return int
     */
    protected function getSocketFlags()
    {
        return STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
    }

    /**
     * @return int
     */
    protected function getSocketTimeout()
    {
        return 1;
    }

    /**
     * @return bool
     */
    protected function getSocketBlocking()
    {
        return false;
    }
}
