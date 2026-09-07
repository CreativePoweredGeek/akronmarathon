<?php

namespace BoldMinded\DataGrab\Queue\Connectors;

use BoldMinded\DataGrab\Dependency\Illuminate\Queue\Connectors\SqsConnector;
use BoldMinded\DataGrab\Dependency\Illuminate\Support\Arr;

class SqsFifoConnector extends SqsConnector
{
    public function connect(array $config)
    {
        $config = $this->getDefaultConfiguration($config);

        if ($config['key'] && $config['secret']) {
            $config['credentials'] = Arr::only($config, ['key', 'secret']);
        }

        return new SqsFifoQueue(
            new SqsClient($config), $config['queue'], Arr::get($config, 'prefix', '')
        );
    }
}
