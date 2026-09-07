<?php

namespace BoldMinded\Speedy\Service\Drivers\Configuration;

use BoldMinded\Speedy\Model\DriverConfiguration;

interface SettingsFormInterface
{
    /**
     * @param \BoldMinded\Speedy\Model\DriverConfiguration $configuration
     * @return array
     */
    public function getSections(DriverConfiguration $configuration);

    /**
     * @param \BoldMinded\Speedy\Model\DriverConfiguration $configuration
     * @return bool
     */
    public function validate(DriverConfiguration $configuration);

    /**
     * @return array
     */
    public function getErrors();
}
