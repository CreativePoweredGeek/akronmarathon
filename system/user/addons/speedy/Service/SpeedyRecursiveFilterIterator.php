<?php

namespace BoldMinded\Speedy\Service;

use RecursiveFilterIterator;

class SpeedyRecursiveFilterIterator extends RecursiveFilterIterator
{
    /*
     * Ignore files that Speedy generates. Don't want to count them as cached items.
     */
    public static $FILTERS = [
        'utilities',
    ];

    public function accept(): bool {
        return !in_array(
            $this->current()->getFilename(),
            self::$FILTERS,
            true
        );
    }
}
