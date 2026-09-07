<?php

namespace BoldMinded\Speedy\Service\Supported\Check;

use BoldMinded\Speedy\Service\Supported\SupportedCheck;

class ConfigNotEqualsCheck implements SupportedCheck
{
    /** @var string */
    private $key;

    /** @var mixed */
    private $value;

    /** @var string */
    private $message;

    /**
     * ConfigNotEqualsCheck constructor.
     *
     * @param string $key
     * @param mixed  $value
     * @param string $message
     */
    public function __construct($key, $value, $message = null)
    {
        $this->key = $key;
        $this->value = $value;
        $this->message = $message;
    }

    /**
     * @return bool
     */
    public function isSupported()
    {
        return ee()->config->item($this->key) !== $this->value;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        $message = $this->message ?: lang('speedy_supported_config_not_equals');

        return sprintf($message, $this->key, $this->value);
    }
}
