<?php

namespace BoldMinded\DataGrab\Service;

use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Queue\Queue as DataGrabQueueContract;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\Queue as QueueContract;
use BoldMinded\DataGrab\Model\ImportStatus;

class QueueStatus
{
    /**
     * @param string $importId
     * @return array
     */
    public function fetch(string $importId = ''): array
    {
        $db = ee('db');

        // https://stackoverflow.com/questions/917640/any-way-to-select-without-causing-locking-in-mysql
        // $db->query('SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        $query = $db->select([
            'id',
            'total_records',
            'last_record',
            'total_delete_records',
            'last_delete_record',
            'error_records',
            'status'
        ]);


        if ($importId) {
            $query->where('id', $importId);
        }

        $result = $query->get('datagrab');
        // $db->query('COMMIT');

        $collection = [];

        foreach ($result->result_array() as $import) {
            $importQueueSize = $this->getQueueConnection()->size(ee('datagrab:Importer')->getImportQueueName($import['id']));
            $deleteQueueSize = $this->getQueueConnection()->size(ee('datagrab:Importer')->getDeleteQueueName($import['id']));

            $import['display_status'] = ImportStatus::getDisplayStatus(
                $import['id'],
                $import['status'],
                $import['last_record'],
                $import['total_records'],
                $import['error_records'],
                $importQueueSize,
                $deleteQueueSize
            );

            $import['import_queue_size'] = $importQueueSize;
            $import['delete_queue_size'] = $deleteQueueSize;

            $collection[$import['id']] = $import;
        }

        return $collection;
    }

    /**
     * @param string $queueName
     * @return int
     */
    public function clear(string $queueName): int
    {
        return $this->getQueueConnection()->clear($queueName);
    }

    /**
     * @return \Illuminate\Contracts\Queue\Queue
     */
    private function getQueueConnection(): DataGrabQueueContract|QueueContract
    {
        return ee('datagrab:QueueManager')->connection('default');
    }
}
