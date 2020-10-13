<?php
class ApiControllerCommonLogin extends Controller {
	public function index() {
		$flag = false;

		if (isset($this->request->get['api_token']) && isset($this->request->get['route']) && substr($this->request->get['route'], 0, 4) == 'api/') {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "api_session` WHERE TIMESTAMPADD(HOUR, 1, `date_modified`) < NOW()");
							
			// Make sure the IP is allowed
			$api_query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "api` a LEFT JOIN `" . DB_PREFIX . "api_session` `as` ON (a.`api_id` = `as`.`api_id`) LEFT JOIN `" . DB_PREFIX . "api_ip` ai ON (a.`api_id` = ai.`api_id`) WHERE a.`status` = '1' AND `as`.`session_id` = '" . $this->db->escape($this->request->get['api_token']) . "' AND ai.`ip` = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "'");
				 
			if ($api_query->num_rows) {
				$this->session->start($this->request->get['api_token']);
					
				// keep the session alive
				$this->db->query("UPDATE `" . DB_PREFIX . "api_session` SET `date_modified` = NOW() WHERE `api_session_id` = '" . (int)$api_query->row['api_session_id'] . "'");
				
				$flag = true;
			}
		}

		if (!$flag) {
			$customer_logged = false;						
			$customer_group_id = 0;			
			$customer_group_name = '';
			
			// catalog/language/<default_language_code>/api/login.php
			$this->load->language('api/login');
				
			// Lookup for the store hash code from the admin - > systems - > settings - > add / edit settings.
			if (empty($this->request->get['hash']) || $this->request->get['hash'] != $this->config->get('config_hash')) {
				$json['error']['hash'] = $this->language->get('error_hash');
					
			// Lookup for the store code (Business ID) from the admin - > systems - > settings - > add / edit settings.
			} elseif (empty($this->request->get['store_code']) || $this->request->get['store_code'] != $this->config->get('config_code')) {
				$json['error']['code'] = $this->language->get('error_code');
					
			// Else, we instantiate the next lookups.
			} elseif (!empty($this->request->get['store_code']) && $this->request->get['store_code'] == $this->config->get('config_code') && !empty($this->request->get['hash']) && $this->request->get['hash'] == $this->config->get('config_hash')) {
				// Finding a relative API ID for the default selected store by the store owner.
				$api_info = $this->db->query("SELECT * FROM `" . DB_PREFIX . "api` WHERE `api_id` = '" . (int)$this->config->get('config_api_id') . "'");

				// If no API ID can be found, relatively with the store,
				// we reject the API transaction directly from the browser
				// with an error message.
				if (!$api_info->num_rows) {
					$json['error']['token'] = $this->language->get('error_token');
						
				// Else, we instantiate the next lookups.
				} elseif ($api_info->num_rows) {
					$json = [];
							
					// catalog/model/account/api.php			
					$this->load->model('account/api');
							
					$api_token = '';
								
					// Instantiating a new session for the API.
					$api_session = new Session($this->config->get('session_engine'), $this->registry);

					// Starting API session ...
					$api_session->start();
							
					// Delete old API sessions.
					$this->db->query("DELETE FROM `" . DB_PREFIX . "api_session` WHERE `session_id` = '" . $this->db->escape($session->getId()) . "'");

					// Acquire authorized IP address to connect via
					// the API defined from the OC admin.
					$api_ip_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "api_ip` WHERE `ip` = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "'");
								
					// If no results are being found with authorized IP addresses,
					// we add the results.
					if (!$api_ip_query->num_rows) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "api_ip` SET `api_id` = '" . (int)$api_info['api_id'] . "', `ip` = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "'");
					}
						
					// Adding API session on database ...
					$this->model_account_api->addApiSession($api_info['api_id'], $api_session->getId(), $this->request->server['REMOTE_ADDR']);

					$api_session->data['oc_api_id'] = $api_info->row['api_id'];
						
					// Capturing the API token.
					$api_token = $api_session->getId();
							
					// If the API token could not be created,
					// we return an error message.
					if (!$api_token) {
						$json['error']['token'] = $this->language->get('error_token');
							
					// Otherwise ...
					} else {
						$api_info = [];
							
						// Login with API Key
						if (isset($this->request->post['username'])) {
							$api_info = $this->model_account_api->login($this->request->post['username'], $this->request->post['key']);
						}
							
						// If the API login is unsuccessful,
						// we return an error message.
						if (!$api_info) {
							$json['error']['login'] = $this->language->get('error_login');
							
						// Otherwise ...
						} else {
							// Check if IP is allowed
							$ip_data = [];
						
							// Capturing IP addresses linked with this API ID ...
							$results = $this->model_account_api->getApiIps($api_info['api_id']);
							
							// Adding the IP addresses into an array.
							foreach ($results as $result) {
								$ip_data[] = trim($result['ip']);
							}
								
							// Output a default JSON POST error message.
							$json['error']['ip'] = $this->language->get('error_permission');
						
							// If the authorized IP address is not listed,
							// we return an error message.
							if (!in_array($this->request->server['REMOTE_ADDR'], $ip_data)) {
								$json['error']['ip'] = sprintf($this->language->get('error_ip'), $this->request->server['REMOTE_ADDR']);
							}
								
							// If no errors have been found.
							if (!$json) {
								// We return a success message.
								$json['success'] = $this->language->get('text_success');
									
								// Create Token
								$json['oc_api_token'] = $api_session->getId();
								
							// Else, we return an error message.
							} else {
								$json['error']['key'] = $this->language->get('error_key');
							}
											
							// Currency
							$code = '';
											
							$this->load->model('localisation/currency');
											
							$currencies = $this->model_localisation_currency->getCurrencies();
										
							// Validating the API currency session aside from the core.
							if (isset($this->session->data['oc_api_currency'])) {
								$code = $this->session->data['oc_api_currency'];
							}
										
							// If no currency code can be found,
							// we capture the default one from the current store.
							if (!array_key_exists($code, $currencies)) {
								$code = $this->config->get('config_currency');
							}
										
							// If the API currency session code does not match,
							// we create a new session with the API currency based on the captured code.
							if (!isset($this->session->data['oc_api_currency']) || $this->session->data['oc_api_currency'] != $code) {
								$this->session->data['oc_api_currency'] = $code;
							}
								
							// Cart
							$json['cart'] = [];
							
							// Guest customers by default.
							$customer_id = 0;
								
							// Captures the defined customer token from the oc_customer table.
							if (!empty($this->request->get['customer_token'])) {
								// catalog/model/account/customer.php
								$this->load->model('account/customer');
								
								// Queries the associated token defined on the browser
								// and on the database.
								$customer_info = $this->model_account_customer->getCustomerByToken($this->request->get['customer_token']);
									
								// If the customer token has been found,
								// we capture the OC customer ID.
								if ($customer_info) {
									$customer_id = $customer_info['customer_id'];
								}
							}
								
							// If the IP address from the request contains an address,
							// along with the product ID, we initiate a query into the oc_cart table.
							if (!empty($this->request->get['ip']) && !empty($this->request->get['product_id'])) {
								$cart_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cart` WHERE `ip` = '" . $this->db->escape($this->request->get['REMOTE_ADDR']) . "' AND `product_id` = '" . (int)$this->request->get['product_id'] . "', `customer_id` = '" . (int)$customer_id . "'");
								
								if ($cart_query->num_rows) {
									$json['cart'] = $this->cart->getProducts();
								}
							}
							
							// Customer Logged
							$json['customer_info'] = [];
							
							// If customer online from admin - > systems - > settings - > add / edit settings page,
							// is enabled and that the $customer_id is a match ...
							if ($this->config->get('config_customer_online') && $customer_id) {
								// Checking the selected customer if he is currently online.
								$customer_info = $this->db->query("SELECT `c`.* FROM `" . DB_PREFIX . "customer_online` co LEFT JOIN `" . DB_PREFIX . "customer` c ON (c.`customer_id` = co.`customer_id`) WHERE c.`customer_id` > '0' AND c.`customer_id` = '" . (int)$customer_id . "' AND c.`status` = '1'")->row;
									
								// If he is online ...
								if ($customer_info) {
									// Customer Group
									$this->load->model('account/customer_group');
									
									$customer_group = $this->model_account_customer_group->getCustomerGroup($customer_info['customer_group_id']);
									
									if ($customer_group) {
										$customer_group_id = $customer_group['customer_group_id'];										
										$customer_group_name = $customer_group['name'];
									}
									
									// Remove the customer ID before being returned via the JSON POST.
									unset ($customer_info['customer_id']);
										
									// Merging the customer's results.
									$json['customer_info'] = $customer_info;
										
									// The customer is currently logged in.
									$customer_logged = true;
								}
							}
							
							// Returning the customer logged in result.
							$json['customer_logged'] = $customer_logged;							
							$json['customer_group_name'] = $customer_group_name;
							
							// Totals
							$this->load->model('setting/extension');
								
							$totals = [];
							
							// Uses a stand-alone function with the API,
							// since the cart library can't specifically capture products
							// other than by the relative product IDs of the current logged in customers
							// based on their browser.
							$taxes = $this->cart->getTaxes($json['cart']);
							
							$total = 0;
								
							// Display prices
							if ($customer_logged || !$this->config->get('config_customer_price')) {
								$sort_order = [];
								
								$results = $this->model_setting_extension->getExtensions('total');
									
								foreach ($results as $key => $value) {
									$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
								}
										
								array_multisort($sort_order, SORT_ASC, $results);
										
								foreach ($results as $result) {
									if ($this->config->get('total_' . $result['code'] . '_status')) {
										$this->load->model('extension/total/' . $result['code']);
										
										// __call can not pass-by-reference so we get PHP to call it as an anonymous function.
										($this->model_extension_total_' . $result['code']->getTotal)($totals, $taxes, $total);
									}
								}
								
								$sort_order = [];
									
								foreach ($totals as $key => $value) {
									$sort_order[$key] = $value['sort_order'];
								}
										
								array_multisort($sort_order, SORT_ASC, $totals);
							}
									
							$json['totals'] = [];
									
							foreach ($totals as $total) {
								$json['totals'][] = [
									'title' => $total['title'],
									'text'  => $this->currency->format($total['value'], $this->session->data['oc_api_currency'])
								];
							}
						}
					}
							
					$this->response->addHeader('Content-Type: application/json');
					$this->response->setOutput(json_encode($json));			
				}
			}
		}
	}
}
