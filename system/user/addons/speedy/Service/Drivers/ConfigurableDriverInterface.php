<?php

namespace BoldMinded\Speedy\Service\Drivers;

use BoldMinded\Speedy\Service\Drivers\Configuration\SettingsFormInterface;
use BoldMinded\Speedy\Service\Drivers\Support\SupportValidator;

interface ConfigurableDriverInterface extends DriverInterface
{
    public function configure(array $settings): void;

    public function isConfigured(): bool;

    public function getConfiguredValidator(): SupportValidator;

    public function getConfigurationForm(): SettingsFormInterface;

    public function hasFileConfigOverride(): bool;

    public function setHasFileConfigOverride(bool $hasFileConfigOverride);
}
