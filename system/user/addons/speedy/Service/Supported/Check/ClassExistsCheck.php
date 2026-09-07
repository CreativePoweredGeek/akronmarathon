<?php

namespace BoldMinded\Speedy\Service\Supported\Check;

use BoldMinded\Speedy\Service\Supported\SupportedCheck;

class ClassExistsCheck implements SupportedCheck
{
    /** @var string */
    private $className;

    /** @var string */
    private $message;

    /**
     * ClassExistsCheck constructor.
     *
     * @param string $className
     * @param string $message
     */
    public function __construct($className, $message = null)
    {
        $this->className = $className;
        $this->message = $message;
    }

    /**
     * @return bool
     */
    public function isSupported()
    {
        return class_exists($this->className);
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        $message = $this->message ?: lang('speedy_supported_class_exists');

        return sprintf($message, $this->className);
    }
}
