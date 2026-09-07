<?php

namespace BoldMinded\Speedy\Service\Request;

use BoldMinded\Speedy\Service\Logger\Logger;
use BoldMinded\Speedy\Service\Request\Driver\CurlAsyncRequestDriver;
use BoldMinded\Speedy\Service\Request\Driver\CurlExecRequestDriver;
use BoldMinded\Speedy\Service\Request\Driver\CurlSyncRequestDriver;
use BoldMinded\Speedy\Service\Request\Driver\StreamAsyncRequestDriver;
use BoldMinded\Speedy\Service\Request\Driver\StreamSyncRequestDriver;

class Facade
{
    /** @var \BoldMinded\Speedy\Service\Request\RequestDriver[] */
    private $drivers = [];

    /**
     * Request constructor.
     */
    public function __construct(
        private Logger $logger
    )
    {
        if (CurlAsyncRequestDriver::isSupported()) {
            $this->drivers[] = new CurlAsyncRequestDriver();
        }
        if (CurlExecRequestDriver::isSupported()) {
            $this->drivers[] = new CurlExecRequestDriver();
        }
        if (CurlSyncRequestDriver::isSupported()) {
            $this->drivers[] = new CurlSyncRequestDriver();
        }
        if (StreamAsyncRequestDriver::isSupported()) {
            $this->drivers[] = new StreamAsyncRequestDriver();
        }
        if (StreamSyncRequestDriver::isSupported()) {
            $this->drivers[] = new StreamSyncRequestDriver();
        }
    }

    public function isSupported(): bool
    {
        return count($this->drivers) > 0;
    }

    public function get(string $url): bool
    {
        try {
            foreach ($this->drivers as $driver) {
                $this->logger->info(sprintf(
                    'Attempting to load %s with %s',
                    $url,
                    $driver::class
                ));

                if ($driver->get($url)) {
                    return true;
                }
            }
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                'Could not load %s with error %s',
                $url,
                $exception->getMessage()
            ));
        }

        $this->logger->error(sprintf('Could not load %s', $url));

        return false;
    }

    public function getAction(
        string $class,
        string $method,
        array $params = []
    ): bool
    {
        $action_id = $this->fetchActionId($class, $method);

        if ($action_id === false) {
            return false;
        }

        $url = $this->getActionUrl($class, $method, $params);

        if (!$url) {
            return false;
        }

        return $this->get($url);
    }

    /**
     * @param string    $class
     * @param string    $method
     * @param array     $params
     * @return string
     */
    public function getActionUrl(
        string $class,
        string $method,
        array $params = []
    ): string
    {
        $actionId = $this->fetchActionId($class, $method);

        if (!$actionId) {
            return false;
        }

        $siteIndex = ee()->functions->fetch_site_index(false, false);
        $additional = rtrim('&' . http_build_query($params, '', '&'), '&');

        return $siteIndex . '?ACT=' . $actionId . $additional;
    }

    public function fetchActionId(
        string $class,
        string $method
    ): int
    {
        /** @var \ExpressionEngine\Model\Addon\Action $action */
        $action = ee('Model')->get('Action')
            ->filter('class', '=', $class)
            ->filter('method', '=', $method)
            ->first();

        if ($action === null) {
            return 0;
        }

        return $action->action_id;
    }
}
