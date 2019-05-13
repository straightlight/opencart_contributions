<?php
class ControllerExtensionModuleAddressValidation extends Controller {
	// catalog/model/setting/extension/getPaymentMethods/after
	public function validatePaymentAddress(&$route, &$args, &$output) {
		if (isset($this->session->data['payment_address']) && $this->config->get('config_maintenance')) {
			// Totals
			$totals = array();
			$taxes = $this->cart->getTaxes();
			$total = 0;

			$this->load->model('setting/extension');

			$sort_order = array();

			$results = $this->model_setting_extension->getExtensions('total');

			foreach ($results as $key => $value) {
				$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
			}

			array_multisort($sort_order, SORT_ASC, $results);

			foreach ($results as $result) {
				if ($this->config->get('total_' . $result['code'] . '_status')) {
					$this->load->model('extension/total/' . $result['code']);

					// __call can not pass-by-reference so we get PHP to call it as an anonymous function.
					($this->{'model_extension_total_' . $result['code']}->getTotal)($totals, $taxes, $total);
				}
			}
			
			$method_data = array();
			
			$this->load->model('localisation/country');
			
			$country_info = $this->model_localisation_country->getCountry($this->session->data['payment_address']['country_id']);
			
			if (!$country_info['postcode_required'] && empty($this->session->data['payment_address']['postcode'])) {
				$results = $this->model_setting_extension->getExtensions('payment');

				foreach ($results as $result) {
					if ($this->config->get('payment_' . $result['code'] . '_status') && !$this->config->get('payment_' . $result['code'] . '_geo_zone_id')) {
						$this->load->model('extension/payment/' . $result['code']);

						$method = $this->{'model_extension_payment_' . $result['code']}->getMethod($this->session->data['payment_address'], $total);

						if (!$method || (float)$total < 0.1) {
							$this->load->language('extension/payment/' . $result['code']);
							
							if (!empty($this->language->get('text_title'))) {
								$method_data['payment'][] = $this->language->get('text_title');
							}
						}
					}
				}
			}
			
			if ($method_data) {
				$this->alert($method_data);
			}
		}
	}
	
	// catalog/model/setting/extension/getShippingMethods/after
	public function validateShippingAddress(&$route, &$args, &$output) {
		if (isset($this->session->data['shipping_address']) && $this->config->get('config_maintenance')) {
			$method_data = array();
			
			$this->load->model('localisation/country');
			
			$country_info = $this->model_localisation_country->getCountry($this->session->data['shipping_address']['country_id']);
			
			if (!$country_info['postcode_required'] && empty($this->session->data['shipping_address']['postcode'])) {
				$results = $this->model_setting_extension->getExtensions('shipping');

				foreach ($results as $result) {
					if ($this->config->get('shipping_' . $result['code'] . '_status') && !$this->config->get('shipping_' . $result['code'] . '_geo_zone_id')) {
						$this->load->model('extension/shipping/' . $result['code']);

						$quote = $this->{'model_extension_shipping_' . $result['code']}->getQuote($this->session->data['shipping_address']);

						if (!$quote) {
							$this->load->language('extension/shipping/' . $result['code']);
							
							if (!empty($this->language->get('text_title'))) {
								$method_data['shipping'][] = $this->language->get('text_title');
							}
						}
					}
				}
			}
			
			if ($method_data) {
				$this->alert($method_data);
			}
		}
	}
	
	protected function alert($method_data) {
		if ($method_data) {
			$this->load->language('mail/address_validation');
			
			$address_data = array();
			
			$address_data['text_validation'] = $this->language->get('text_validation');
			
			$address_data['text_date_added'] = $this->language->get('text_date_added');
			
			$address_data['text_payment_method'] = $this->language->get('text_payment_method');
			
			$address_data['text_shipping_method'] = $this->language->get('text_shipping_method');
			
			$address_data['text_method'] = $this->language->get('text_method');
			
			$address_data['text_product'] = $this->language->get('text_product');
			
			$address_data['text_report'] = $this->language->get('text_report');
			
			$address_data['text_footer'] = $this->language->get('text_footer');
			
			$address_data['store_name'] = $this->config->get('config_name');
			
			$address_data['store_url'] = HTTP_CATALOG . 'index.php?route=common/home';

			$address_data['date_added'] = date($this->language->get('datetime_format'));
			
			$address_data['payments'] = array();
			
			if (!empty($method_data['payments'])) {
				$address_data['payments'] = $method_data['payments'];
			}
			
			$address_data['shippings'] = array();
			
			if (!empty($method_data['shippings'])) {
				$address_data['shippings'] = $method_data['shippings'];
			}
			
			$products = $this->cart->getProducts();
			
			$address_data['products'] = array();
			
			foreach ($products as $product) {
				$address_data['products'][] = array('name'			=> html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'),
													'href'			=> HTTP_CATALOG . 'index.php?route=product/product&product_id=' . (int)$product['product_id'],
												   );
			}
			
			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($from);
			$mail->setFrom($from);
			$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode(sprintf($this->language->get('text_subject'), $this->config->get('config_name')), ENT_QUOTES, 'UTF-8'));
			$mail->setHtml($this->load->view('mail/address_validation', $address_data));
			$mail->send();
		}
	}
}
