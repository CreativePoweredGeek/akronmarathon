<?php

namespace BoldMinded\DataGrab\Service;

class FileResult
{
    public function __construct(
        public string $fileVar = '',
        public string $filePath = '',
        public bool $isNew = false,
    ) {}
}
