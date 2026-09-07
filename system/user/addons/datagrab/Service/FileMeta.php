<?php

namespace BoldMinded\DataGrab\Service;

class FileMeta
{
    public function __construct(
        public string $credit = '',
        public string $description = '',
        public string $location = '',
    ) {}
}
