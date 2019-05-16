<?php
class ControllerCheckoutShippingMethod extends Controller {
	public function index() {
		$this->load->language('checkout/checkout');

		if (isset($this->session->data['shipping_address'])) {
			// Shipping Methods
			$this->load->model('setting/extension');

			$method_data = $this->model_setting_extension->getShippingMethods($this->session->data['shipping_address']);

			$this->session->data['shipping_methods'] = $method_data;
		}

		if (empty($this->session->data['shipping_methods'])) {
			$data['error_warning'] = sprintf($this->language->get('error_no_shipping'), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['shipping_methods'])) {
			$data['shipping_methods'] = $this->session->data['shipping_methods'];
		} else {
			$data['shipping_methods'] = array();
		}

		if (isset($this->session->data['shipping_method']['code'])) {
			$data['code'] = $this->session->data['shipping_method']['code'];
		} else {
			$data['code'] = '';
		}

		if (isset($this->session->data['comment'])) {
			$data['comment'] = $this->session->data['comment'];
		} else {
			$data['comment'] = '';
		}
		
		$this->response->setOutput($this->load->view('checkout/shipping_method', $data));
	}

	public function save() {
		$this->load->language('checkout/checkout');

		$json = array();

		// Validate if shipping is required. If not the customer should not have reached this page.
		if (!$this->cart->hasShipping()) {
			$json['redirect'] = str_replace('&amp;', '&', $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language')));
		}

		// Validate if shipping address has been set.
		if (!isset($this->session->data['shipping_address'])) {
			$json['redirect'] = str_replace('&amp;', '&', $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language')));
		}

		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = str_replace('&amp;', '&', $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language')));
		}

		// Validate minimum quantity requirements.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$json['redirect'] = str_replace('&amp;', '&', $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language')));

				break;
			}
		}

		if (!isset($this->request->post['shipping_method'])) {
			$json['error']['warning'] = $this->language->get('error_shipping');
		} else {
			$shipping = explode('.', $this->request->post['shipping_method']);

			if (!isset($shipping[0]) || !isset($shipping[1]) || !isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])) {
				$json['error']['warning'] = $this->language->get('error_shipping');
			}
		}

		if (!$json) {
			$shipping_code = $this->session->data['shipping_methods'][$this->request->post['shipping_method']]['code'];			

			if (!$this->config->get('shipping_' . $shipping_code . '_status') && (!empty($this->session->data['shipping_address']['country_id']) || !empty($this->session->data['shipping_address']['postcode']))) {
				$json['error']['warning'] = sprintf($this->language->get('error_no_shipping'), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
			} else {
				$this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$this->request->post['shipping_method']];
			}

			$this->session->data['comment'] = strip_tags($this->request->post['comment']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
