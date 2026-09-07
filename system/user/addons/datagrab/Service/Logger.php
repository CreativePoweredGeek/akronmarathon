<?php

namespace BoldMinded\DataGrab\Service;

use DateTime;
use EE_Logger;

class Logger
{
    private EE_Logger $logger;
    private string $logType;
    private string $logFile;
    private bool $shouldRotate;

    /**
     * @param EE_Logger $logger
     * @param string    $logType
     * @param string    $logFile
     */
    public function __construct(
        EE_Logger $logger,
        string $logType,
        string $logFile,
        bool $shouldRotate = false
    ){
        $this->logger = $logger;
        $this->logType = $logType;
        $this->logFile = $logFile;
        $this->shouldRotate = $shouldRotate;
    }

    /**
     * @param string $message
     * @param bool   $update
     * @return void
     */
    public function log(
        string $message = '',
        bool $update = true
    ): void
    {
        if (!$message || $this->logType === 'off') {
            return;
        }

        switch ($this->logType) {
            case 'developer':
                $this->logger->developer('DataGrab: ' . $message, $update);
                break;
            case 'php':
                if ($this->logFile) {
                    $oldLogFile = ini_get('error_log');
                    @ini_set('error_log', $this->logFile);
                    error_log('DataGrab: ' . $message);
                    @ini_set('error_log', $oldLogFile);
                } else {
                    error_log('DataGrab: ' . $message);
                }
                break;
            default:
                $this->writeToFile($message);
        }
    }

    /**
     * @param string $message
     * @return void
     */
    private function writeToFile(string $message = ''): void
    {
        $time = date('H:i:s m/d/Y', time());
        $stream = fopen($this->logFile, 'a+');
        fwrite($stream, $time . ' ' . print_r($message, true) ."\n");
        fclose($stream);
    }

    /**
     * @return void
     */
    public function reset(): void
    {
        switch ($this->logType) {
            case 'developer':
                // Developer log already updates duplicate messages with a new timestamp, effectively resetting it.
                break;
            case 'php':
                break;
            default:
                if ($this->shouldRotate) {
                    $this->rotate();
                }

                @unlink($this->logFile);
        }
    }

    private function rotate(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        $firstLine = fgets(fopen($this->logFile, 'r'));

        if ($firstLine === false) {
            return;
        }

        if (preg_match('/^(\d{2}:\d{2}:\d{2})\s+(\d{2}\/\d{2}\/\d{4})/', $firstLine, $matches)) {
            $time = $matches[1];
            $date = $matches[2];

            $dateTime = DateTime::createFromFormat('H:i:s m/d/Y', "$time $date");

            if (!$dateTime) {
                $dateTime = new DateTime();
            }

            $backupFile = $this->logFile . '.' . $dateTime->format('Ymd_His');
            @copy($this->logFile, $backupFile);
        }
    }

    public function getBacktrace(
        int $startIndex = 2,
        int $endIndex = 6,
        bool $includeArgs = true,
    ): string {
        $backtrace = debug_backtrace();

        $i = $startIndex;
        $messages = [];

        while ($i <= $endIndex) {
            if (isset($backtrace[$i])) {
                $callerIndex = $i + 1;
                if ($backtrace[$i + 1]['function'] == 'call_user_func_array') {
                    $callerIndex = $i + 2;
                }

                $caller = $backtrace[$callerIndex]['class'] .'->'. $backtrace[$callerIndex]['function'] .'()';
                $callerLine = isset($backtrace[$i]['line']) ? $backtrace[$i]['line'] : '';
                $method = $backtrace[$i]['class'] .'->'. $backtrace[$i]['function'] .'()';
                $methodLine = $backtrace[$callerIndex]['line'];

                $data = [
                    'caller' => $caller .' line: '. $callerLine,
                    'method' => $method .' line: '. $methodLine,
                ];

                if ($includeArgs) {
                    $data['args'] = json_encode($backtrace[$i]['args'], JSON_PRETTY_PRINT);
                }

                $messages[] = implode(' - ', $data);
            }

            $i++;
        }

        return "\n" . implode("\n", $messages);
    }
}
