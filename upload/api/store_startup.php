<?php

// Store
if ($registry->get('request')->server['HTTPS']) {
	$query = $registry->get('db')->query("SELECT * FROM `" . DB_PREFIX . "store` WHERE REPLACE(`ssl`, 'www.', '') = '" . $registry->get('db')->escape('https://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/') . "'");
} else {
	$query = $registry->get('db')->query("SELECT * FROM `" . DB_PREFIX . "store` WHERE REPLACE(`url`, 'www.', '') = '" . $registry->get('db')->escape('http://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/') . "'");
}
		
if (isset($registry->get('request')->get['store_id'])) {
	$registry->get('config')->set('config_store_id', (int)$registry->get('request')->get['store_id']);
} elseif ($query->num_rows) {
	$registry->get('config')->set('config_store_id', $query->row['store_id']);
} else {
	$registry->get('config')->set('config_store_id', 0);
}
		
if (!$query->num_rows) {
	$registry->get('config')->set('config_url', HTTP_SERVER);
	$registry->get('config')->set('config_ssl', HTTPS_SERVER);
}
		
// Settings
$query = $registry->get('db')->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' OR `store_id` = '" . (int)$registry->get('config')->get('config_store_id') . "' ORDER BY `store_id` ASC");
		
foreach ($query->rows as $result) {
	if (!$result['serialized']) {
		$registry->get('config')->set($result['key'], $result['value']);
	} else {
		$registry->get('config')->set($result['key'], json_decode($result['value'], true));
	}
}

// Language
$code = '';
		
$registry->get('load')->model('localisation/language');
		
$languages = $registry->get('model_localisation_language')->getLanguages();
		
if (isset($registry->get('session')->data['language'])) {
	$code = $registry->get('session')->data['language'];
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
		
if (!isset($registry->get('session')->data['language']) || $registry->get('session')->data['language'] != $code) {
	$registry->get('session')->data['language'] = $code;
}
				
// Overwrite the default language object
$language = new Language($code);
$language->load($code);
		
$registry->set('language', $language);
		
// Set the config language_id
$registry->get('config')->set('config_language_id', $languages[$code]['language_id']);	

// Customer Group
if (isset($registry->get('session')->data['customer']) && isset($registry->get('session')->data['customer']['customer_group_id'])) {
	// For API calls
	$registry->get('config')->set('config_customer_group_id', $registry->get('session')->data['customer']['customer_group_id']);
} elseif ($customer_logged) {
	// Logged in customers
	$registry->get('config')->set('config_customer_group_id', $customer_group_id);
} elseif (isset($registry->get('session')->data['guest']) && isset($registry->get('session')->data['guest']['customer_group_id'])) {
	$registry->get('config')->set('config_customer_group_id', $registry->get('session')->data['guest']['customer_group_id']);
}
		
// Tracking Code
if (isset($registry->get('request')->get['tracking'])) {
	$registry->get('db')->query("UPDATE `" . DB_PREFIX . "marketing` SET `clicks` = (clicks + 1) WHERE `code` = '" . $registry->get('db')->escape($registry->get('request')->get['tracking']) . "'");
}		
		
// Currency
$code = '';
		
$registry->get('load')->model('localisation/currency');
		
$currencies = $registry->get('model_localisation_currency')->getCurrencies();
		
if (isset($registry->get('session')->data['currency'])) {
	$code = $registry->get('session')->data['currency'];
}
		
if (!array_key_exists($code, $currencies)) {
	$code = $registry->get('config')->get('config_currency');
}
		
if (!isset($registry->get('session')->data['currency']) || $registry->get('session')->data['currency'] != $code) {
	$registry->get('session')->data['currency'] = $code;
}
		
$registry->set('currency', new Cart\Currency($registry));
		
// Tax
$registry->set('tax', new Cart\Tax($registry));
		
if (isset($registry->get('session')->data['shipping_address'])) {
	$registry->get('tax')->setShippingAddress($registry->get('session')->data['shipping_address']['country_id'], $registry->get('session')->data['shipping_address']['zone_id']);
} elseif ($registry->get('config')->get('config_tax_default') == 'shipping') {
	$registry->get('tax')->setShippingAddress($registry->get('config')->get('config_country_id'), $registry->get('config')->get('config_zone_id'));
}

if (isset($registry->get('session')->data['payment_address'])) {
	$registry->get('tax')->setPaymentAddress($registry->get('session')->data['payment_address']['country_id'], $registry->get('session')->data['payment_address']['zone_id']);
} elseif ($registry->get('config')->get('config_tax_default') == 'payment') {
	$registry->get('tax')->setPaymentAddress($registry->get('config')->get('config_country_id'), $registry->get('config')->get('config_zone_id'));
}

$registry->get('tax')->setStoreAddress($registry->get('config')->get('config_country_id'), $registry->get('config')->get('config_zone_id'));
		
// Weight
$registry->set('weight', new Cart\Weight($registry));
		
// Length
$registry->set('length', new Cart\Length($registry));
		
// Encryption
$registry->set('encryption', new Encryption($registry->get('config')->get('config_encryption')));
	
