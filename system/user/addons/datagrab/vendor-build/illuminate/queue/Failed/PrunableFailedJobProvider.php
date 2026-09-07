<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Queue\Failed;

use DateTimeInterface;
interface PrunableFailedJobProvider
{
    /**
     * Prune all of the entries older than the given date.
     *
     * @param  \DateTimeInterface  $before
     * @return int
     */
    public function prune(DateTimeInterface $before);
}
