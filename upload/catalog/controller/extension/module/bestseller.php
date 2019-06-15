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
			$this->session->data['bestseller_setting'] = $setting;
			
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
	
	// catalog/model/checkout/order/addOrder/before
	public function getBestSellerByOrders(&$route, &$args, &$output) {
		if ($this->config->get('config_customer_search') && !empty($this->session->data['bestseller_setting']) && $this->session->data['bestseller_setting']['order_period_notify'] && !empty($this->session->data['bestseller_setting']['order_period']) && (int)$this->session->data['bestseller_setting']['order_period_value'] > 0) {
			$this->load->language('mail/bestseller');
			
			$this->load->model('checkout/order');
			
			$data['store_url'] = HTTP_CATALOG . 'index.php?route=common/home';
			
			$data['store_name'] = $this->config->get('config_name');
			
			$bestsellers = $this->model_checkout_order->getBestSellerByOrders($this->session->data['bestseller_setting']);
			
			$tmp_bestsellers_data = array();
			
			foreach ($bestsellers as $bestseller) {
				if ($bestseller['search_date_end']) {
					$datetime1 = new DateTime($bestseller['date_end']);

					$datetime2 = new DateTime($bestseller['search_date_end']);

					$difference = $datetime1->diff($datetime2);
					
					if ($this->session->data['bestseller_setting']['order_period'] == 'day') {
						$notify = ($difference->d > 1 ? sprintf($this->language->get('text_order_period_days'), $difference->d) : sprintf($this->language->get('text_order_period_day'), $difference->d));
					} elseif ($this->session->data['bestseller_setting']['order_period'] == 'week') {
						$notify = ($difference->d % 7 > 1 ? sprintf($this->language->get('text_order_period_weeks'), $difference->d % 7) : sprintf($this->language->get('text_order_period_week'), $difference->d % 7));
					} elseif ($this->session->data['bestseller_setting']['order_period'] == 'month') {
						$notify = ($difference->m > 1 ? sprintf($this->language->get('text_order_period_months'), $difference->m) : sprintf($this->language->get('text_order_period_month'), $difference->m));
					} elseif ($this->session->data['bestseller_setting']['order_period'] == 'year') {
						$notify = ($difference->y > 1 ? sprintf($this->language->get('text_order_period_years'), $difference->y) : sprintf($this->language->get('text_order_period_year'), $difference->y));
					}
						
					if ($notify) {
						$tmp_bestsellers_data[$bestseller['order_id'] . '|' . $bestseller['search_date_end'] . '|' . $bestseller['searches'] . '|' . $notify][] = $bestseller['products'];
					}					
				}
			}
			
			$bestsellers_data = array();
			
			foreach ($tmp_bestsellers_data as $order => $results) {
				if (!empty($results) && is_array($results) && min($results) > 1) {
					$bestsellers_data['minimum'][$order] = min($results);
				} elseif (!empty($results) && is_array($results) && max($results)) {
					$bestsellers_data['maximum'][$order] = max($results);
				}
			}
			
			$bestsellers = $bestsellers_data;
			
			$data['bestsellers'] = array();
			
			// Minimum products ordered for worst level of sales.
			if (!empty($bestsellers['minimum'])) {
				foreach ($bestsellers['minimum'] as $order => $value) {
					$order_exploded = explode('|', trim($order));
					
					$order_info = $this->model_checkout_order->getOrder($order_exploded[0]);
					
					if ($order_info) {
						$affiliate = $this->model_account_customer->getAffiliateByTracking($order_info['tracking']);
						
						$affiliate_name = '';
						
						$affiliate_href = '';
						
						if ($affiliate) {
							$affiliate_info = $this->model_account_customer->getCustomer($affiliate['customer_id']);
							
							if ($affiliate_info) {
								$affiliate_name = $affiliate_info['firstname'] . ' ' . $affiliate_info['lastname'];
								
								$affiliate_href = $this->url->link('customer/customer', 'user_token=' . $this->session->data['user_token'] . '&customer_id= ' . (int)$affiliate_info['customer_id'] . '&language=' . $this->config->get('config_language'));
							}
						}
						
						$order_products = $this->model_sale_order->getOrderProducts($order_info['order_id']);
						
						if ($order_products) {
							$data['bestsellers']['minimum'][$order_exploded[3]][] = array('payment_firstname'			=> $order_info['payment_firstname'],
																						  'payment_lastname'			=> $order_info['payment_lastname'],
																						  'email'						=> $order_info['email'],
																						  'total'						=> $this->currency->format($order_info['total'], $order_info['currency_code']),
																						  'products'					=> $value,
																						  'searches'					=> $order_exploded[2],
																						  'order_products'				=> $order_products,
																						  'affiliate_name'				=> $affiliate_name,
																						  'affiliate_href'				=> $affiliate_href,
																						  'date_added'					=> date($this->language->get('datetime_format'), $order_info['date_added']),
																						  'search_date_end'				=> date($this->language->get('datetime_format'), $order_exploded[1]),																  
																						 );
						}
																 
						$this->model_checkout_order->setSalesRepMin($order_info['order_id']);
					}
				}
			}
			
			// Maximum products ordered for best level of sales.
			if (!empty($bestsellers['maximum'])) {
				foreach ($bestsellers['maximum'] as $order => $value) {
					$order_exploded = explode('|', trim($order));
					
					$order_info = $this->model_checkout_order->getOrder($order_exploded[0]);
					
					if ($order_info) {
						$affiliate = $this->model_account_customer->getAffiliateByTracking($order_info['tracking']);
						
						$affiliate_name = '';
						
						$affiliate_href = '';
						
						if ($affiliate) {
							$affiliate_info = $this->model_account_customer->getCustomer($affiliate['customer_id']);
							
							if ($affiliate_info) {
								$affiliate_name = $affiliate_info['firstname'] . ' ' . $affiliate_info['lastname'];
								
								$affiliate_href = $this->url->link('customer/customer', 'user_token=' . $this->session->data['user_token'] . '&customer_id= ' . (int)$affiliate_info['customer_id'] . '&language=' . $this->config->get('config_language'));
							}
						}
						
						$order_products = $this->model_sale_order->getOrderProducts($order_info['order_id']);
						
						if ($order_products) {
							$data['bestsellers']['maximum'][$order_exploded[3]][] = array('payment_firstname'			=> $order_info['payment_firstname'],
																						  'payment_lastname'			=> $order_info['payment_lastname'],
																						  'email'						=> $order_info['email'],
																						  'total'						=> $this->currency->format($order_info['total'], $order_info['currency_code']),
																						  'products'					=> $value,																							  
																						  'searches'					=> $order_exploded[2],																  
																						  'order_products'				=> $order_products,
																						  'affiliate_name'				=> $affiliate_name,
																						  'affiliate_href'				=> $affiliate_href,
																						  'date_added'					=> date($this->language->get('datetime_format'), $order_info['date_added']),
																						  'search_date_end'				=> date($this->language->get('datetime_format'), $order_exploded[1]),																  
																						 );
						}
							
						$this->model_checkout_order->setSalesRepMax($order_info['order_id']);					
					}
				}
			}
			
			unset ($this->session->data['bestseller_setting']);
			
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
			$mail->setSubject(html_entity_decode(sprintf($language->get('text_subject'), $this->config->get('config_name')), ENT_QUOTES, 'UTF-8'));
			$mail->setHtml($this->load->view('mail/bestseller', $data));
			
			$mail->send();
		}
	}
}
