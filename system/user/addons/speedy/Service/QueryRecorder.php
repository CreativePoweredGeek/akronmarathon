<?php

namespace BoldMinded\Speedy\Service;

use ExpressionEngine\Service\Database\Database;
use ExpressionEngine\Service\Database\Log;

class QueryRecorder
{
    private Database $database;
    private bool $recording = false;
    private array $queries = [];

    public function __construct()
    {
        $this->database = ee('Database');
    }

    public function start(bool $reset = true): QueryRecorder
    {
        $this->recording = true;

        if ($reset) {
            $this->resetQueries();
        }

        $databaseLog = new Log('speedy');
        $databaseLog->saveQueries();

        $this->database->getConnection()->setLog($databaseLog);

        return $this;
    }

    public function stop(): QueryRecorder
    {
        $queries = $this->database->getConnection()->getLog()->getQueries();

        foreach ($queries as $query) {
            $this->queries[] = $query;
        }

        $this->recording = false;

        return $this;
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function countQueries(): int
    {
        return count($this->queries);
    }

    public function resetQueries(): QueryRecorder
    {
        $this->queries = [];

        return $this;
    }
}
