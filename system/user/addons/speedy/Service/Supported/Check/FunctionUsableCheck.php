<?php

namespace BoldMinded\Speedy\Service\Supported\Check;

use BoldMinded\Speedy\Service\Supported\SupportedCheck;

class FunctionUsableCheck implements SupportedCheck
{
    /** @var string */
    private $functionName;

    /** @var string */
    private $message;

    /**
     * FunctionUsableCheck constructor.
     *
     * @param string $functionName
     * @param string $message
     */
    public function __construct($functionName, $message = null)
    {
        $this->functionName = $functionName;
        $this->message = $message;
    }

    /**
     * @return bool
     */
    public function isSupported()
    {
        if (function_exists('function_usable')) {
            return function_usable($this->functionName);
        }

        return function_exists($this->functionName);
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        $message = $this->message ?: lang('speedy_supported_function_usable');

        return sprintf($message, $this->functionName);
    }
}
