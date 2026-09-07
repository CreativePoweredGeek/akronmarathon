<?php

namespace BoldMinded\Speedy\Framework;

class Module
{
    /** @var string */
    private $configPrefix;

    /**
     * Module constructor.
     *
     * @param string $configPrefix
     */
    public function __construct($configPrefix)
    {
        $this->configPrefix = $configPrefix;
    }

    /**
     * @param string $name
     * @param mixed  $default
     * @param bool   $config
     * @return mixed
     */
    protected function fetchParam($name, $default = false, $config = false)
    {
        $value = ee()->TMPL->fetch_param($name);

        if (empty($value) && $config) {
            $item = $this->configPrefix . '_' . $name;
            $value = ee()->config->item($item);
            $value = $this->normalizeConfigValue($value);
        }

        if (empty($value)) {
            return $default;
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function normalizeConfigValue($value)
    {
        switch ($value) {
            case 'y':
            case 'on':
                return 'yes';

            case 'n':
            case 'off':
                return 'no';

            default:
                return $value;
        }
    }
}
