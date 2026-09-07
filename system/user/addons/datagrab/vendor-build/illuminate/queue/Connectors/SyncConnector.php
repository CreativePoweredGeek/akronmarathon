<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Queue\Connectors;

use BoldMinded\DataGrab\Dependency\Illuminate\Queue\SyncQueue;
class SyncConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        return new SyncQueue($config['after_commit'] ?? null);
    }
}
