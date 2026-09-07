<?php

namespace BoldMinded\Speedy\Service\Supported\Check;

use BoldMinded\Speedy\Service\Supported\SupportedCheck;

class IniEnabledCheck implements SupportedCheck
{
    /** @var string */
    private $iniKey;

    /** @var string */
    private $message;

    /**
     * IniEnabledCheck constructor.
     *
     * @param string $iniKey
     * @param string $message
     */
    public function __construct($iniKey, $message = null)
    {
        $this->iniKey = $iniKey;
        $this->message = $message;
    }

    /**
     * @return bool
     */
    public function isSupported()
    {
        return (bool) ini_get($this->iniKey);
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        $message = $this->message ?: lang('speedy_supported_ini_enabled');

        return sprintf($message, $this->iniKey);
    }
}
