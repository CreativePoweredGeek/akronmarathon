<?php

namespace BoldMinded\Speedy\Service\Supported;

interface SupportedCheck
{
    /**
     * @return bool
     */
    public function isSupported();

    /**
     * @return string
     */
    public function getMessage();
}
