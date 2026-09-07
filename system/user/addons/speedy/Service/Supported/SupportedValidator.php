<?php

namespace BoldMinded\Speedy\Service\Supported;

class SupportedValidator
{
    /** @var \BoldMinded\Speedy\Service\Supported\SupportedCheck[] */
    private $checks = [];

    /**
     * @return \BoldMinded\Speedy\Service\Supported\SupportedValidator
     */
    public static function make()
    {
        return new self();
    }

    /**
     * @param string $functionName
     * @param string $message
     * @return \BoldMinded\Speedy\Service\Supported\SupportedValidator
     */
    public function checkFunctionUsable($functionName, $message = null)
    {
        $this->checks[] = new Check\FunctionUsableCheck($functionName, $message);

        return $this;
    }

    /**
     * @param string $className
     * @param string $message
     * @return \BoldMinded\Speedy\Service\Supported\SupportedValidator
     */
    public function checkClassExists($className, $message = null)
    {
        $this->checks[] = new Check\ClassExistsCheck($className, $message);

        return $this;
    }

    /**
     * @param string $iniKey
     * @param string $message
     * @return \BoldMinded\Speedy\Service\Supported\SupportedValidator
     */
    public function checkIniEnabled($iniKey, $message = null)
    {
        $this->checks[] = new Check\IniEnabledCheck($iniKey, $message);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param string $message
     * @return \BoldMinded\Speedy\Service\Supported\SupportedValidator
     */
    public function checkConfigEquals($key, $value, $message = null)
    {
        $this->checks[] = new Check\ConfigEqualsCheck($key, $value, $message);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param string $message
     * @return \BoldMinded\Speedy\Service\Supported\SupportedValidator
     */
    public function checkConfigNotEquals($key, $value, $message = null)
    {
        $this->checks[] = new Check\ConfigNotEqualsCheck($key, $value, $message);

        return $this;
    }

    /**
     * @param callable $callback
     * @param string   $message
     * @return \BoldMinded\Speedy\Service\Supported\SupportedValidator
     */
    public function checkCallback(callable $callback, $message)
    {
        $this->checks[] = new Check\CallbackCheck($callback, $message);

        return $this;
    }

    /**
     * @return bool
     */
    public function isSupported()
    {
        foreach ($this->checks as $check) {
            if (!$check->isSupported()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        $messages = [];

        foreach ($this->checks as $check) {
            if (!$check->isSupported()) {
                $messages[] = $check->getMessage();
            }
        }

        return $messages;
    }
}
