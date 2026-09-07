<?php

namespace BoldMinded\Speedy\Service;

class Diagnostics
{
    public QueryRecorder $queryRecorder;

    private string $driver;
    private float $startTime;
    private float $stopTime;

    public function __construct(QueryRecorder $queryRecorder)
    {
        $this->queryRecorder = $queryRecorder;
    }

    public function isDisabled(): bool
    {
        return ee()->config->item('speedy_diagnostics_enabled') === 'no';
    }

    public function isEnabled(): bool
    {
        return ee()->config->item('speedy_diagnostics_enabled') !== 'no';
    }

    public function shouldSaveQueries(): bool
    {
        return ee()->config->item('speedy_diagnostics_save_queries') === 'yes';
    }

    public function setDriver(string $driver): Diagnostics
    {
        $this->driver = $driver;

        return $this;
    }

    public function start(bool $reset = true): Diagnostics
    {
        if (REQ !== 'PAGE' || $this->isDisabled()) {
            return $this;
        }

        $this->startTimer();

        $this->queryRecorder->start($reset);

        return $this;
    }

    public function stop(string $key = ''): Diagnostics
    {
        if (REQ !== 'PAGE' || $this->isDisabled()) {
            return $this;
        }

        $this->stopTimer();

        $this->queryRecorder->stop();

        $this->saveDiagnostics($key);

        return $this;
    }

    private function startTimer(): void
    {
        $this->startTime = microtime(true);
    }

    private function stopTimer(): void
    {
        $this->stopTime = microtime(true);
    }

    private function totalExecutionTime(): float
    {
        return $this->stopTime - $this->startTime;
    }

    private function saveDiagnostics(string $key): Diagnostics
    {
        if ($this->isDisabled()) {
            return $this;
        }

        $queriesEncoded = '';

        if ($this->shouldSaveQueries()) {
            $queries = $this->queryRecorder->getQueries();
            $queriesEncoded = json_encode($queries);
        }

        $queryCount = $this->queryRecorder->countQueries();
        $executionTime = $this->totalExecutionTime();

        /** @var \BoldMinded\Speedy\Model\Diagnostics $model */
        $model = ee('Model')->get('speedy:Diagnostics');
        $record = $model->filter('key', $key)->first();

        if (!$record) {
            $record = ee('Model')->make('speedy:Diagnostics');
            $record->key = $key; // . '/' . $i;
            $record->site_id = ee()->config->item('site_id') ?: 1;
        }

        $record->query_count = $queryCount;
        $record->execution_time = $executionTime;
        $record->queries = $queriesEncoded;
        $record->driver = $this->driver;

        $record->save();

        return $this;
    }

    public function getDiagnostic(string $key)
    {
        return ee('Model')
            ->get('speedy:Diagnostics')
            ->filter('key', $key)
            ->first();
    }

    public function clearDiagnostics(string $key): bool
    {
        if ($this->isDisabled()) {
            return false;
        }

        ee('Model')
            ->get('speedy:Diagnostics')
            ->filter('key', $key)
            ->delete();

        return true;
    }

    public function clearAllDiagnostics(): bool
    {
        if ($this->isDisabled()) {
            return false;
        }

        ee('Model')
            ->get('speedy:Diagnostics')
            ->delete();

        return true;
    }
}
