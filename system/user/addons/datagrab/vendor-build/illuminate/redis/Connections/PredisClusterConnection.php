<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Redis\Connections;

use BoldMinded\DataGrab\Dependency\Predis\Command\Redis\FLUSHDB;
use BoldMinded\DataGrab\Dependency\Predis\Command\ServerFlushDatabase;
class PredisClusterConnection extends PredisConnection
{
    /**
     * Flush the selected Redis database on all cluster nodes.
     *
     * @return void
     */
    public function flushdb()
    {
        $command = \class_exists(ServerFlushDatabase::class) ? ServerFlushDatabase::class : FLUSHDB::class;
        foreach ($this->client as $node) {
            $node->executeCommand(tap(new $command())->setArguments(\func_get_args()));
        }
    }
}
