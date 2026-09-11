<?php

require_once 'autoload.php';
$addonJson = json_decode(file_get_contents(__DIR__ . '/addon.json'));

if (! defined('LOW_EVENTS_VERSION')) {
    define('LOW_EVENTS_VERSION', $addonJson->version);
}

return array(
    'name'              => $addonJson->name,
    'description'       => $addonJson->description,
    'version'           => $addonJson->version,
    'namespace'         => $addonJson->namespace,
    'author'            => 'EEHarbor',
    'author_url'        => 'http://eeharbor.com/low_events',
    'docs_url'          => 'http://eeharbor.com/low_events/documentation',
    'settings_exist'    => true,
);
