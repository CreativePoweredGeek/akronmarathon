<?php

namespace BoldMinded\Speedy\Service\Logger;

class TemplateLogger implements Logger
{
    /** @var bool */
    private $debug = false;

    /**
     * @param bool $debug
     */
    public function __construct($debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     */
    public function error($message, array $context = [])
    {
        $message = '<span style="color:red"">Speedy: ' . $message . '</span>';

        ee()->TMPL->log_item($message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     */
    public function warning($message, array $context = [])
    {
        $message = '<span style="color:darkorange"">Speedy: ' . $message . '</span>';

        ee()->TMPL->log_item($message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     */
    public function info($message, array $context = [])
    {
        $message = 'Speedy: ' . $message;

        ee()->TMPL->log_item($message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     */
    public function debug($message, array $context = [])
    {
        if ($this->debug) {
            $this->info($message, $context);
        }
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     */
    public function log($level, $message, array $context = [])
    {
        $level = strtolower($level);

        if (method_exists($this, $level)) {
            $this->$level($message, $context);
        }

        $this->info($message, $context);
    }
}
