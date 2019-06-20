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
			if (empty($this->session->data['bestseller_setting'])) {
				$this->session->data['bestseller_setting'] = $setting;
			}
			
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
				
				$searches = 0;
				
				if (!empty($search_results[$result['product_id']])) {
					$searches = sprintf($this->language->get('text_customer_search'), $search_results[$result['product_id']]['searches']);
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
					'searches'	  => $searches,
					'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id'])
				);
			}

			return $this->load->view('extension/module/bestseller', $data);
		}
	}
	
	// catalog/model/checkout/order/addOrder/before
	public function getBestSellerByOrders(&$route, &$args) {
		if ($this->config->get('config_customer_search') && !empty($this->session->data['bestseller_setting']) && $this->session->data['bestseller_setting']['order_period_notify'] && !empty($this->session->data['bestseller_setting']['order_period_value']) && $this->session->data['bestseller_setting']['order_period_value'] > 0) {
			$this->load->language('mail/bestseller');
			
			$this->load->model('checkout/order');
			
			$this->load->model('setting/extension');
			
			$this->load->model('localisation/order_status');
			
			$this->load->model('catalog/category');
			
			$this->load->model('catalog/product');						
			
			$data['store_url'] = HTTP_CATALOG . 'index.php?route=common/home';
			
			$data['store_name'] = $this->config->get('config_name');
			
			$data['text_greeting'] = sprintf($this->language->get('text_greeting'), $this->config->get('config_name'));
			
			$filter_data = array('filter_salesrep'			=> true,
								);
			
			$bestsellers = $this->model_checkout_order->getCustomerSearchesByOrders($this->session->data['bestseller_setting'], $filter_data);
			
			$tmp_bestsellers_data = array();
			
			foreach ($bestsellers as $bestseller) {
				if ($bestseller['search_date_end']) {
					$datetime1 = new DateTime($bestseller['date_end']);

					$datetime2 = new DateTime($bestseller['search_date_end']);

					$difference = $datetime1->diff($datetime2);
					
					if (($difference->d > 0 && $difference->d >= $this->session->data['bestseller_setting']['order_period_value'] && $difference->d <= 364) || ($difference->d % 7 > 0 && $difference->d % 7 >= $this->session->data['bestseller_setting']['order_period_value'] && $difference->d % 7 <= 52) || ($difference->m > 0 && $difference->m >= $this->session->data['bestseller_setting']['order_period_value'] && $difference->m <= 12) || ($difference->y > 0 && $difference->y >= $this->session->data['bestseller_setting']['order_period_value'])) {
						if ($this->session->data['bestseller_setting']['group'] == 'day') {
							$notify = ($difference->d > 1 ? sprintf($this->language->get('text_order_period_days'), $difference->d) : sprintf($this->language->get('text_order_period_day'), $difference->d));
						} elseif ($this->session->data['bestseller_setting']['group'] == 'week') {
							$notify = ($difference->d % 7 > 1 ? sprintf($this->language->get('text_order_period_weeks'), $difference->d % 7) : sprintf($this->language->get('text_order_period_week'), $difference->d % 7));
						} elseif ($this->session->data['bestseller_setting']['group'] == 'month') {
							$notify = ($difference->m > 1 ? sprintf($this->language->get('text_order_period_months'), $difference->m) : sprintf($this->language->get('text_order_period_month'), $difference->m));
						} elseif ($this->session->data['bestseller_setting']['group'] == 'year') {
							$notify = ($difference->y > 1 ? sprintf($this->language->get('text_order_period_years'), $difference->y) : sprintf($this->language->get('text_order_period_year'), $difference->y));
						}
					}
						
					if ($notify) {
						if ($bestseller['min_products']) {
							$product_range = 'minimum';
							
							$salesrep = 1;
						} elseif ($bestseller['max_products']) {
							$product_range = 'maximum';
							
							$salesrep = 2;
						}
						
						$order_info = $this->model_checkout_order->getOrder($bestseller['order_id']);
						
						if ($order_info) {
							$order_totals = $this->model_checkout_order->getOrderTotals($order_info['order_id']);
							
							$affiliate = $this->model_account_customer->getAffiliateByTracking($order_info['tracking']);
							
							$affiliate_id = 0;
							
							$affiliate_name = '';
							
							if ($affiliate) {
								$affiliate_info = $this->model_account_customer->getCustomer($affiliate['customer_id']);							
								
								if ($affiliate_info) {
									$affiliate_id = $affiliate_info['affiliate_id'];
									
									$affiliate_name = $affiliate_info['firstname'] . ' ' . $affiliate_info['lastname'];
								}
							}
							
							$tmp_order_products = $this->model_sale_order->getOrderProducts($order_info['order_id']);
							
							if ($tmp_order_products) {
								$order_products = array();
								
								$option_data = array();
								
								foreach ($tmp_order_products as $order_product) {
									$product_to_categories = $this->model_catalog_product->getCategories($order_product['product_id']);
									
									$categories = array();
									
									foreach ($product_to_categories as $result) {
										if ($result['category_id'] == $bestseller['category_id']) {
											$categories[$order_product['product_id']] = $result['category_id'];
										}
									}
									
									$category = array();
									
									if (!empty($categories[$order_product['product_id']])) {
										$category = $this->model_catalog_category->getCategory($categories[$order_product['product_id']]);
									}
									
									$order_products[] = array('name'				=> $order_product['name'],
															  'model'				=> $order_product['model'],
															  'quantity'			=> $order_product['quantity'],
															  'category'			=> $category,
															  'price'				=> $this->currency->format($order_product['price'], $this->config->get('config_currency')),
															  'total'				=> $this->currency->format($order_product['total'], $this->config->get('config_currency')),
															 );
															 
									$order_options = $this->model_checkout_order->getOrderOptions($order_info['order_id'], $order_product['order_product_id']);

									foreach ($order_options as $option) {
										if ($option['type'] != 'file') {
											$value = $option['value'];
										} else {
											$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

											if ($upload_info) {
												$value = $upload_info['name'];
											} else {
												$value = '';
											}
										}

										$option_data[] = array(
											'name'  => $option['name'],
											'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
										);
									}
								}
								
								$reputable = false;
								
								if (date($this->language->get('datetime_format'), $order_info['date_added']) < date($this->language->get('datetime_format'), $bestseller['search_date_end'])) {
									$date_interval = sprintf($this->language->get('text_order_date_interval_search'), date($this->language->get('datetime_format'), $order_info['date_added']), date($this->language->get('datetime_format'), $bestseller['search_date_end']), $bestseller['products']);
								} elseif (date($this->language->get('datetime_format'), $order_info['date_added']) > date($this->language->get('datetime_format'), $bestseller['search_date_end']) && !empty($order_info['order_ip']) && $order_info['order_ip'] != $order_info['customer_search_ip']) {
									$date_interval = sprintf($this->language->get('text_order_date_interval'), date($this->language->get('datetime_format'), $order_info['date_added']), date($this->language->get('datetime_format'), $bestseller['search_date_end']), $bestseller['products']);
									
									$reputable = true;
								}
								
								$data['bestsellers'][$product_range][] = array('payment_name'					=> $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'],
																			   'email'							=> $order_info['email'],
																			   'total'							=> $this->currency->format($order_info['total'], $this->config->get('config_currency')),
																			   'products'						=> $bestseller['products'],
																			   'searches'						=> $bestseller['searches'],
																			   'order_products'					=> $order_products,
																			   'order_options'					=> $option_data,																	  
																			   'totals'							=> $order_totals,
																			   'affiliate_name'					=> $affiliate_name,
																			   'affiliate_tracking'				=> $order_info['tracking'],
																			   'reputable'						=> $reputable,
																			   'date_interval'					=> $date_interval,
																			  );
																								   
								$this->model_checkout_order->setSalesRep($order_info['order_id'], $salesrep);
							}
						}
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
