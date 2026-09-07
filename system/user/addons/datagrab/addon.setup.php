<?php
// Build: {DATAGRAB_BUILD_VERSION}
require_once PATH_THIRD . 'datagrab/vendor-build/autoload.php';

use BoldMinded\DataGrab\Dependency\Illuminate\Events\Dispatcher;
use BoldMinded\DataGrab\Dependency\Illuminate\Queue\Worker;
use BoldMinded\DataGrab\Dependency\Illuminate\Queue\WorkerOptions;
use BoldMinded\DataGrab\Dependency\Illuminate\Database\Capsule\Manager as DatabaseCapsuleManager;
use BoldMinded\DataGrab\Dependency\Illuminate\Queue\QueueManager;
use BoldMinded\DataGrab\Dependency\Litzinger\Basee\RequestCache;
use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Setting;
use BoldMinded\DataGrab\Model\ImportStatus;
use BoldMinded\DataGrab\Queue\Drivers\DatabaseDriver;
use BoldMinded\DataGrab\Queue\Drivers\RedisDriver;
use BoldMinded\DataGrab\Queue\Drivers\SQSDriver;
use BoldMinded\DataGrab\Queue\Exceptions\QueueException;
use BoldMinded\DataGrab\Queue\Subscribers\QueueSubscriber;
use BoldMinded\DataGrab\Service\DataGrabLoader;
use BoldMinded\DataGrab\Service\Importer;
use BoldMinded\DataGrab\Service\Logger;
use BoldMinded\DataGrab\Service\QueueStatus;
use BoldMinded\DataGrab\Service\UrlGuard;

if (!defined('DATAGRAB_NAME')) {
    define('DATAGRAB_VERSION', '6.2.0');
    define('DATAGRAB_BUILD_VERSION', 'ad796014');

    if (defined('BASE')) {
        define('DATAGRAB_URL', BASE . AMP . '?/cp/addons/settings/datagrab');
        define('DATAGRAB_PATH', '?/cp/addons/settings/datagrab');
    }

    define('DATAGRAB_WORDPRESS', file_exists(PATH_THIRD . 'datagrab/config/wordpress'));
    define('DATAGRAB_NAME', 'DataGrab' . (DATAGRAB_WORDPRESS ? ' (WordPress Edition)' : ''));
    define('DATAGRAB_CLASS_NAME', 'datagrab');
}

return [
    'author' => 'BoldMinded, LLC',
    'author_url' => 'http://boldminded.com/add-ons/datagrab',
    'name' => DATAGRAB_NAME,
    'description' => 'Easily import data into ExpressionEngine channel entries',
    'version' => DATAGRAB_VERSION,
    'namespace' => 'BoldMinded\DataGrab',
    'settings_exist' => true,

    'requires' => [
        'php'   => '8.2',
        'ee'    => '6.4'
    ],

    'services.singletons' => [
        'Cache' => function () {
            return (new RequestCache())->setNamespace('datagrab');
        },
        'Logger' => function () {
            ee()->load->library('logger');

            return new Logger(
                ee()->logger,
                ee()->config->item('datagrab_log_type'),
                ee()->config->item('datagrab_log_file') ?: sprintf('%sDataGrab-import.log', PATH_CACHE),
                bool_config_item('datagrab_rotate_log')
            );
        },
        'UrlGuard' => function () {
            return new UrlGuard(ee('datagrab:Logger'));
        },
        'Importer' => function () {
            ee()->load->library('addons');

            $logger = ee('datagrab:Logger');

            if (isset(ee()->TMPL)) {
                $template = ee()->TMPL;
            } else {
                ee()->load->library('template');
                $template = ee()->template; // aka TMPL
            }

            // Check if Queue addon provides the QueueService
            if (ee('Addon')->get('queue')?->isInstalled() && ee()->has('queue:QueueService')) {
                $queueService = ee('queue:QueueService');
                $queueManager = $queueService->getQueueManager();
                $queueWorker = $queueService->getWorker();
                $queueWorkerOptions = $queueService->getWorkerOptions();
            } else {
                // Use DataGrab's own scoped dependencies
                $queueManager = ee('datagrab:QueueManager');
                $queueWorker = ee('datagrab:QueueWorker');
                $queueWorkerOptions = ee('datagrab:QueueWorkerOptions');
            }

            $importer = new Importer(
                addons: ee()->addons,
                config: ee()->config,
                extensions: ee()->extensions,
                functions: ee()->functions,
                session: ee()->session,
                load: ee()->load,
                template: $template,
                db: ee()->db,
                logger: $logger,
                loader: new DataGrabLoader,
                importStatus: new ImportStatus,
                queueManager: $queueManager,
                queueWorker: $queueWorker,
                queueWorkerOptions: $queueWorkerOptions,
            );

            $importer->initialise_types();

            return $importer;
        },
        'Setting' => function () {
            return new Setting('datagrab_settings');
        },
        'DatabaseConfig' => function () {
            if (ee('Addon')->get('queue')?->isInstalled()) {
                return ee('queue:DatabaseConfig');
            }

            return  [
                'driver' => 'mysql',
                'host' => ee('db')->hostname,
                'database' => ee('db')->database,
                'port' => ee('db')->port,
                'username' => ee('db')->username,
                'password' => ee('db')->password,
                'charset' => ee('db')->char_set,
                'collation' => ee('db')->dbcollat,
                'prefix' => ee('db')->dbprefix . 'datagrab_',
            ];
        },
        'DatabaseManager' => function ($provider) {
            if (ee('Addon')->get('queue')?->isInstalled()) {
                return ee('queue:DatabaseManager');
            }

            $databaseManager = new DatabaseCapsuleManager;
            $databaseManager->addConnection($provider->make('DatabaseConfig'));

            return $databaseManager;
        },
        'QueueManager' => function ($provider) {
            if (ee('Addon')->get('queue')?->isInstalled()) {
                return ee('queue:QueueManager');
            }

            $config = ee()->config->item('datagrab') ?: [];

            // @todo this is incomplete and unsupported
             if (isset($config['driver']) && $config['driver'] === 'sqs') {
                 return (new SQSDriver($provider, $config['sqs_config'] ?? []))->getQueueManager();
             }

            if (isset($config['driver']) && $config['driver'] === 'redis') {
                return (new RedisDriver($provider, ['default' => $config['redis_config'] ?? []]))->getQueueManager();
            }

            return (new DatabaseDriver($provider))->getQueueManager();
        },
        'QueueWorker' => function ($provider) {
            if (ee('Addon')->get('queue')?->isInstalled()) {
                return ee('queue:QueueWorker');
            }

            /** @var QueueManager $queueManager */
            $queueManager = $provider->make('QueueManager');

            $dispatcher = new Dispatcher($queueManager->getContainer());
            $dispatcher->subscribe(new QueueSubscriber);

            return new Worker(
                $queueManager,
                $dispatcher,
                new QueueException,
                function () {},
                function () {}
            );
        },
        'QueueWorkerOptions' => function () {
            if (ee('Addon')->get('queue')?->isInstalled()) {
                return ee('queue:QueueWorkerOptions');
            }

            // Determine how long a worker can run without timing out based on the PHP settings.
            $maxExecutionTime = (ini_get('max_execution_time') ?: 130) - 10;

            // Set minimum timeout, especially if PHP's CLI config does not have max_execution_time set.
            if ($maxExecutionTime < 30) {
                $maxExecutionTime = 30;
            }

            return new WorkerOptions(
                'default', // name
                0, // backoff
                1024, // memory
                $maxExecutionTime, // timeout
                3, // sleep
                3, // max tries
                \false, // force
                \true, // stop when empty
                0, // max jobs or limit
                $maxExecutionTime, // max time
                0 // rest
            );
        },
        'QueueStatus' => function () {
            return new QueueStatus();
        }
    ],

    'models' => [
        'Import' => 'Model\Import',
        'Endpoint' => 'Model\Endpoint',
    ],

    'commands' => [
        'import:run' => BoldMinded\DataGrab\Commands\CommandImportRun::class,
        'import:reset' => BoldMinded\DataGrab\Commands\CommandImportReset::class,
    ]
];
