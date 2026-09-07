<?php

namespace BoldMinded\Speedy\Service\Logger;

interface LoggerAware
{
    /**
     * @return \BoldMinded\Speedy\Service\Logger\Logger
     */
    public function getLogger();

    /**
     * @param \BoldMinded\Speedy\Service\Logger\Logger $logger
     */
    public function setLogger(Logger $logger);
}
