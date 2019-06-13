<?php
class ControllerExtensionModuleBestSeller extends Controller {
	public function index($setting) {
		$this->load->language('extension/module/bestseller');

		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		$data['products'] = array();

		$category_id = 0;
		
		$sub_categories = array();
		
		if (isset($this->request->get['path'])) {
			$parts = explode('_', (string)$this->request->get['path']);

			$category_id = (int)array_pop($parts);

			foreach ($parts as $path_id) {
				$sub_categories[] = $path_id;
			}
		}
		
		if (isset($this->request->get['product_id'])) {
			$product_id = $this->request->get['product_id'];
		} else {
			$product_id = 0;
		}
		
		$filter_data = array('category_id'			=> $category_id,
							 'sub_categories'		=> $sub_categories,
							 'product_id'			=> $product_id,
							 'filter'				=> 'product',
							);

		$results = $this->model_catalog_product->getBestSellerProducts($setting, $filter_data);
		
		$filter_data = array('category_id'			=> $category_id,
							 'sub_categories'		=> $sub_categories,
							 'product_id'			=> $product_id,
							 'filter'				=> 'customer_search',
							);
		
		$search_results = $this->model_catalog_product->getBestSellerProducts($setting, $filter_data);
		
		if ($search_results) {
		    $this->load->model('account/search');
		    
		    $this->model_account_search->deleteSearch($search_results, $setting);
		}

		if ($results) {
			foreach ($results as $result) {
				if ($result['image']) {
					$image = $this->model_tool_image->resize($result['image'], $setting['width'], $setting['height']);
				} else {
					$image = $this->model_tool_image->resize('placeholder.png', $setting['width'], $setting['height']);
				}

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$price = false;
				}

				if ((float)$result['special']) {
					$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$special = false;
				}

				if ($this->config->get('config_tax')) {
					$tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price'], $this->session->data['currency']);
				} else {
					$tax = false;
				}

				if ($this->config->get('config_review_status')) {
					$rating = $result['rating'];
				} else {
					$rating = false;
				}

				$data['products'][] = array(
					'product_id'  => $result['product_id'],
					'thumb'       => $image,
					'name'        => $result['name'],
					'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
					'price'       => $price,
					'special'     => $special,
					'tax'         => $tax,
					'rating'      => $rating,
					'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id'])
				);
			}

			return $this->load->view('extension/module/bestseller', $data);
		}
	}
	
	// catalog/model/account/search/addSearch/before
	public function getBestSellersEvent(&$route, &$args) {
		$this->load->language('mail/bestsellers');
		
		$data['bestsellers'] = array();

		$category_id = 0;
		
		$sub_categories = array();
		
		if (isset($this->request->get['path'])) {
			$parts = explode('_', (string)$this->request->get['path']);

			$category_id = (int)array_pop($parts);

			foreach ($parts as $path_id) {
				$sub_categories[] = $path_id;
			}
		}
		
		if (isset($this->request->get['product_id'])) {
			$product_id = $this->request->get['product_id'];
		} else {
			$product_id = 0;
		}
		
		$setting_data = array('group'				=> $this->config->get('module_bestsellers_group'),
							  'type_order'			=> $this->config->get('module_bestsellers_type_order'),
							  'limit'				=> $this->config->get('module_bestsellers_limit'),
							 );
		
		$filter_data = array('category_id'			=> $category_id,
							 'sub_categories'		=> $sub_categories,
							 'product_id'			=> $product_id,
							 'privilege'			=> true,
							 'filter'				=> 'product',
							);

		$this->load->model('catalog/product');
		
		$bestsellers = $this->model_catalog_product->getBestSellerProducts($setting_data, $filter_data);
		
		if ($bestsellers) {
			$this->load->model('checkout/order');
			
			foreach ($bestsellers as $bestseller) {
				$order_recurring = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_recurring` WHERE `product_id` = '" . (int)$bestseller['product_id'] . "'");
				
				if (!$order_recurring || $order_recurring['status']) {
					$order_info = $this->model_checkout_order->getOrder($bestseller['order_product_order_id']);
					
					if ($order_info) {
						$data['bestsellers'][] = array('firstname'				=> $order_info['firstname'],
													   'lastname'				=> $order_info['lastname'],
													   'email'					=> $order_info['email'],
													   'date_added'				=> date($this->language->get('date_format'), $order_info['date_added']),
													   'date_modified'			=> date($this->language->get('date_format'), $order_info['date_modified']),
													   'order_product_name'		=> $bestseller['order_product_name'],
													   'products'				=> $bestseller['products'],
													   'searches'				=> $bestseller['searches'],
													  );
						
						$this->model_checkout_order->setOrderProductPrivileges($bestseller['order_product_order_id']);
					}
				}
			}
			
			// Mail
			$mail = new Mail($this->config->get('config_mail_engine'));
			
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($this->config->get('config_email'));
			$mail->setFrom($this->config->get('config_email'));
			$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode(sprintf($this->language->get('text_subject'), $this->config->get('config_name')), ENT_QUOTES, 'UTF-8'));
			$mail->setText($this->load->view('mail/bestsellers', $data));
			
			$mail->send();
		}
	}
}
