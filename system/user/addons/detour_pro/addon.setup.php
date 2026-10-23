<?php

require_once 'autoload.php';
$addonJson = json_decode(file_get_contents(__DIR__ . '/addon.json'));

return array(
    'name'           => $addonJson->name,
    'description'    => $addonJson->description,
    'version'        => $addonJson->version,
    'namespace'      => $addonJson->namespace,
    'author'         => 'EEHarbor',
    'author_url'     => 'https://eeharbor.com/detour_pro',
    'docs_url'       => 'http://eeharbor.com/detour-pro/documentation',
    'settings_exist' => true,
    'services.singletons' => array(
        'AnalyticsService' => function ($ee) {
            require_once __DIR__ . '/Service/AnalyticsService.php';
            return new \EEHarbor\DetourPro\Service\AnalyticsService();
        },
        'ChartDataService' => function ($ee) {
            require_once __DIR__ . '/Service/ChartDataService.php';
            return new \EEHarbor\DetourPro\Service\ChartDataService();
        },
    ),
);
