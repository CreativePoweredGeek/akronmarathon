<?php

namespace BoldMinded\Speedy\Service\Purgers;

class Cloudflare implements Purger
{
    private string $apiToken;
    private string $zoneId;
    private string $apiEndpoint = 'https://api.cloudflare.com/client/v4/zones/';

    public function __construct(array $settings = [])
    {
        $this->apiToken = $settings['api_token'] ?? '';
        $this->zoneId = $settings['zone_id'] ?? '';
    }

    public function getName(): string
    {
        return 'Cloudflare';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function settings(): array
    {
        return [
            'Cloudflare Settings' => [
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
                    'title'  => 'Zone ID',
                    'desc'   => '',
                    'fields' => [
                        'purger[zone_id]' => [
                            'type' => 'text',
                            'required' => true,
                            'value' => $this->zoneId,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function purgeAll(): bool
    {
        return $this->makeRequest([
            'purge_everything' => true
        ]);
    }

    public function purgeUrl(string $url): bool
    {
        return $this->makeRequest([
            'files' => [$url],
        ]);
    }

    public function purgeUrls(array $urls = []): bool
    {
        if (count($urls) === 0) {
            return false;
        }

        return $this->makeRequest([
            'files' => $urls,
        ]);
    }

    /**
     * @throws \Exception
     */
    private function makeRequest(array $data): bool
    {
        try {
            $endpoint = $this->apiEndpoint . $this->zoneId . '/purge_cache';

            $ch = curl_init($endpoint);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiToken,
                    'Content-Type: application/json'
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($response === false) {
                ee('speedy:Logger')->error(sprintf(
                    'Cloudflare purge request to %s failed: %s',
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

            $succeeded = $httpCode === 200 && isset($result['success']) && $result['success'] === true;

            if (!$succeeded) {
                ee('speedy:Logger')->error(sprintf(
                    'Cloudflare purge request to %s failed with HTTP code %d: %s',
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
