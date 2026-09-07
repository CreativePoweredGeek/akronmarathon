<?php

namespace BoldMinded\DataGrab\Queue\Listeners;

class AbstractListener
{
    /**
     * @return bool
     */
    protected function isCli(): bool
    {
        return defined('STDIN') && php_sapi_name() === 'cli';
    }
}
