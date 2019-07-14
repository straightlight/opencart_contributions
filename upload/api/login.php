<?php

// catalog/language/<default_language_code>/api/login.php
$registry->get('load')->language('api/login');
	
// Lookup for the store hash code from the admin - > systems - > settings - > add / edit settings.
if (empty($registry->get('request')->get['hash']) || $registry->get('request')->get['hash'] != $registry->get('config')->get('config_hash')) {
	$json['error']['hash'] = $registry->get('language')->get('error_hash');
		
// Lookup for the store code (Business ID) from the admin - > systems - > settings - > add / edit settings.
} elseif (empty($registry->get('request')->get['store_code']) || $registry->get('request')->get['store_code'] != $registry->get('config')->get('config_code')) {
	$json['error']['code'] = $registry->get('language')->get('error_code');
		
// Else, we instantiate the next lookups.
} elseif (!empty($registry->get('request')->get['store_code']) && !empty($registry->get('config')->get('config_code')) && $registry->get('request')->get['store_code'] == $registry->get('config')->get('config_code') && !empty($registry->get('request')->get['hash']) && $registry->get('request')->get['hash'] == $registry->get('config')->get('config_hash')) {
	// Finding a relative API ID for the default selected store by the store owner.
	$api_info = $registry->get('db')->query("SELECT * FROM `" . DB_PREFIX . "api` WHERE api_id = '" . (int)$registry->get('config')->get('config_api_id') . "'");

	// If no API ID can be found, relatively with the store,
	// we reject the API transaction directly from the browser
	// with an error message.
	if (!$api_info->num_rows) {
		$json['error']['token'] = $registry->get('language')->get('error_token');
			
	// Else, we instantiate the next lookups.
	} elseif ($api_info->num_rows) {
		$json = array();
				
		// catalog/model/account/api.php			
		$registry->get('load')->model('account/api');
				
		$api_token = '';
					
		// Instantiating a new session for the API.
		$api_session = new Session($registry->get('config')->get('session_engine'), $registry);

		// Starting API session ...
		$api_session->start();
				
		// Delete old API sessions.
		$registry->get('db')->query("DELETE FROM `" . DB_PREFIX . "api_session` WHERE session_id = '" . $registry->get('db')->escape($session->getId()) . "'");

		// Acquire authorized IP address to connect via
		// the API defined from the OC admin.
		$api_ip_query = $registry->get('db')->query("SELECT * FROM `" . DB_PREFIX . "api_ip` WHERE ip = '" . $registry->get('db')->escape($registry->get('request')->server['REMOTE_ADDR']) . "'");
					
		// If no results are being found with authorized IP addresses,
		// we add the results.
		if (!$api_ip_query->num_rows) {
			$registry->get('db')->query("INSERT INTO `" . DB_PREFIX . "api_ip` SET api_id = '" . (int)$api_info['api_id'] . "', ip = '" . $registry->get('db')->escape($registry->get('request')->server['REMOTE_ADDR']) . "'");
		}
			
		// Adding API session on database ...
		$registry->get('model_account_api')->addApiSession($api_info['api_id'], $api_session->getId(), $registry->get('request')->server['REMOTE_ADDR']);

		$api_session->data['oc_api_id'] = $api_info->row['api_id'];
			
		// Capturing the API token.
		$api_token = $api_session->getId();
				
		// If the API token could not be created,
		// we return an error message via JSON POST.
		if (!$api_token) {
			$json['error']['token'] = $registry->get('language')->get('error_token');
				
		// Otherwise ...
		} else {
			$api_info = array();
				
			// Login with API Key
			if (isset($registry->get('request')->post['username'])) {
				$api_info = $registry->get('model_account_api')->login($registry->get('request')->post['username'], $registry->get('request')->post['key']);
			}
				
			// If the API login is unsuccessful,
			// we return an error message via JSON POST.
			if (!$api_info) {
				$json['error']['login'] = $registry->get('language')->get('error_login');
				
			// Otherwise ...
			} else {
				// Check if IP is allowed
				$ip_data = array();
			
				// Capturing IP addresses linked with this API ID ...
				$results = $registry->get('model_account_api')->getApiIps($api_info['api_id']);
				
				// Adding the IP addresses into an array.
				foreach ($results as $result) {
					$ip_data[] = trim($result['ip']);
				}
					
				// Output a default JSON POST error message.
				$json['error']['ip'] = $registry->get('language')->get('error_permission');
			
				// If the authorized IP address is not listed,
				// we return an error message via JSON POST.
				if (!in_array($registry->get('request')->server['REMOTE_ADDR'], $ip_data)) {
					$json['error']['ip'] = sprintf($registry->get('language')->get('error_ip'), $registry->get('request')->server['REMOTE_ADDR']);
				}
					
				// If no errors have been found.
				if (!$json) {
					// We return a success message via JSON POST.
					$json['success'] = $registry->get('language')->get('text_success');
						
					// Create Token
					$json['oc_api_token'] = $api_session->getId();
					
				// Else, we return an error message via JSON POST.
				} else {
					$json['error']['key'] = $registry->get('language')->get('error_key');
				}
								
				// Currency
				$code = '';
								
				$registry->get('load')->model('localisation/currency');
								
				$currencies = $registry->get('model_localisation_currency')->getCurrencies();
							
				// Validating the API currency session aside from the core.
				if (isset($registry->get('session')->data['oc_api_currency'])) {
					$code = $registry->get('session')->data['oc_api_currency'];
				}
							
				// If no currency code can be found,
				// we capture the default one from the current store.
				if (!array_key_exists($code, $currencies)) {
					$code = $registry->get('config')->get('config_currency');
				}
							
				// If the API currency session code does not match,
				// we create a new session with the API currency based on the captured code.
				if (!isset($registry->get('session')->data['oc_api_currency']) || $registry->get('session')->data['oc_api_currency'] != $code) {
					$registry->get('session')->data['oc_api_currency'] = $code;
				}
					
				// Cart
				$json['cart'] = array();
				
				// Guest customers by default.
				$customer_id = 0;
					
				// Captures the defined customer token from the oc_customer table.
				if (!empty($registry->get('request')->get['customer_token'])) {
					// catalog/model/account/customer.php
					$registry->get('load')->model('account/customer');
					
					// Queries the associated token defined on the browser
					// and on the database.
					$customer_info = $registry->get('model_account_customer')->getCustomerByToken($registry->get('request')->get['customer_token']);
						
					// If the customer token has been found,
					// we capture the OC customer ID.
					if ($customer_info) {
						$customer_id = $customer_info['customer_id'];
					}
				}
					
				// If the IP address from the request contains an address,
				// along with the product ID, we initiate a query into the oc_cart table.
				if (!empty($registry->get('request')->get['ip']) && !empty($registry->get('request')->get['product_id'])) {
					$cart_query = $registry->get('db')->query("SELECT * FROM `" . DB_PREFIX . "cart` WHERE `product_id` = '" . (int)$registry->get('request')->get['product_id'] . "' AND `ip` = '" . $registry->get('request')->get['ip'] . "' AND `customer_id` = '" . (int)$customer_id . "'");
					
					// If the query does find a match,
					// we add the results into the 'cart' array
					// via JSON POST.
					foreach ($cart_query->rows as $cart) {
						$json['cart'][] = $cart;
					}
				}
				
				// Customer Logged
				$customer_logged = false;
					
				$json['customer_info'] = array();
				
				// If customer online from admin - > systems - > settings - > add / edit settings page,
				// is enabled and that the $customer_id is a match ...
				if ($registry->get('config')->get('config_customer_online') && $customer_id) {
					// Checking the selected customer if he is currently online.
					$customer_info = $registry->get('db')->query("SELECT `c`.* FROM `" . DB_PREFIX . "customer_online` `co` LEFT JOIN `" . DB_PREFIX . "customer` `c` ON (`c`.`customer_id` = `co`.`customer_id`) WHERE `c`.`customer_id` > '0' AND `c`.`customer_id` = '" . (int)$customer_id . "' AND `c`.`status` = '1'")->row;
						
					// If he is online ...
					if ($customer_info) {
						// Remove the customer ID before being returned via the JSON POST.
						unset ($customer_info['customer_id']);
							
						// Merging the customer's results via JSON POST.
						$json['customer_info'] = $customer_info;
							
						// The customer is currently logged in.
						$customer_logged = true;
					}
				}
				
				// Returning the customer logged in result via JSON POST.
				$json['customer_logged'] = $customer_logged;
				
				// Totals
				$registry->get('load')->model('setting/extension');
					
				$totals = array();
				
				// Uses a stand-alone function with the API,
				// since the cart library can't specifically capture products
				// other than by the relative product IDs of the current logged in customers
				// based on their browser.
				$taxes = getTaxes($registry, $json['cart']);
				$total = 0;
					
				// Display prices
				if ($customer_logged || !$registry->get('config')->get('config_customer_price')) {
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
						'text'  => $registry->get('currency')->format($total['value'], $registry->get('session')->data['oc_api_currency'])
					);
				}
			}
		}
				
		$registry->get('response')->addHeader('Content-Type: application/json');
		$registry->get('response')->setOutput(json_encode($json));			
	}
}

// Stand-alone taxes calculations.
// Pulled from system/library/cart/cart.php file.
function getTaxes($registry, $cart_query) {
	$tax_data = array();

	foreach ($cart_query as $product) {
		if ($product['tax_class_id']) {
			$tax_rates = $registry->get('tax')->getRates($product['price'], $product['tax_class_id']);

			foreach ($tax_rates as $tax_rate) {
				if (!isset($tax_data[$tax_rate['tax_rate_id']])) {
					$tax_data[$tax_rate['tax_rate_id']] = ($tax_rate['amount'] * $product['quantity']);
				} else {
					$tax_data[$tax_rate['tax_rate_id']] += ($tax_rate['amount'] * $product['quantity']);
				}
			}
		}
	}
	
	return $tax_data;
}
