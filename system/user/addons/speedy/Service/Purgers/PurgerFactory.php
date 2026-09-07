<?php

namespace BoldMinded\Speedy\Service\Purgers;

final class PurgerFactory
{
    private string $providerName;
    private array $settings;

    public function __construct(
        string $providerName = '',
        array $settings = []
    ) {
        $this->providerName = $providerName;
        $this->settings = $settings;
    }

    public static function providers(): array
    {
        /*
         Allow for additional providers.

         $config['speedy_purgers'] = [
            \Path\To\My\Files\SomeReverseProxyService::class => 'Service Name',
        ];

        */
        $defaultProviders = [
            Cloudflare::class => 'Cloudflare',
            Fastly::class => 'Fastly',
        ];

        $additionalProviders = ee()->config->item('speedy_purgers') ?: [];

        return array_merge($defaultProviders, $additionalProviders);
    }

    public function create(): Purger
    {
        $providers = self::providers();

        if (!$this->providerName || empty($this->settings)) {
            $setting = ee('speedy:Setting');
            $settings = json_decode($setting->get('settings_purger'), true);

            if (!is_array($settings) || empty($settings)) {
                return new Dummy();
            }

            $this->settings = $settings;
            $this->providerName = $this->settings['provider'] ?? '';
        }

        if (array_key_exists($this->providerName, $providers)) {
            return new $this->providerName($this->settings);
        }

        return new Dummy();
    }
}
