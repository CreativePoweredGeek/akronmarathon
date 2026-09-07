<?php

namespace BoldMinded\Speedy\Service\Logger;

trait LoggerAwareTrait
{
    /** @var \BoldMinded\Speedy\Service\Logger\Logger */
    protected $logger;

    /**
     * @return \BoldMinded\Speedy\Service\Logger\Logger
     */
    public function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = new ArrayLogger();
        }

        return $this->logger;
    }

    /**
     * @param \BoldMinded\Speedy\Service\Logger\Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }
}
