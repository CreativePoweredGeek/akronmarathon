<?php

namespace BoldMinded\Speedy\Queue;

trait QueueableTrait
{
    protected bool $shouldQueue;

    protected function isQueueAvailable(): bool
    {
        return ee('Addon')->get('queue')?->isInstalled() ?? false;

    }

    protected function shouldUseQueue(): bool
    {
        return $this->isQueueAvailable() && bool_config_item('speedy_use_queue');
    }

    protected function pushToQueue(
        string $className,
        mixed $payload,
    ) {
        ee('queue:QueueManager')->push($className, $payload);
    }
}
