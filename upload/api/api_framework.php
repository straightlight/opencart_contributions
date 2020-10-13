<?php
/**
 * @package		 OpenCart API
 * @author		 Straightlight (Originally owned by Daniel Kerr)
 * @copyright	 Copyright (c) 2005 - 2019, OpenCart, Ltd. (https://www.opencart.com/)
 * @license		 https://opensource.org/licenses/GPL-3.0
 * @link		 https://www.opencart.com
 * @author link  https://github.com/straightlight/opencart_contributions/tree/master/upload/api
*/

// Registry
$registry = new Registry();

// Config
$config = new Config();
$config->load('default');
$config->load($application_config);
$registry->set('config', $config);

// Log
$log = new Log($registry->get('config')->get('error_filename'));
$registry->set('log', $log);

date_default_timezone_set($registry->get('config')->get('date_timezone'));

$is_ajax = 'XMLHttpRequest' == ($registry->get('request')->server['HTTP_X_REQUESTED_WITH'] ?? '');

set_error_handler(function($code, $message, $file, $line) use($log, $registry, $is_ajax) {
	// error suppressed with @
	if (error_reporting() === 0) {
		return false;
	}

	switch ($code) {
		case E_NOTICE:
		case E_USER_NOTICE:
			$error = 'Notice';
			break;
		case E_WARNING:
		case E_USER_WARNING:
			$error = 'Warning';
			break;
		case E_ERROR:
		case E_USER_ERROR:
			$error = 'Fatal Error';
			break;
		default:
			$error = 'Unknown';
			break;
	}

	if ($registry->get('config')->get('error_display') && !$is_ajax) {
		echo '<b>API :: ' . $error . '</b>: ' . $message . ' in <b>' . $file . '</b> on line <b>' . $line . '</b>';
	}

	if ($registry->get('config')->get('error_log')) {
		$log->write('API :: PHP ' . $error . ':  ' . $message . ' in ' . $file . ' on line ' . $line);
	}

	return true;
});

// Event
$event = new Event($registry);
$registry->set('event', $event);

// Event Register
if ($registry->get('config')->has('action_event')) {
	foreach ($registry->get('config')->get('action_event') as $key => $value) {
		foreach ($value as $priority => $action) {
			$registry->get('event')->register($key, new Action($action), $priority);
		}
	}
}

// Loader
$loader = new loader($registry);
$registry->set('load', $loader);

// API Loader
$apiLoader = new apiLoader($registry);
$registry->set('apiLoad', $apiLoader);

// Request
$registry->set('request', new Request());

// Response
$response = new Response();
$response->addHeader('Content-Type: text/html; charset=utf-8');
$response->setCompression($registry->get('config')->get('config_compression'));
$registry->set('response', $response);

// Database
if ($registry->get('config')->get('db_autostart')) {
	$registry->set('db', new DB($registry->get('config')->get('db_engine'), $registry->get('config')->get('db_hostname'), $registry->get('config')->get('db_username'), $registry->get('config')->get('db_password'), $registry->get('config')->get('db_database'), $registry->get('config')->get('db_port')));
}

// Session
$session = new Session($registry->get('config')->get('session_engine'), $registry);
$registry->set('session', $session);

// Cache
$registry->set('cache', new Cache($registry->get('config')->get('cache_engine'), $registry->get('config')->get('cache_expire')));

// Language
$language = new Language($registry->get('config')->get('language_directory'));
$registry->set('language', $language);

// Config Autoload
if ($registry->get('config')->has('config_autoload')) {
	foreach ($registry->get('config')->get('config_autoload') as $value) {
		$registry->get('load')->config($value);
	}
}

// Language Autoload
if ($registry->get('config')->has('language_autoload')) {
	foreach ($registry->get('config')->get('language_autoload') as $value) {
		$registry->get('load')->language($value);
	}
}

// Library Autoload
if ($registry->get('config')->has('library_autoload')) {
	foreach ($registry->get('config')->get('library_autoload') as $value) {
		$registry->get('load')->library($value);
	}
}

// Model Autoload
if ($registry->get('config')->has('model_autoload')) {
	foreach ($registry->get('config')->get('model_autoload') as $value) {
		$registry->get('load')->model($value);
	}
}

// Route
$route = new Router($registry);

// Pre Actions
if ($registry->get('config')->has('action_pre_action')) {
	foreach ($registry->get('config')->get('action_pre_action') as $value) {
		$route->addPreAction(new Action($value));
	}
}

// Dispatch
$route->dispatch(new Action($registry->get('config')->get('action_router')), new Action($registry->get('config')->get('action_error')));

// API login

// If user is not loading this file with JSON POST request,
// we reject the API transaction directly from the browser
// with an error message.
if (!$is_ajax) {
	exit('You are not authorized to view this page!');
	
// Otherwise, we instantiate the API lookups.
} else {
	if (!isset($registry->get('session')->data['api_id'])) {
		$registry->get('apiLoad')->controller('common/login');
	} else {
		require('store_startup.php');	
	}
}
