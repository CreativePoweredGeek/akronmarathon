<?php

use BoldMinded\Speedy\Library\Basee\Setting;
use BoldMinded\Speedy\Library\Basee\Trial;
use BoldMinded\Speedy\Service\Diagnostics;
use BoldMinded\Speedy\Service\EscapeRepository;
use BoldMinded\Speedy\Service\Logger\ArrayLogger;
use BoldMinded\Speedy\Service\Logger\DeveloperLogger;
use BoldMinded\Speedy\Service\Logger\TemplateLogger;
use BoldMinded\Speedy\Service\Request\Facade;
use ExpressionEngine\Core\Provider;

if (!defined('SPEEDY_NAME')) {
    define('SPEEDY_VERSION', '1.16.1');
    define('SPEEDY_CLASS_NAME', 'speedy');
    define('SPEEDY_EXT', 'Speedy_ext');
    define('SPEEDY_BUILD_VERSION', '2f07a9f2');
    define('SPEEDY_TRIAL', file_exists(PATH_THIRD . 'speedy/Config/trial'));
    define('SPEEDY_NAME', 'Speedy' . (SPEEDY_TRIAL ? ' (Free Trial)' : ''));

    // Used for normal EE requests. PATH_THIRD will not be defined when executing unit tests.
    // SPEEDY_APP_PATH will be defined in the bootstrap.php
    if (defined('PATH_THIRD')) {
        define('SPEEDY_APP_PATH', PATH_THIRD . 'speedy/');
    }
}

return [
    'author'              => 'BoldMinded',
    'author_url'          => 'https://boldminded.com/add-ons/speedy',
    'name'                => 'Speedy', // Don't use SPEEDY_NAME here, will cause Trial to not install correctly.
    'description'         => 'An advanced cache module for Expression Engine.',
    'version'             => SPEEDY_VERSION,
    'namespace'           => 'BoldMinded\Speedy',
    'settings_exist'      => true,

    'requires' => [
        'php'   => '8.2',
        'ee'    => '6.4'
    ],

    'services'            => [
        'CacheBreaker' => 'Service\CacheBreaker',
        'DriverFactory' => 'Service\DriverFactory',
        'QueryRecorder' => 'Service\QueryRecorder',
    ],
    'services.singletons' => [
        'Diagnostics' => function ($provider) {
            return new Diagnostics($provider->make('QueryRecorder'));
        },
        'EscapeRepository' => function () {
            return new EscapeRepository();
        },
        'Logger' => function () {
            $debug = (int) ee()->config->item('debug');

            if (bool_config_item('speedy_enable_logging')) {
                // The TemplateLogger is only relevant for a front-end PAGE request
                // with a logged-in superadmin and the profiler on. ee()->session
                // does not exist in CLI/other contexts, so guard the dereference
                // (REQ alone is not enough — the session read happens first).
                $isProfilerPageRequest =
                    defined('REQ') && REQ === 'PAGE'
                    && isset(ee()->session)
                    && (int) (ee()->session->userdata['group_id'] ?? 0) === 1
                    && ee()->config->item('show_profiler') === 'y';

                if ($isProfilerPageRequest) {
                    return new TemplateLogger($debug);
                }

                ee()->load->library('logger');
                return new DeveloperLogger($debug);
            }

            return new ArrayLogger();
        },
        'Request' => function ($provider) {
            return new Facade(
                $provider->make('Logger')
            );
        },
        'Setting' => function () {
            $setting = new Setting('speedy_settings');
            $setting->setGlobalSettings(
                array_merge($setting->getGlobalSettings(), [
                    'settings_cache' => '',
                    'settings_purger' => '',
                ])
            );

            return $setting;
        },
        'Trial' => function ($provider) {
            /** @var Provider $provider */
            $setting = $provider->make('Setting');
            /** @var Trial $trialService */
            $trialService = new Trial();
            $trialService
                ->setTrialEnabled(SPEEDY_TRIAL)
                ->setInstalledDate($setting->get('installed_date'))
                ->setMessageTitle('Your free trial of Speedy has expired and is disabled.')
                ->setMessageBody('Please go to the <a href="https://boldminded.com">https://boldminded.com</a> to purchase the full version.');

            return $trialService;
        },
    ],
    'models' => [
        'CacheBreaking' => 'Model\CacheBreaking',
        'DatabaseDriver' => 'Model\DatabaseDriver',
        'DatabaseDriverStats' => 'Model\DatabaseDriverStats',
        'Diagnostics' => 'Model\Diagnostics',
        'DriverConfiguration' => 'Model\DriverConfiguration',
        'Tag' => 'Model\Tag',
        'Url' => 'Model\Url',
    ],
    // https://docs.expressionengine.com/latest/development/addon-setup-php-file.html#aliases
    'aliases' => [
        'ExpressionEngine\Model\Channel\ChannelEntry',
        'ExpressionEngine\Model\Comment\Comment',
        'ExpressionEngine\Service\Model\Collection',
        'ExpressionEngine\Service\Model\Query\Builder',
    ],
    'commands' => [
        'speedy:clear' => BoldMinded\Speedy\Commands\CommandClearCache::class,
        'speedy:clear-future' => BoldMinded\Speedy\Commands\CommandClearFutureEntries::class,
        'speedy:clear-expired' => BoldMinded\Speedy\Commands\CommandClearExpiredEntries::class,
        'speedy:clear-item' => BoldMinded\Speedy\Commands\CommandClearItem::class,
        'speedy:generate' => BoldMinded\Speedy\Commands\CommandGenerateCacheItems::class,
    ]
];
