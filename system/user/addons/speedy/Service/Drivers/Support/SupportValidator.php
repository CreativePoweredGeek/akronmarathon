<?php

namespace BoldMinded\Speedy\Service\Drivers\Support;

class SupportValidator
{
    /** @var \BoldMinded\Speedy\Service\Drivers\Support\SupportRule[] */
    private $rules = [];

    /**
     * @return \BoldMinded\Speedy\Service\Drivers\Support\SupportValidator
     */
    public static function create()
    {
        return new self();
    }

    /**
     * @param string   $reason
     * @param callable $test
     * @return \BoldMinded\Speedy\Service\Drivers\Support\SupportValidator
     */
    public function addRule($reason, callable $test)
    {
        $this->rules[] = new SupportRule($reason, $test);

        return $this;
    }

    public function isSupported(): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule->isSupported()) {
                return false;
            }
        }

        return true;
    }

    public function getSupportList(): array
    {
        $support = [];

        foreach ($this->rules as $rule) {
            $support[$rule->getReason()] = $rule->isSupported();
        }

        return $support;
    }
}
