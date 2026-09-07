<?php

namespace BoldMinded\Speedy\Service\Drivers\Support;

class SupportRule
{
    /** @var string */
    private $reason;

    /** @var callable */
    private $test;

    /**
     * SupportRule constructor.
     *
     * @param string   $reason
     * @param callable $test
     */
    public function __construct($reason, callable $test)
    {
        $this->reason = $reason;
        $this->test = $test;
    }

    public function isSupported(): bool
    {
        $f = $this->test;

        return $f();
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
