<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Bus\Events;

use BoldMinded\DataGrab\Dependency\Illuminate\Bus\Batch;
class BatchDispatched
{
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Bus\Batch  $batch  The batch instance.
     * @return void
     */
    public function __construct(public Batch $batch)
    {
    }
}
