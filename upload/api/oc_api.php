<?php
// Error Reporting
//error_reporting(E_ALL);

if (file_exists('../config.php')) {
	require_once('../config.php');
}

// Check Installed
if (!defined('DIR_APPLICATION')) {
	die('You are not authorized to view this page!');
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

// Ajax validation with PHP 7
// Source: https://stackoverflow.com/questions/18260537/how-to-check-if-the-request-is-an-ajax-request-with-php
$is_ajax = 'XMLHttpRequest' == ( $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '' );

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
	if ($is_ajax) {
		$json = array();
		
		$json['error']['token'] = 'No API Access!';
	} elseif (!$is_ajax) {
		$registry->get('response')->addHeader($registry->get('request')->server['SERVER_PROTOCOL'] . ' 404 Not Found');
		
		die('You are not authorized to view this page!');
	}
} else {
	$registry->get('response')->addHeader('Content-Type: text/html; charset=utf-8');
	$registry->get('response')->setCompression($registry->get('config')->get('config_compression'));
	
	$json = array();
	
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
			} elseif (!empty($registry->get('request')->get['email']) && filter_var($registry->get('request')->get['email'], FILTER_VALIDATE_EMAIL) && $registry->get('request')->get['email'] == $registry->get('customer')->getEmail()) {
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
			
			if (!empty($registry->get('request')->get['email']) && filter_var($registry->get('request')->get['email'], FILTER_VALIDATE_EMAIL) && $registry->get('request')->get['email'] == $registry->get('customer')->getEmail()) {
				$cart_query = $registry->get('cart')->getProducts();
				
				foreach ($cart_query as $cart) {
					if ($cart['customer_id'] == $registry->get('customer')->getId()) {
						$json['cart'][] = $cart;
					}
				}
				
				$customer_logged = true;
			}
			
			$json['customer_logged'] = $customer_logged;
			
			// Site Search
			$registry->get('load')->language('product/search');
			
			$registry->get('load')->model('catalog/category');
			
			$registry->get('load')->model('catalog/product');
			
			$registry->get('load')->model('tool/image');

			if (isset($registry->get('request')->get['search'])) {
				$search = $registry->get('request')->get['search'];
			} else {
				$search = '';
			}

			if (isset($registry->get('request')->get['tag'])) {
				$tag = $registry->get('request')->get['tag'];
			} elseif (isset($registry->get('request')->get['search'])) {
				$tag = $registry->get('request')->get['search'];
			} else {
				$tag = '';
			}

			if (isset($registry->get('request')->get['description'])) {
				$description = $registry->get('request')->get['description'];
			} else {
				$description = '';
			}

			if (isset($registry->get('request')->get['category_id'])) {
				$category_id = $registry->get('request')->get['category_id'];
			} else {
				$category_id = 0;
			}

			if (isset($registry->get('request')->get['sub_category'])) {
				$sub_category = $registry->get('request')->get['sub_category'];
			} else {
				$sub_category = '';
			}

			if (isset($registry->get('request')->get['sort'])) {
				$sort = $registry->get('request')->get['sort'];
			} else {
				$sort = 'p.sort_order';
			}

			if (isset($registry->get('request')->get['order'])) {
				$order = $registry->get('request')->get['order'];
			} else {
				$order = 'ASC';
			}

			if (isset($registry->get('request')->get['page'])) {
				$page = $registry->get('request')->get['page'];
			} else {
				$page = 1;
			}

			if (isset($registry->get('request')->get['limit'])) {
				$limit = (int)$registry->get('request')->get['limit'];
			} else {
				$limit = $registry->get('config')->get('theme_' . $registry->get('config')->get('config_theme') . '_product_limit');
			}

			if (isset($registry->get('request')->get['search'])) {
				$registry->get('document')->setTitle($registry->get('language')->get('heading_title') .  ' - ' . $registry->get('request')->get['search']);
			} elseif (isset($registry->get('request')->get['tag'])) {
				$registry->get('document')->setTitle($registry->get('language')->get('heading_title') .  ' - ' . $registry->get('language')->get('heading_tag') . $registry->get('request')->get['tag']);
			} else {
				$registry->get('document')->setTitle($registry->get('language')->get('heading_title'));
			}

			$url = '';

			if (isset($registry->get('request')->get['search'])) {
				$url .= '&search=' . urlencode(html_entity_decode($registry->get('request')->get['search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($registry->get('request')->get['tag'])) {
				$url .= '&tag=' . urlencode(html_entity_decode($registry->get('request')->get['tag'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($registry->get('request')->get['description'])) {
				$url .= '&description=' . $registry->get('request')->get['description'];
			}

			if (isset($registry->get('request')->get['category_id'])) {
				$url .= '&category_id=' . $registry->get('request')->get['category_id'];
			}

			if (isset($registry->get('request')->get['sub_category'])) {
				$url .= '&sub_category=' . $registry->get('request')->get['sub_category'];
			}

			if (isset($registry->get('request')->get['sort'])) {
				$url .= '&sort=' . $registry->get('request')->get['sort'];
			}

			if (isset($registry->get('request')->get['order'])) {
				$url .= '&order=' . $registry->get('request')->get['order'];
			}

			if (isset($registry->get('request')->get['page'])) {
				$url .= '&page=' . $registry->get('request')->get['page'];
			}

			if (isset($registry->get('request')->get['limit'])) {
				$url .= '&limit=' . $registry->get('request')->get['limit'];
			}

			if (isset($registry->get('request')->get['search'])) {
				$json['heading_title'] = $registry->get('language')->get('heading_title') .  ' - ' . $registry->get('request')->get['search'];
			} else {
				$json['heading_title'] = $registry->get('language')->get('heading_title');
			}

			$registry->get('load')->model('catalog/category');

			// 3 Level Category Search
			$json['categories'] = array();

			$categories_1 = $registry->get('model_catalog_category')->getCategories(0);

			foreach ($categories_1 as $category_1) {
				$level_2_data = array();

				$categories_2 = $registry->get('model_catalog_category')->getCategories($category_1['category_id']);

				foreach ($categories_2 as $category_2) {
					$level_3_data = array();

					$categories_3 = $registry->get('model_catalog_category')->getCategories($category_2['category_id']);

					foreach ($categories_3 as $category_3) {
						$level_3_data[] = array(
							'category_id' => $category_3['category_id'],
							'name'        => $category_3['name'],
						);
					}

					$level_2_data[] = array(
						'category_id' => $category_2['category_id'],
						'name'        => $category_2['name'],
						'children'    => $level_3_data
					);
				}

				$json['categories'][] = array(
					'category_id' => $category_1['category_id'],
					'name'        => $category_1['name'],
					'children'    => $level_2_data
				);
			}

			$json['products'] = array();

			if (isset($registry->get('request')->get['search']) || isset($registry->get('request')->get['tag'])) {
				$filter_data = array(
					'filter_name'         => $search,
					'filter_tag'          => $tag,
					'filter_description'  => $description,
					'filter_category_id'  => $category_id,
					'filter_sub_category' => $sub_category,
					'sort'                => $sort,
					'order'               => $order,
					'start'               => ($page - 1) * $limit,
					'limit'               => $limit,
				);

				$product_total = $registry->get('model_catalog_product')->getTotalProducts($filter_data);

				$results = $registry->get('model_catalog_product')->getProducts($filter_data);

				foreach ($results as $result) {
					if ($result['image']) {
						$image = $registry->get('model_tool_image')->resize($result['image'], $registry->get('config')->get('theme_' . $registry->get('config')->get('config_theme') . '_image_product_width'), $registry->get('config')->get('theme_' . $registry->get('config')->get('config_theme') . '_image_product_height'));
					} else {
						$image = $registry->get('model_tool_image')->resize('placeholder.png', $registry->get('config')->get('theme_' . $registry->get('config')->get('config_theme') . '_image_product_width'), $registry->get('config')->get('theme_' . $registry->get('config')->get('config_theme') . '_image_product_height'));
					}

					if ((!empty($registry->get('request')->get['email']) && filter_var($registry->get('request')->get['email'], FILTER_VALIDATE_EMAIL) && $registry->get('request')->get['email'] == $registry->get('customer')->getEmail()) || (!$registry->get('config')->get('config_customer_price'))) {
						$price = $registry->get('currency')->format($registry->get('tax')->calculate($result['price'], $result['tax_class_id'], $registry->get('config')->get('config_tax')), $registry->get('session')->data['currency']);
					} else {
						$price = false;
					}

					if ((float)$result['special']) {
						$special = $registry->get('currency')->format($registry->get('tax')->calculate($result['special'], $result['tax_class_id'], $registry->get('config')->get('config_tax')), $registry->get('session')->data['currency']);
					} else {
						$special = false;
					}

					if ($registry->get('config')->get('config_tax')) {
						$tax = $registry->get('currency')->format((float)$result['special'] ? $result['special'] : $result['price'], $registry->get('session')->data['currency']);
					} else {
						$tax = false;
					}

					if ($registry->get('config')->get('config_review_status')) {
						$rating = (int)$result['rating'];
					} else {
						$rating = false;
					}

					$json['products'][] = array(
						'product_id'  => $result['product_id'],
						'thumb'       => $image,
						'name'        => $result['name'],
						'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $registry->get('config')->get('theme_' . $registry->get('config')->get('config_theme') . '_product_description_length')) . '..',
						'price'       => $price,
						'special'     => $special,
						'tax'         => $tax,
						'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
						'rating'      => $result['rating'],
						'href'        => '',
					);
				}

				$url = '';

				if (isset($registry->get('request')->get['search'])) {
					$url .= '&search=' . urlencode(html_entity_decode($registry->get('request')->get['search'], ENT_QUOTES, 'UTF-8'));
				}

				if (isset($registry->get('request')->get['tag'])) {
					$url .= '&tag=' . urlencode(html_entity_decode($registry->get('request')->get['tag'], ENT_QUOTES, 'UTF-8'));
				}

				if (isset($registry->get('request')->get['description'])) {
					$url .= '&description=' . $registry->get('request')->get['description'];
				}

				if (isset($registry->get('request')->get['category_id'])) {
					$url .= '&category_id=' . $registry->get('request')->get['category_id'];
				}

				if (isset($registry->get('request')->get['sub_category'])) {
					$url .= '&sub_category=' . $registry->get('request')->get['sub_category'];
				}

				if (isset($registry->get('request')->get['limit'])) {
					$url .= '&limit=' . $registry->get('request')->get['limit'];
				}

				$json['sorts'] = array();

				$json['sorts'][] = array(
					'text'  => $registry->get('language')->get('text_default'),
					'value' => 'p.sort_order-ASC',
					'href'  => '',
				);

				$json['sorts'][] = array(
					'text'  => $registry->get('language')->get('text_name_asc'),
					'value' => 'pd.name-ASC',
					'href'  => '',
				);

				$json['sorts'][] = array(
					'text'  => $registry->get('language')->get('text_name_desc'),
					'value' => 'pd.name-DESC',
					'href'  => '',
				);

				$json['sorts'][] = array(
					'text'  => $registry->get('language')->get('text_price_asc'),
					'value' => 'p.price-ASC',
					'href'  => '',
				);

				$json['sorts'][] = array(
					'text'  => $registry->get('language')->get('text_price_desc'),
					'value' => 'p.price-DESC',
					'href'  => '',
				);

				if ($registry->get('config')->get('config_review_status')) {
					$json['sorts'][] = array(
						'text'  => $registry->get('language')->get('text_rating_desc'),
						'value' => 'rating-DESC',
						'href'  => '',
					);

					$json['sorts'][] = array(
						'text'  => $registry->get('language')->get('text_rating_asc'),
						'value' => 'rating-ASC',
						'href'  => '',
					);
				}

				$json['sorts'][] = array(
					'text'  => $registry->get('language')->get('text_model_asc'),
					'value' => 'p.model-ASC',
					'href'  => '',
				);

				$json['sorts'][] = array(
					'text'  => $registry->get('language')->get('text_model_desc'),
					'value' => 'p.model-DESC',
					'href'  => '',
				);

				$url = '';

				if (isset($registry->get('request')->get['search'])) {
					$url .= '&search=' . urlencode(html_entity_decode($registry->get('request')->get['search'], ENT_QUOTES, 'UTF-8'));
				}

				if (isset($registry->get('request')->get['tag'])) {
					$url .= '&tag=' . urlencode(html_entity_decode($registry->get('request')->get['tag'], ENT_QUOTES, 'UTF-8'));
				}

				if (isset($registry->get('request')->get['description'])) {
					$url .= '&description=' . $registry->get('request')->get['description'];
				}

				if (isset($registry->get('request')->get['category_id'])) {
					$url .= '&category_id=' . $registry->get('request')->get['category_id'];
				}

				if (isset($registry->get('request')->get['sub_category'])) {
					$url .= '&sub_category=' . $registry->get('request')->get['sub_category'];
				}

				if (isset($registry->get('request')->get['sort'])) {
					$url .= '&sort=' . $registry->get('request')->get['sort'];
				}

				if (isset($registry->get('request')->get['order'])) {
					$url .= '&order=' . $registry->get('request')->get['order'];
				}

				$json['limits'] = array();

				$limits = array_unique(array($registry->get('config')->get('theme_' . $registry->get('config')->get('config_theme') . '_product_limit'), 25, 50, 75, 100));

				sort($limits);

				foreach($limits as $value) {
					$json['limits'][] = array(
						'text'  => $value,
						'value' => $value,
						'href'  => '',
					);
				}

				$url = '';

				if (isset($registry->get('request')->get['search'])) {
					$url .= '&search=' . urlencode(html_entity_decode($registry->get('request')->get['search'], ENT_QUOTES, 'UTF-8'));
				}

				if (isset($registry->get('request')->get['tag'])) {
					$url .= '&tag=' . urlencode(html_entity_decode($registry->get('request')->get['tag'], ENT_QUOTES, 'UTF-8'));
				}

				if (isset($registry->get('request')->get['description'])) {
					$url .= '&description=' . $registry->get('request')->get['description'];
				}

				if (isset($registry->get('request')->get['category_id'])) {
					$url .= '&category_id=' . $registry->get('request')->get['category_id'];
				}

				if (isset($registry->get('request')->get['sub_category'])) {
					$url .= '&sub_category=' . $registry->get('request')->get['sub_category'];
				}

				if (isset($registry->get('request')->get['sort'])) {
					$url .= '&sort=' . $registry->get('request')->get['sort'];
				}

				if (isset($registry->get('request')->get['order'])) {
					$url .= '&order=' . $registry->get('request')->get['order'];
				}

				if (isset($registry->get('request')->get['limit'])) {
					$url .= '&limit=' . $registry->get('request')->get['limit'];
				}

				$json['pagination_total'] = $product_total;				
				$json['pagination_page'] = $page;				
				$json['pagination_limit'] = $limit;				
				$json['pagination_url'] = $url;

				$json['results'] = sprintf($registry->get('language')->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

				if (isset($registry->get('request')->get['search']) && $registry->get('config')->get('config_customer_search')) {
					$registry->get('load')->model('account/search');

					if (!empty($registry->get('request')->get['email']) && filter_var($registry->get('request')->get['email'], FILTER_VALIDATE_EMAIL) && $registry->get('request')->get['email'] == $registry->get('customer')->getEmail()) {
						$customer_id = $registry->get('customer')->getId();
					} else {
						$customer_id = 0;
					}

					if (isset($registry->get('request')->server['REMOTE_ADDR'])) {
						$ip = $registry->get('request')->server['REMOTE_ADDR'];
					} else {
						$ip = '';
					}

					$search_data = array(
						'keyword'       => $search,
						'category_id'   => $category_id,
						'sub_category'  => $sub_category,
						'description'   => $description,
						'products'      => $product_total,
						'customer_id'   => $customer_id,
						'ip'            => $ip,
					);

					$registry->get('model_account_search')->addSearch($search_data);
				}
			}

			$json['search'] = $search;
			$json['description'] = $description;
			$json['category_id'] = $category_id;
			$json['sub_category'] = $sub_category;

			$json['sort'] = $sort;
			$json['order'] = $order;
			$json['limit'] = $limit;
			
			// Totals
			$registry->get('load')->model('setting/extension');
			
			$totals = array();
			$taxes = ($customer_logged ? $registry->get('cart')->getTaxes() : 0);
			$total = 0;
			
			// Display prices
			if ((!empty($registry->get('request')->get['email']) && filter_var($registry->get('request')->get['email'], FILTER_VALIDATE_EMAIL) && $registry->get('request')->get['email'] == $registry->get('customer')->getEmail()) || (!$registry->get('config')->get('config_customer_price'))) {
				$sort_order = array();
				
				$results = $registry->get('model_setting_extension')->getExtensions('total');
				
				foreach ($results as $key => $value) {
					$sort_order[$key] = $registry->get('config')->get('total_' . $value['code'] . '_sort_order');
				}
				
				array_multisort($sort_order, SORT_ASC, $results);
				
				foreach ($results as $result) {
					if ($registry->get('config')->get('total_' . $result['code'] . '_status')) {
						$registry->get('load')->model('extension/total/' . $result['code']);
						
						// __call can not pass-by-reference so we get PHP to call it as an anonymous function.
						($registry->get('model_extension_total_' . $result['code']->getTotal))($totals, $taxes, $total);
					}
				}
				
				$sort_order = array();
				
				foreach ($totals as $key => $value) {
					$sort_order[$key] = $value['sort_order'];
				}
				
				array_multisort($sort_order, SORT_ASC, $totals);
			}
			
			$json['totals'] = array();
			
			foreach ($totals as $total) {
				$json['totals'][] = array(
					'title' => $total['title'],
					'text'  => $registry->get('currency')->format($total['value'], $registry->get('session')->data['currency'])
				);
			}
		}
	}
	
	$registry->get('response')->addHeader('Content-Type: application/json');
	$registry->get('response')->setOutput(json_encode($json));
}
