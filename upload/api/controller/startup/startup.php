<?php
class ApiControllerStartupStartup extends Controller {
	public function index() {
		// Store
		if ($this->request->server['HTTPS']) {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "store` WHERE REPLACE(`ssl`, 'www.', '') = '" . $this->db->escape('https://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/') . "'");
		} else {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "store` WHERE REPLACE(`url`, 'www.', '') = '" . $this->db->escape('http://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/') . "'");
		}
			
		if (!$this->config->has('config_store_id')) {
			if (isset($this->request->get['store_id'])) {
				$this->config->set('config_store_id', (int)$this->request->get['store_id']);
			} elseif ($query->num_rows) {
				$this->config->set('config_store_id', $query->row['store_id']);
			} else {
				$this->config->set('config_store_id', 0);
			}
		}

		if (!$this->config->has('config_url') && !$this->config->has('config_ssl')) {
			if (!$query->num_rows) {
				$this->config->set('config_url', HTTP_SERVER);
				$this->config->set('config_ssl', HTTPS_SERVER);
			}
		}
				
		// Settings
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' OR `store_id` = '" . (int)$this->config->get('config_store_id') . "' ORDER BY `store_id` ASC");
				
		foreach ($query->rows as $result) {
			if (!$this->config->has($result['key'])) {
				if (!$result['serialized']) {
					$this->config->set($result['key'], $result['value']);
				} else {
					$this->config->set($result['key'], json_decode($result['value'], true));
				}
			}
		}

		// Language
		$code = '';
				
		$this->load->model('localisation/language');
				
		$languages = $this->model_localisation_language->getLanguages();
				
		if (isset($this->session->data['language'])) {
			$code = $this->session->data['language'];
		}
						
		// Language Detection
		if (!empty($this->request->server['HTTP_ACCEPT_LANGUAGE']) && !array_key_exists($code, $languages)) {
			$detect = '';
					
			$browser_languages = explode(',', $this->request->server['HTTP_ACCEPT_LANGUAGE']);
					
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
			$code = $this->config->get('config_language');
		}
				
		if (!isset($this->session->data['language']) || $this->session->data['language'] != $code) {
			$this->session->data['language'] = $code;
		}
						
		// Overwrite the default language object
		$language = new Language($code);
		$language->load($code);		
		$this->registry->set('language', $language);
				
		// Set the config language_id
		$this->config->set('config_language_id', $languages[$code]['language_id']);	

		// Customer Group
		if (!$this->config->has('config_customer_group_id')) {
			if (isset($this->session->data['customer']) && isset($this->session->data['customer']['customer_group_id'])) {
				// For API calls
				$this->config->set('config_customer_group_id', $this->session->data['customer']['customer_group_id']);
			} elseif ($customer_logged) {
				// Logged in customers
				$this->config->set('config_customer_group_id', $customer_group_id);
			} elseif (isset($this->session->data['guest']) && isset($this->session->data['guest']['customer_group_id'])) {
				$this->config->set('config_customer_group_id', $this->session->data['guest']['customer_group_id']);
			}
		}
				
		// Tracking Code
		if (isset($this->request->get['tracking'])) {
			setcookie('tracking', $this->request->get['tracking'], time() + 3600 * 24 * 1000, '/');
			
			$this->db->query("UPDATE `" . DB_PREFIX . "marketing` SET `clicks` = (clicks + 1) WHERE `code` = '" . $this->db->escape($this->request->get['tracking']) . "'");
		}		
				
		// Currency
		$code = '';
				
		$this->load->model('localisation/currency');
				
		$currencies = $this->model_localisation_currency->getCurrencies();
				
		if (isset($this->session->data['currency'])) {
			$code = $this->session->data['currency'];
		}
				
		if (!array_key_exists($code, $currencies)) {
			$code = $this->config->get('config_currency');
		}
				
		if (!isset($this->session->data['currency']) || $this->session->data['currency'] != $code) {
			$this->session->data['currency'] = $code;
		}
				
		$this->registry->set('currency', new Cart\Currency($this->registry));
				
		// Tax
		$this->registry->set('tax', new Cart\Tax($this->registry));
				
		if (isset($this->session->data['shipping_address']['country_id']) && sset($this->session->data['shipping_address']['zone_id'])) {
			$this->tax->setShippingAddress($this->session->data['shipping_address']['country_id'], $this->session->data['shipping_address']['zone_id']);
		} elseif ($this->config->get('config_tax_default') == 'shipping') {
			$this->tax->setShippingAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
		}

		if (isset($this->session->data['payment_address']['country_id']) && isset($this->session->data['payment_address']['zone_id'])) {
			$this->tax->setPaymentAddress($this->session->data['payment_address']['country_id'], $this->session->data['payment_address']['zone_id']);
		} elseif ($this->config->get('config_tax_default') == 'payment') {
			$this->tax->setPaymentAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
		}

		$this->tax->setStoreAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
				
		// Weight
		$this->registry->set('weight', new Cart\Weight($this->registry));
				
		// Length
		$this->registry->set('length', new Cart\Length($this->registry));
				
		// Encryption
		$this->registry->set('encryption', new Encryption($this->config->get('config_encryption')));
	}
}
