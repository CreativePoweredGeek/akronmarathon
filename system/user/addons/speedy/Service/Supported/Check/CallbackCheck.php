<?php

namespace BoldMinded\Speedy\Service\Supported\Check;

use BoldMinded\Speedy\Service\Supported\SupportedCheck;

class CallbackCheck implements SupportedCheck
{
    /** @var callable */
    private $callback;

    /** @var string */
    private $message;

    /**
     * CallbackCheck constructor.
     *
     * @param callable $callback
     * @param string   $message
     */
    public function __construct(callable $callback, $message)
    {
        $this->callback = $callback;
        $this->message = $message;
    }

    /**
     * @return bool
     */
    public function isSupported()
    {
        return call_user_func($this->callback);
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }
}
