<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Database\Eloquent\Concerns;

use BoldMinded\DataGrab\Dependency\Illuminate\Support\Str;
trait HasVersion4Uuids
{
    use HasUuids;
    /**
     * Generate a new UUID (version 4) for the model.
     *
     * @return string
     */
    public function newUniqueId()
    {
        return (string) Str::orderedUuid();
    }
}
