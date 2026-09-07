<?php

namespace BoldMinded\DataGrab\Queue\Jobs;

use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Queue\ShouldBeUnique;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Queue\ShouldQueue;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Queue\Job;

class DeleteFile extends AbstractJob implements ShouldQueue, ShouldBeUnique
{
    /**
     * @param Job $job
     * @param array $payload
     * @return bool
     */
    public function fire(Job $job, array $payload = []): bool
    {
        $this->job = $job;

        if (!$this->isValidImport($this->getImportId('delete'))) {
            return false;
        }

        if (!isset($payload['entryId']) || !$payload['entryId']) {
            return true;
        }

        ee('datagrab:Importer')->logger->log('DeleteFile PID: ' . getmypid());

        ee('Model')
            ->get('File')
            ->filter('file_id', $payload['entryId'])
            ->delete();

        ee('datagrab:Importer')->logger->log('Deleting file #' . $payload['entryId']);

        ee('datagrab:Importer')->recordDeletedEntryIds();

        $this->job->delete();

        return true;
    }
}
