<?php
class ControllerApiLocation extends Controller {
	public function getLocation() {
		$json = array();
		
		$this->load->language('api/location');
			
		if (!isset($this->session->data['api_id']) || !isset($this->session->data['api_session_id'])) {
			$json['error']['warning'] = $this->language->get('error_permission');
		} elseif (!isset($this->request->post['customer_group_id'])) {
			$json['error']['warning'] = $this->language->get('error_customer_group');
		} elseif (empty($this->request->post['address']) || empty($this->request->post['geocode']) || empty($this->request->post['customer_group_id'])) {
			$json['error']['warning'] = $this->language->get('error_location');		
		} elseif (!empty($this->request->post['address']) && !empty($this->request->post['geocode']) && !empty($this->request->post['customer_group_id'])) {
			$this->load->model('account/customer_group');
				
			$customer_group = $this->model_account_customer_group->getCustomerGroup($this->request->post['customer_group_id']);
			
			if (!$customer_group) {
				$json['error']['warning'] = $this->language->get('error_customer_group');								
			} else {
				$this->load->model('account/custom_field');
					
				$custom_fields = $this->model_account_custom_field->getCustomFields($this->request->post['customer_group_id']);
					
				if (!$custom_fields) {
					$json['error']['warning'] = $this->language->get('error_geo_zone_customer_custom_field');
				} else {
					// Payment Address
					if (empty($geo_zones['payment_address'])) {
						$json['error']['payment_address'] = $this->language->get('error_payment_address');
					} else {
						$json['payment_address'] = $geo_zones['payment_address'];
							
						$json['payment_address_success'] = $this->language->get('text_payment_address_success');
					}
						
					// Shipping Address
					if (empty($geo_zones['shipping_address'])) {
						$json['error']['shipping_address'] = $this->language->get('error_shipping_address');						
					} else {
						$json['shipping_address'] = $geo_zones['shipping_address'];
							
						$json['shipping_address_success'] = $this->language->get('text_shipping_address_success');
					}
					
					// Account Management
					if (empty($geo_zones['payment_account'])) {
						$json['error']['payment_account'] = $this->language->get('error_payment_account');
					} else {
						$json['payment_account'] = $geo_zones['payment_account'];						
							
						$json['payment_account_success'] = $this->language->get('text_payment_account_success');
					}
				}			
			}
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
