<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$config['enable_devlog_alerts'] = 'n';
$config['index_page'] = '';
$config['site_license_key'] = '';
// ExpressionEngine Config Items
// Find more configs and overrides at
// https://docs.expressionengine.com/latest/general/system-configuration-overrides.html

$config['base_url'] = $_ENV['BASE_URL'];
$config['base_path'] = $_ENV['BASE_PATH'];
$config['hidden_template_indicator'] = '_';

$config['app_version'] = '7.5.26';
$config['encryption_key'] = '7570b072f1397b3970a8200569b10abb337b11df';
$config['session_crypt_key'] = '22125832e1750a8a8f6c9d58e31f58331a664a1d';
$config['database'] = array(
	'expressionengine' => array(
		'hostname' => 'localhost',
		'database' => $_ENV['DB_DATABASE'],
		'username' => $_ENV['DB_USERNAME'],
		'password' => $_ENV['DB_PASSWORD'],
		'dbprefix' => 'exp_',
		'char_set' => 'utf8mb4',
		'dbcollat' => 'utf8mb4_unicode_ci',
		'port'     => ''
	),
);
$config['show_ee_news'] = 'y';

// EOF