<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Redis\Connections;

use Closure;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Redis\Connection as ConnectionContract;
use BoldMinded\DataGrab\Dependency\Illuminate\Support\Collection;
use BoldMinded\DataGrab\Dependency\Predis\Command\Argument\ArrayableArgument;
/**
 * @mixin \Predis\Client
 */
class PredisConnection extends Connection implements ConnectionContract
{
    /**
     * The Predis client.
     *
     * @var \Predis\Client
     */
    protected $client;
    /**
     * Create a new Predis connection.
     *
     * @param  \Predis\Client  $client
     * @return void
     */
    public function __construct($client)
    {
        $this->client = $client;
    }
    /**
     * Subscribe to a set of given channels for messages.
     *
     * @param  array|string  $channels
     * @param  \Closure  $callback
     * @param  string  $method
     * @return void
     */
    public function createSubscription($channels, Closure $callback, $method = 'subscribe')
    {
        $loop = $this->pubSubLoop();
        $loop->{$method}(...\array_values((array) $channels));
        foreach ($loop as $message) {
            if ($message->kind === 'message' || $message->kind === 'pmessage') {
                $callback($message->payload, $message->channel);
            }
        }
        unset($loop);
    }
    /**
     * Parse the command's parameters for event dispatching.
     *
     * @param  array  $parameters
     * @return array
     */
    protected function parseParametersForEvent(array $parameters)
    {
        return (new Collection($parameters))->transform(function ($parameter) {
            return $parameter instanceof ArrayableArgument ? $parameter->toArray() : $parameter;
        })->all();
    }
}
