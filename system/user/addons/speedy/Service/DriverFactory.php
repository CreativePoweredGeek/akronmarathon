<?php

namespace BoldMinded\Speedy\Service;

use BoldMinded\Speedy\Service\Drivers\AbstractDriver;
use BoldMinded\Speedy\Service\Drivers\DatabaseDriver;
use BoldMinded\Speedy\Service\Drivers\StaticDriver;
use BoldMinded\Speedy\Service\Drivers\ConfigurableDriverInterface;
use BoldMinded\Speedy\Service\Drivers\DriverInterface;
use BoldMinded\Speedy\Service\Drivers\DummyDriver;
use BoldMinded\Speedy\Service\Drivers\FilesystemDriver;
use BoldMinded\Speedy\Service\Drivers\MemcachedDriver;
use BoldMinded\Speedy\Service\Drivers\MemcacheDriver;
use BoldMinded\Speedy\Service\Drivers\RedisDriver;
use BoldMinded\Speedy\Service\Logger\ArrayLogger;
use BoldMinded\Speedy\Service\Logger\Logger;

class DriverFactory
{
    const FALLBACK_DRIVER = 'dummy';

    /** @var \BoldMinded\Speedy\Service\Drivers\DriverInterface[] */
    private static $instances = [];

    /** @var array */
    private $drivers = [
        //ApcDriver::NAME        => ApcDriver::class,
        //ApcuDriver::NAME       => ApcuDriver::class,
        DatabaseDriver::NAME   => DatabaseDriver::class,
        DummyDriver::NAME      => DummyDriver::class,
        FilesystemDriver::NAME => FilesystemDriver::class,
        //MemcacheDriver::NAME   => MemcacheDriver::class,
        MemcachedDriver::NAME  => MemcachedDriver::class,
        RedisDriver::NAME      => RedisDriver::class,
        StaticDriver::NAME     => StaticDriver::class,
    ];

    public function __construct()
    {
        // @todo: Add extension hook to register custom drivers
    }

    /**
     * @return DriverInterface[]
     */
    public function getDrivers()
    {
        $drivers = [];
        foreach ($this->drivers as $name => $class) {
            $driver = $this->getDriver($name);
            if ($driver) {
                $drivers[] = $driver;
            }
        }

        return $drivers;
    }

    /**
     * @return DriverInterface[]
     */
    public function getEnabledDrivers()
    {
        $drivers = $this->getDrivers();

        foreach ($drivers as $key => $driver) {
            if (!$driver->isSupported()) {
                unset($drivers[$key]);
            }
        }

        return $drivers;
    }

    /**
     * @param string      $driverName
     * @param Logger|null $logger
     * @return DriverInterface|false
     */
    public function getDriver($driverName, Logger|null $logger = null)
    {
        if (!isset(self::$instances[$driverName])) {
            $class = $this->getDriverClass($driverName);

            if ($class === false) {
                return false;
            }

            /** @var DriverInterface|AbstractDriver $driver */
            $driver = new $class;

            if ($logger === null) {
                $logger = new ArrayLogger();
            }

            $driver->setLogger($logger);

            if ($driver->isSupported() && $driver instanceof ConfigurableDriverInterface) {
                $configuration = $this->getDriverConfiguration($driverName);

                if ($configuration === null) {
                    $configuration = ee('Model')->make('speedy:DriverConfiguration');
                    $configuration->settings = [];
                }

                // Allow config overrides
                $configSettings = ee()->config->item('speedy_'. $driverName .'_settings');

                if ($configSettings && is_array($configSettings) && !empty($configSettings)) {
                    $configuration = ee('Model')->make('speedy:DriverConfiguration');
                    $configuration->settings = $configSettings;

                    $driver->setHasFileConfigOverride(true);
                }

                // If a reverse proxy purger is configured, pass it along to the driver
                $setting = ee('speedy:Setting');
                $purgerSettings = json_decode($setting->get('settings_purger'), true);

                if ($purgerSettings) {
                    $configuration->settings = array_merge($configuration->settings, [
                        'purger' => $purgerSettings
                    ]);
                }

                $driver->configure($configuration->settings);
            }

            self::$instances[$driverName] = $driver;
        }

        return self::$instances[$driverName];
    }

    /**
     * @param $driverName
     * @return object|null
     */
    private function getDriverConfiguration($driverName)
    {
        return ee('Model')->get('speedy:DriverConfiguration')
            ->filter('site_id', '=', ee()->config->item('site_id'))
            ->filter('driver', '=', $driverName)
            ->first();
    }

    /**
     * @param string $name
     * @return string
     */
    private function getDriverClass($name)
    {
        if (array_key_exists($name, $this->drivers)) {
            $driver = $this->drivers[$name];
        } else {
            return false;
        }

        if ($this->isValidDriverClass($driver)) {
            return $driver;
        }

        return false;
    }

    /**
     * @param string $driver
     * @return bool
     */
    private function isValidDriverClass($driver)
    {
        return class_exists($driver) &&
               is_subclass_of($driver, DriverInterface::class, true);
    }
}
