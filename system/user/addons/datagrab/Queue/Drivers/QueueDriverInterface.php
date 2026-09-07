<?php

namespace BoldMinded\DataGrab\Queue\Drivers;

use ExpressionEngine\Core\Provider;

interface QueueDriverInterface
{
    public function __construct(Provider $provider, array $config = []);

    public function getQueueManager();
}
