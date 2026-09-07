<?php

namespace BoldMinded\Speedy\Service\Purgers;

class Fastly implements Purger
{
    private string $apiToken;
    private string $serviceId;
    private string $apiEndpoint = 'https://api.fastly.com/service/';

    public function __construct(array $settings = [])
    {
        $this->apiToken = $settings['api_token'] ?? '';
        $this->serviceId = $settings['service_id'] ?? '';
    }

    public function getName(): string
    {
        return 'Fastly';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function settings(): array
    {
        return [
            'Fastly Settings' => [
                [
                    'title'  => 'API Token',
                    'desc'   => '',
                    'fields' => [
                        'purger[api_token]' => [
                            'type' => 'text',
                            'required' => true,
                            'value' => $this->apiToken,
                        ],
                    ],
                ],
                [
                    'title'  => 'Service ID',
                    'desc'   => '',
                    'fields' => [
                        'purger[service_id]' => [
                            'type' => 'text',
                            'required' => true,
                            'value' => $this->serviceId,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @throws \Exception
     */
    public function purgeAll(): bool
    {
        $endpoint = $this->apiEndpoint . $this->serviceId . '/purge_all';

        return $this->makeRequest($endpoint);
    }

    /**
     * @throws \Exception
     */
    public function purgeUrl(string $url): bool
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '/';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $endpoint = 'https://api.fastly.com/purge/' . $host . $path . $query;

        return $this->makeRequest($endpoint);
    }

    /**
     * @throws \Exception
     */
    public function purgeUrls(array $urls = []): bool
    {
        if (count($urls) === 0) {
            return false;
        }

        $allSucceeded = true;

        foreach ($urls as $url) {
            if (!$this->purgeUrl($url)) {
                $allSucceeded = false;
            }
        }

        return $allSucceeded;
    }

    /**
     * @throws \Exception
     */
    private function makeRequest(
        string $endpoint,
        string $method = 'POST'
    ): bool {
        try {
            $ch = curl_init($endpoint);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => [
                    'Fastly-Key: ' . $this->apiToken,
                    'Accept: application/json',
                    'Fastly-Soft-Purge: 1',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($response === false) {
                ee('speedy:Logger')->error(sprintf(
                    'Fastly purge request to %s failed: %s',
                    $endpoint,
                    $curlError ?: 'unknown cURL error'
                ));

                return false;
            }

            $result = json_decode($response, true);

            ee('speedy:Logger')->debug(json_encode([
                'httpCode' => $httpCode,
                'result' => $result,
                'endpoint' => $endpoint,
            ], JSON_PRETTY_PRINT));

            $succeeded = $httpCode === 200 && isset($result['status']) && $result['status'] === 'ok';

            if (!$succeeded) {
                ee('speedy:Logger')->error(sprintf(
                    'Fastly purge request to %s failed with HTTP code %d: %s',
                    $endpoint,
                    $httpCode,
                    $response
                ));
            }

            return $succeeded;
        } catch (\Exception $exception) {
            ee('speedy:Logger')->error($exception->getMessage());

            return false;
        }
    }
}
