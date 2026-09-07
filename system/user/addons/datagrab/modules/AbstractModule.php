<?php

abstract class AbstractModule
{
    /**
     * @var array
     */
    protected $settings = [];

    public function isInstalled()
    {
        return array_key_exists($this->getName(), ee()->addons->get_installed('modules'));
    }

    /**
     * @param array $settings
     * @return AbstractModule
     */
    public function setSettings(array $settings = []): AbstractModule
    {
        $this->settings = $settings;

        return $this;
    }

    /**
     * @param string $name
     * @return mixed|string
     */
    protected function getSettingValue(string $name)
    {
        if (empty($this->settings)) {
            return '';
        }

        // Legacy module settings. They were lumped with the custom fields array, and prefixed with ajw_ :(
        // Eventually when an import is saved the settings will be given the new cm (custom modules) namespace.
        if (isset($this->settings['cf']['ajw_' . $name])) {
            return $this->settings['cf']['ajw_' . $name];
        }

        return $this->settings['cm'][$this->getName()][$name] ?? '';
    }
}
