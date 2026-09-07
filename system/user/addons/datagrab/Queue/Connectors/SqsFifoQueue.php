<?php

namespace BoldMinded\DataGrab\Queue\Connectors;

use BoldMinded\DataGrab\Dependency\Illuminate\Queue\SqsQueue;
use BoldMinded\Logit\Dependency\Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class SqsFifoQueue extends SqsQueue
{
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        try {
            $response = $this->sqs->sendMessage([
                'QueueUrl' => $this->getQueue($queue),
                'MessageBody' => $payload,
                'MessageGroupId' => uniqid(),
                'MessageDeduplicationId' => uniqid(),
            ]);

            return $response->get('MessageId');
        } catch (\Exception $exception) {
            ee('datagrab:Importer')->logger->log($exception->getMessage());
            return null;
        }
    }
}
