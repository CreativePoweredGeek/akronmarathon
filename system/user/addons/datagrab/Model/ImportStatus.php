<?php

namespace BoldMinded\DataGrab\Model;

class ImportStatus
{
    const ABORTED = 'ABORTED';
    const COMPLETED = 'COMPLETED';
    const FAILED = 'FAILED';
    const NEW = 'NEW';
    const RUNNING = 'RUNNING';
    const WAITING = 'WAITING';
    const QUEUING = 'QUEUING';

    private int $added = 0;
    private int $deleted = 0;
    private int $error = 0;
    private int $iterator = 0;
    private int $updated = 0;

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->added + $this->updated;
    }

    /**
     * @return int
     */
    public function getAdded(): int
    {
        return $this->added;
    }

    /**
     * @param int $added
     * @return ImportStatus
     */
    public function setAdded(int $added): ImportStatus
    {
        $this->added = $added;

        return $this;
    }

    /**
     * @return ImportStatus
     */
    public function incrementAdded(): ImportStatus
    {
        $this->added = $this->added + 1;

        return $this;
    }

    /**
     * @return int
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * @param int $error
     * @return ImportStatus
     */
    public function setError(int $error): ImportStatus
    {
        $this->error = $error;

        return $this;
    }

    /**
     * @return ImportStatus
     */
    public function incrementError(): ImportStatus
    {
        $this->error = $this->error + 1;

        return $this;
    }

    /**
     * @return int
     */
    public function getDeleted(): int
    {
        return $this->deleted;
    }

    /**
     * @param int $deleted
     * @return ImportStatus
     */
    public function setDeleted(int $deleted): ImportStatus
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * @return int
     */
    public function getIterator(): int
    {
        return $this->iterator;
    }

    /**
     * @param int $iterator
     * @return ImportStatus
     */
    public function setIterator(int $iterator): ImportStatus
    {
        $this->iterator = $iterator;

        return $this;
    }

    /**
     * @return ImportStatus
     */
    public function reduceIterator(): ImportStatus
    {
        if ($this->iterator > 0) {
            $this->iterator = $this->iterator - 1;
        } else {
            $this->iterator = 0;
        }

        return $this;
    }

    /**
     * @return ImportStatus
     */
    public function incrementIterator(): ImportStatus
    {
        $this->iterator = $this->iterator + 1;

        return $this;
    }

    /**
     * @return int
     */
    public function getUpdated(): int
    {
        return $this->updated;
    }

    /**
     * @param int $updated
     * @return ImportStatus
     */
    public function setUpdated(int $updated): ImportStatus
    {
        $this->updated = $updated;

        return $this;
    }

    /**
     * @return ImportStatus
     */
    public function incrementUpdated(): ImportStatus
    {
        $this->updated = $this->updated + 1;

        return $this;
    }

    /**
     * @param int $id
     * @param string $status
     * @param int $lastRecord
     * @param int $totalRecords
     * @param int $errorRecords
     * @param int $importQueueSize
     * @param int $deleteQueueSize
     * @return string
     */
    public static function getDisplayStatus(
        int $id,
        string $status = '',
        int $lastRecord = 0,
        int $totalRecords = 0,
        int $errorRecords = 0,
        int $importQueueSize = 0,
        int $deleteQueueSize = 0
    ): string {
        $percentComplete = sprintf(' (%d%%)', self::getPercentage($lastRecord, $totalRecords));
        $count = sprintf(' %d of %d imported', $lastRecord, $totalRecords);
        $incompleteEmptyQueue =
            $importQueueSize === 0 &&
            $deleteQueueSize === 0 &&
            $lastRecord + $errorRecords < $totalRecords &&
            $status !== ImportStatus::COMPLETED;

        if ($incompleteEmptyQueue) {
            $status = 'WAITING: QUEUE EMPTY';
        }

        $template = '<div class="import-status" data-id="'. $id .'"><span class="st-%s">'. $status .'</span>%s</div>';

        if ($incompleteEmptyQueue) {
            return sprintf($template, 'pending', '');
        } elseif ($status === self::WAITING) {
            return sprintf($template, 'pending', $count . $percentComplete);
        } elseif ($status === self::RUNNING) {
            return sprintf($template, 'locked', $count . $percentComplete);
        } elseif ($status === self::QUEUING) {
            return sprintf($template, 'pending', '');
        } elseif ($status === self::FAILED) {
            return sprintf($template, 'locked', ' Check logs for details');
        } elseif ($status === self::NEW) {
            return sprintf($template, 'draft', '');
        } elseif ($status === self::ABORTED) {
            return sprintf($template, 'locked', '');
        }

        return sprintf($template, 'open', '');
    }

    /**
     * @param int $count
     * @param int $total
     * @return int
     */
    public static function getPercentage(int $count, int $total): int
    {
        if ($count === 0 || $total === 0) {
            return 0;
        }

        return round($count / $total * 100);
    }
}
