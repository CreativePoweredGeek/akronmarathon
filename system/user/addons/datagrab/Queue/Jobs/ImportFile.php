<?php

namespace BoldMinded\DataGrab\Queue\Jobs;

use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Queue\ShouldBeUnique;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Queue\ShouldQueue;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Queue\Job as DataGrabJobContract;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\Job as QueueJobContract;

class ImportFile extends AbstractJob implements ShouldQueue, ShouldBeUnique
{
    /**
     * @param Job $job
     * @param array $payload
     * @return bool
     */
    public function fire(DataGrabJobContract|QueueJobContract $job, array $payload = []): bool
    {
        $this->job = $job;

        if (!$this->isValidImport($this->getImportId())) {
            return false;
        }

        $importer = ee('datagrab:Importer');

        $result = $importer->setup(
            $importer->datatypes[$this->settings['import']['type']],
            $this->settings
        )->importFile($payload);

        if ($result === true) {
            $this->job->delete();
        }

        ee('datagrab:Importer')->logger->log('ImportEntry PID: ' . getmypid());

        $this->handleOutput((int)$this->settings['import']['id']);

        return $result;
    }
}
