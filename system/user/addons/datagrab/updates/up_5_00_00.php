<?php

use BoldMinded\DataGrab\Dependency\Illuminate\Database\Schema\Blueprint;
use BoldMinded\DataGrab\Dependency\Litzinger\Basee\Update\AbstractUpdate;

class Update_5_00_00 extends AbstractUpdate
{
    public function doUpdate()
    {
        // Using Laravel here to create the tables
        $database = ee('datagrab:DatabaseManager');
        $schema = $database->getConnection()->getSchemaBuilder();

        if (!ee('db')->table_exists('datagrab_failed_jobs')) {
            $schema->create('failed_jobs', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (!ee('db')->table_exists('datagrab_jobs')) {
            $schema->create('jobs', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

//        $tablePrefix = ee()->db->dbprefix;
//
//        $tables = [
//            $tablePrefix.'datagrab_jobs' => "CREATE TABLE `{$tablePrefix}datagrab_jobs` (
//                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
//                `queue` varchar(255) NOT NULL,
//                `payload` longtext NOT NULL,
//                `attempts` tinyint(3) unsigned NOT NULL,
//                `reserved_at` int(10) unsigned DEFAULT NULL,
//                `available_at` int(10) unsigned NOT NULL,
//                `created_at` int(10) unsigned NOT NULL,
//                PRIMARY KEY (`id`),
//                KEY `jobs_queue_index` (`queue`)
//            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
//
//            $tablePrefix.'datagrab_failed_jobs' => "CREATE TABLE `{$tablePrefix}datagrab_failed_jobs` (
//                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
//                `uuid` varchar(255) NOT NULL,
//                `connection` text NOT NULL,
//                `queue` text NOT NULL,
//                `payload` longtext NOT NULL,
//                `exception` longtext NOT NULL,
//                 `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
//                PRIMARY KEY (`id`),
//                UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
//            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
//        ];

//        foreach ($tables as $tableName => $tableDefinition) {
//            if (!ee()->db->table_exists($tableName)) {
//                ee()->db->query($tableDefinition);
//            }
//        }

        if (!ee('db')->field_exists('delete_records', 'datagrab')) {
            ee()->load->dbforge();
            ee()->dbforge->add_column('datagrab', [
                'delete_records' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'default' => 0
                ],
            ]);
        }

        $this->addActions([
            [
                'class' => 'Datagrab',
                'method' => 'fetch_queue_status',
            ],
            [
                'class' => 'Datagrab',
                'method' => 'purge_queue',
            ]
        ]);

        $this->updateSettings();
    }

    private function updateSettings()
    {
        // Fetch import settings
        $imports = ee('db')->get('datagrab')->result_array();

        if (empty($imports)) {
            return;
        }

        foreach ($imports as $import) {
            $settings = unserialize($import['settings']);

            if (!isset($settings['import']['limit']) || $settings['import']['limit'] < 50) {
                $settings['import']['limit'] = 50;
            }

            ee('db')
                ->update('datagrab', [
                    'settings' => serialize($settings)
                ], [
                    'id' => $import['id']
                ]);
        }

    }
}
