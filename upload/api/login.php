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
							
				// Validating the API currency cookie aside from the core.
				if (isset($registry->get('request')->cookie['oc_api_currency']) && !array_key_exists($code, $currencies)) {
					$code = $registry->get('request')->cookie['oc_api_currency'];
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
					
				// If the API currency cookie code does not match,
				// we create a new cookie with the API currency based on the captured code.
				if (!isset($registry->get('request')->cookie['oc_api_currency']) || $registry->get('request')->cookie['oc_api_currency'] != $code) {
					setcookie('oc_api_currency', $code, time() + 60 * 60 * 24 * 30, '/', $registry->get('request')->server['HTTP_HOST']);
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
						'text'  => $registry->get('currency')->format($total['value'], $registry->get('session')->data['currency'])
					);
				}
						
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

						if ($customer_logged || (!$registry->get('config')->get('config_customer_price'))) {
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

						if ($customer_logged) {
							$customer_id = $customer_info['customer_id'];
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
