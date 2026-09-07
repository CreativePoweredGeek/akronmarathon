<?php

namespace BoldMinded\Speedy\Service;

class ShutdownProcess
{
    /** @var callable */
    private $callback;

    /**
     * ShutdownProcess constructor.
     *
     * @param callable $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Static constructor.
     *
     * @param callable $callback
     * @return \BoldMinded\Speedy\Service\ShutdownProcess
     */
    public static function create(callable $callback)
    {
        $shutdown = new self($callback);
        $shutdown->register();

        return $shutdown;
    }

    /**
     * Register the callback.
     */
    public function register()
    {
        register_shutdown_function([$this, 'call']);
    }

    /**
     * Unregister the callback.
     */
    public function unregister()
    {
        $this->callback = null;
    }

    /**
     * Executed by the register_shutdown_function.
     */
    public function call()
    {
        if ($this->callback) {
            $callback = $this->callback;
            $callback();
        }
    }
}
