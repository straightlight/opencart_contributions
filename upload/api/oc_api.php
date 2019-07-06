<?php
// Error Reporting
//error_reporting(E_ALL);

$json = array();

if (file_exists('../config.php')) {
	require_once('../config.php');
}

// Check Version
if (version_compare(phpversion(), '7.0.0', '<') == true) {
	exit('PHP7.0+ Required');
}

if (!ini_get('date.timezone')) {
	date_default_timezone_set('UTC');
}

// Windows IIS Compatibility
if (!isset($_SERVER['DOCUMENT_ROOT'])) {
	if (isset($_SERVER['SCRIPT_FILENAME'])) {
		$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', substr($_SERVER['SCRIPT_FILENAME'], 0, 0 - strlen($_SERVER['PHP_SELF'])));
	}
}

if (!isset($_SERVER['DOCUMENT_ROOT'])) {
	if (isset($_SERVER['PATH_TRANSLATED'])) {
		$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', substr(str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED']), 0, 0 - strlen($_SERVER['PHP_SELF'])));
	}
}

if (!isset($_SERVER['REQUEST_URI'])) {
	$_SERVER['REQUEST_URI'] = substr($_SERVER['PHP_SELF'], 1);

	if (isset($_SERVER['QUERY_STRING'])) {
		$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
	}
}

if (!isset($_SERVER['HTTP_HOST'])) {
	$_SERVER['HTTP_HOST'] = getenv('HTTP_HOST');
}

// Check if SSL
if ((isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) || (isset($_SERVER['HTTPS']) && (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443))) {
	$_SERVER['HTTPS'] = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
	$_SERVER['HTTPS'] = true;
} else {
	$_SERVER['HTTPS'] = false;
}

// Modification Override
function modification($filename) {
	if (defined('DIR_CATALOG')) {
		$file = DIR_MODIFICATION . 'admin/' .  substr($filename, strlen(DIR_APPLICATION));
	} elseif (defined('DIR_OPENCART')) {
		$file = DIR_MODIFICATION . 'install/' .  substr($filename, strlen(DIR_APPLICATION));
	} else {
		$file = DIR_MODIFICATION . 'catalog/' . substr($filename, strlen(DIR_APPLICATION));
	}

	if (substr($filename, 0, strlen(DIR_SYSTEM)) == DIR_SYSTEM) {
		$file = DIR_MODIFICATION . 'system/' . substr($filename, strlen(DIR_SYSTEM));
	}

	if (is_file($file)) {
		return $file;
	}

	return $filename;
}

// Autoloader
if (is_file(DIR_STORAGE . 'vendor/autoload.php')) {
	require_once(DIR_STORAGE . 'vendor/autoload.php');
}

function library($class) {
	$file = DIR_SYSTEM . 'library/' . str_replace('\\', '/', strtolower($class)) . '.php';

	if (is_file($file)) {
		include_once(modification($file));

		return true;
	} else {
		return false;
	}
}

spl_autoload_register('library');
spl_autoload_extensions('.php');

// Engine
require_once(modification(DIR_SYSTEM . 'engine/action.php'));
require_once(modification(DIR_SYSTEM . 'engine/controller.php'));
require_once(modification(DIR_SYSTEM . 'engine/event.php'));
require_once(modification(DIR_SYSTEM . 'engine/router.php'));
require_once(modification(DIR_SYSTEM . 'engine/loader.php'));
require_once(modification(DIR_SYSTEM . 'engine/model.php'));
require_once(modification(DIR_SYSTEM . 'engine/registry.php'));
require_once(modification(DIR_SYSTEM . 'engine/proxy.php'));

// Helper
require_once(DIR_SYSTEM . 'helper/general.php');
require_once(DIR_SYSTEM . 'helper/utf8.php');

// Registry
$registry = new Registry();

// Config
$config = new Config();
$config->load('default');
$registry->set('config', $config);

// Log
$log = new Log($registry->get('config')->get('error_filename'));
$registry->set('log', $log);

date_default_timezone_set($registry->get('config')->get('date_timezone'));

set_error_handler(function($code, $message, $file, $line) use($log, $registry) {
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

	if ($registry->get('config')->get('error_display')) {
		echo '<b>' . $error . '</b>: ' . $message . ' in <b>' . $file . '</b> on line <b>' . $line . '</b>';
	}

	if ($registry->get('config')->get('error_log')) {
		$log->write('PHP ' . $error . ':  ' . $message . ' in ' . $file . ' on line ' . $line);
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
			$event->register($key, new Action($action), $priority);
		}
	}
}

// Loader
$loader = new Loader($registry);
$registry->set('load', $loader);

$registry->set('db', new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE));

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

// API login
$api_info = $registry->get('db')->query("SELECT * FROM `" . DB_PREFIX . "api` WHERE api_id = '" . (int)$registry->get('config')->get('config_api_id') . "'");

if (!$api_info->num_rows) {
	$json['error']['token'] = 'No API Access!';
} else {
	$api_token = '';
		
	$api_session = new Session($registry->get('config')->get('session_engine'), $registry);

	$api_session->start();
		
	$registry->get('db')->query("DELETE FROM `" . DB_PREFIX . "api_session` WHERE session_id = '" . $registry->get('db')->escape($session->getId()) . "'");

	$api_ip_query = $registry->get('db')->query("SELECT * FROM `" . DB_PREFIX . "api_ip` WHERE ip = '" . $registry->get('db')->escape($registry->get('request')->server['REMOTE_ADDR']) . "'");
			
	if (!$api_ip_query->num_rows) {
		$registry->get('db')->query("INSERT INTO `" . DB_PREFIX . "api_ip` SET api_id = '" . (int)$api_info['api_id'] . "', ip = '" . $registry->get('db')->escape($registry->get('request')->server['REMOTE_ADDR']) . "'");
	}
			
	$registry->get('db')->query("INSERT INTO `" . DB_PREFIX . "api_session` SET api_id = '" . (int)$api_info->row['api_id'] . "', session_id = '" . $registry->get('db')->escape($session->getId()) . "', ip = '" . $registry->get('db')->escape($registry->get('request')->server['REMOTE_ADDR']) . "', date_added = NOW(), date_modified = NOW()");

	$api_session->data['oc_api_id'] = $api_info->row['api_id'];
		
	$api_token = $api_session->getId();
		
	if (!$api_token) {
		$json['error']['token'] = 'Invalid API Token!';
	} else {
		$registry->get('load')->language('api/login');
		
		$registry->get('load')->model('account/api');
		
		$api_info = array();
		
		// Login with API Key
		if (isset($registry->get('request')->post['username'])) {
			$api_info = $registry->get('model_account_api')->login($registry->get('request')->post['username'], $registry->get('request')->post['key']);
		}
		
		if (!$api_info) {
			$json['error']['login'] = $registry->get('language')->get('error_login');
		} else {
			// Check if IP is allowed
			$ip_data = array();
	
			$results = $registry->get('model_account_api')->getApiIps($api_info['api_id']);
	
			foreach ($results as $result) {
				$ip_data[] = trim($result['ip']);
			}
			
			$json['error']['ip'] = $registry->get('language')->get('error_permission');
	
			if (!in_array($registry->get('request')->server['REMOTE_ADDR'], $ip_data)) {
				$json['error']['ip'] = sprintf($registry->get('language')->get('error_ip'), $registry->get('request')->server['REMOTE_ADDR']);
			}
			
			if (!$json) {
				$json['success'] = $registry->get('language')->get('text_success');
				
				$api_session = new Session($registry->get('config')->get('session_engine'), $registry);
				
				$api_session->start();
				
				$registry->get('model_account_api')->addApiSession($api_info['api_id'], $api_session->getId(), $registry->get('request')->server['REMOTE_ADDR']);
				
				$api_session->data['oc_api_id'] = $api_info['api_id'];
				
				$json['api_session_id'] = $api_session->getId();
				
				// Create Token
				$json['oc_api_token'] = $api_session->getId();
			} else {
				$json['error']['key'] = $registry->get('language')->get('error_key');
			}		
		
			if ($registry->get('config')->get('session_autostart')) {
				/*
				We are adding the session cookie outside of the session class as I believe
				PHP messed up in a big way handling sessions. Why in the hell is it so hard to
				have more than one concurrent session using cookies!

				Is it not better to have multiple cookies when accessing parts of the system
				that requires different cookie sessions for security reasons.

				Also cookies can be accessed via the URL parameters. So why force only one cookie
				for all sessions!
				*/

				if (isset($registry->get('request')->cookie[$registry->get('config')->get('session_name')])) {
					$session_id = $registry->get('request')->cookie[$registry->get('config')->get('session_name')];
				} else {
					$session_id = '';
				}

				$session->start($session_id);

				setcookie($registry->get('config')->get('session_name'), $session->getId(), ini_get('session.cookie_lifetime'), ini_get('session.cookie_path'), ini_get('session.cookie_domain'));
			}

			// Cache
			$registry->set('cache', new Cache($registry->get('config')->get('cache_engine'), $registry->get('config')->get('cache_expire')));

			// Url
			if ($registry->get('config')->get('url_autostart')) {
				$registry->set('url', new Url($registry->get('config')->get('site_url'), $registry->get('config')->get('site_ssl')));
			}

			// Language
			$language = new Language($registry->get('config')->get('language_directory'));
			$registry->set('language', $language);

			// Document
			$registry->set('document', new Document());

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

			// Startup

			// Store
			if ($registry->get('request')->server['HTTPS']) {
				$query = $registry->get('db')->query("SELECT * FROM " . DB_PREFIX . "store WHERE REPLACE(`ssl`, 'www.', '') = '" . $registry->get('db')->escape('https://' . str_replace('www.', '', $registry->get('request')->server['HTTP_HOST']) . rtrim(dirname($registry->get('request')->server['PHP_SELF']), '/.\\') . '/') . "'");
			} else {
				$query = $registry->get('db')->query("SELECT * FROM " . DB_PREFIX . "store WHERE REPLACE(`url`, 'www.', '') = '" . $registry->get('db')->escape('http://' . str_replace('www.', '', $registry->get('request')->server['HTTP_HOST']) . rtrim(dirname($registry->get('request')->server['PHP_SELF']), '/.\\') . '/') . "'");
			}
						
			if (isset($registry->get('request')->get['store_id'])) {
				$registry->get('config')->set('config_store_id', (int)$registry->get('request')->get['store_id']);
			} else if ($query->num_rows) {
				$registry->get('config')->set('config_store_id', $query->row['store_id']);
			} else {
				$registry->get('config')->set('config_store_id', 0);
			}
					
			if (!$query->num_rows) {
				$registry->get('config')->set('config_url', HTTP_SERVER);
				$registry->get('config')->set('config_ssl', HTTPS_SERVER);
			}
							
			// Settings
			$query = $registry->get('db')->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' OR store_id = '" . (int)$registry->get('config')->get('config_store_id') . "' ORDER BY store_id ASC");
						
			foreach ($query->rows as $result) {
				if (!$result['serialized']) {
					$registry->get('config')->set($result['key'], $result['value']);
				} else {
					$registry->get('config')->set($result['key'], json_decode($result['value'], true));
				}
			}

			// Theme
			$registry->get('config')->set('template_cache', $registry->get('config')->get('developer_theme'));
							
			// Url
			$registry->set('url', new Url($registry->get('config')->get('config_url'), $registry->get('config')->get('config_ssl')));
						
			// Language
			$code = '';

			$registry->get('load')->model('localisation/language');
						
			$languages = $registry->get('model_localisation_language')->getLanguages();
							
			if (isset($registry->get('session')->data['oc_api_language'])) {
				$code = $registry->get('session')->data['oc_api_language'];
			}
									
			if (isset($registry->get('request')->cookie['language']) && !array_key_exists($code, $languages)) {
				$code = $registry->get('request')->cookie['language'];
			}
							
			// Language Detection
			if (!empty($registry->get('request')->server['HTTP_ACCEPT_LANGUAGE']) && !array_key_exists($code, $languages)) {
				$detect = '';
							
				$browser_languages = explode(',', $registry->get('request')->server['HTTP_ACCEPT_LANGUAGE']);
								
				// Try using local to detect the language
				foreach ($browser_languages as $browser_language) {
					foreach ($languages as $key => $value) {
						if ($value['status']) {
							$locale = explode(',', $value['locale']);
							
							if (in_array($browser_language, $locale)) {
								$detect = $key;
								break 2;
							}
						}
					}	
				}			
						
				if (!$detect) { 
					// Try using language folder to detect the language
					foreach ($browser_languages as $browser_language) {
						if (array_key_exists(strtolower($browser_language), $languages)) {
							$detect = strtolower($browser_language);
							
							break;
						}
					}
				}
						
				$code = $detect ? $detect : '';
			}
						
			if (!array_key_exists($code, $languages)) {
				$code = $registry->get('config')->get('config_language');
			}
						
			if (!isset($registry->get('session')->data['oc_api_language']) || $registry->get('session')->data['oc_api_language'] != $code) {
				$registry->get('session')->data['oc_api_language'] = $code;
			}
							
			if (!isset($registry->get('request')->cookie['language']) || $registry->get('request')->cookie['language'] != $code) {
				setcookie('oc_api_language', $code, time() + 60 * 60 * 24 * 30, '/', $registry->get('request')->server['HTTP_HOST']);
			}
							
			// Overwrite the default language object
			$language = new Language($code);
			$language->load($code);
					
			$registry->set('language', $language);
					
			// Set the config language_id
			$registry->get('config')->set('config_language_id', $languages[$code]['language_id']);	

			// Customer
			$customer = new Cart\Customer($registry);
			$registry->set('customer', $customer);
					
			// Customer Group
			if (isset($registry->get('session')->data['oc_api_customer']) && isset($registry->get('session')->data['oc_api_customer']['customer_group_id'])) {
				// For API calls
				$registry->get('config')->set('config_customer_group_id', $registry->get('session')->data['oc_api_customer']['customer_group_id']);
			} elseif ($customer->isLogged()) {
				// Logged in customers
				$registry->get('config')->set('config_customer_group_id', $customer->getGroupId());
			} elseif (isset($registry->get('session')->data['oc_api_guest']) && isset($registry->get('session')->data['oc_api_guest']['customer_group_id'])) {
				$registry->get('config')->set('config_customer_group_id', $registry->get('session')->data['oc_api_guest']['customer_group_id']);
			}
						
			// Tracking Code
			if (isset($registry->get('request')->get['tracking'])) {
				setcookie('oc_api_tracking', $registry->get('request')->get['tracking'], time() + 3600 * 24 * 1000, '/');
					
				$registry->get('db')->query("UPDATE `" . DB_PREFIX . "marketing` SET clicks = (clicks + 1) WHERE code = '" . $registry->get('db')->escape($registry->get('request')->get['tracking']) . "'");
			}		
					
			// Currency
			$code = '';
					
			$registry->get('load')->model('localisation/currency');
					
			$currencies = $registry->get('model_localisation_currency')->getCurrencies();
						
			if (isset($registry->get('session')->data['oc_api_currency'])) {
				$code = $registry->get('session')->data['oc_api_currency'];
			}
					
			if (isset($registry->get('request')->cookie['oc_api_currency']) && !array_key_exists($code, $currencies)) {
				$code = $registry->get('request')->cookie['oc_api_currency'];
			}
					
			if (!array_key_exists($code, $currencies)) {
				$code = $registry->get('config')->get('config_currency');
			}
					
			if (!isset($registry->get('session')->data['oc_api_currency']) || $registry->get('session')->data['oc_api_currency'] != $code) {
				$registry->get('session')->data['oc_api_currency'] = $code;
			}
					
			if (!isset($registry->get('request')->cookie['oc_api_currency']) || $registry->get('request')->cookie['oc_api_currency'] != $code) {
				setcookie('oc_api_currency', $code, time() + 60 * 60 * 24 * 30, '/', $registry->get('request')->server['HTTP_HOST']);
			}		
						
			$registry->set('currency', new Cart\Currency($registry));
					
			// Tax
			$registry->set('tax', new Cart\Tax($registry));

			$tax = $registry->get('tax');

			if ($tax) {
				if (isset($registry->get('session')->data['oc_api_shipping_address'])) {
					$tax->setShippingAddress($registry->get('session')->data['oc_api_shipping_address']['country_id'], $registry->get('session')->data['oc_api_shipping_address']['zone_id']);
				} elseif ($registry->get('config')->get('config_tax_default') == 'shipping') {
					$tax->setShippingAddress($registry->get('config')->get('config_country_id'), $registry->get('config')->get('config_zone_id'));
				}

				if (isset($registry->get('session')->data['oc_api_payment_address'])) {
					$tax->setPaymentAddress($registry->get('session')->data['oc_api_payment_address']['country_id'], $registry->get('session')->data['oc_api_payment_address']['zone_id']);
				} elseif ($registry->get('config')->get('config_tax_default') == 'payment') {
					$tax->setPaymentAddress($registry->get('config')->get('config_country_id'), $registry->get('config')->get('config_zone_id'));
				}
					$tax->setStoreAddress($registry->get('config')->get('config_country_id'), $registry->get('config')->get('config_zone_id'));
			}
						
			// Weight
			$registry->set('weight', new Cart\Weight($registry));
					
			// Length
			$registry->set('length', new Cart\Length($registry));
					
			// Cart
			$registry->set('cart', new Cart\Cart($registry));
					
			// Encryption
			$registry->set('encryption', new Encryption($registry->get('config')->get('config_encryption')));
			
			// Logged customer
			$customer_logged = false;
			
			if (!empty($registry->get('request')->post['email']) && filter_var($registry->get('request')->post['email'], FILTER_VALIDATE_EMAIL) && $registry->get('request')->post['email'] == $registry->get('customer')->getEmail()) {
				$customer_logged = true;
			}
			
			$json['customer_logged'] = $customer_logged;
		}
	}
}

$registry->get('response')->addHeader('Content-Type: application/json');
$registry->get('response')->setOutput(json_encode($json));		
