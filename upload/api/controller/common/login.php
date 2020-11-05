<?php
class ApiControllerCommonLogin extends Controller {
	// At the programmers' complete discretion to modify this
	// authentication method for their remote platforms.
	public function index() {
		$json = [];
		
		// API
		$this->apiLoad->language('common/login');
		
		// Core
		$this->load->model('account/api');

		// Login with API Key
		$api_info = $this->model_account_api->login($this->request->post['username'], $this->request->post['key']);

		if ($api_info) {
			// Check if IP is allowed
			$ip_data = [];
	
			$results = $this->model_account_api->getIps($api_info['api_id']);
	
			foreach ($results as $result) {
				$ip_data[] = trim($result['ip']);
			}
	
			if (!in_array($this->request->server['REMOTE_ADDR'], $ip_data)) {
				$json['error']['ip'] = sprintf($this->language->get('error_ip'), $this->request->server['REMOTE_ADDR']);
			}				
				
			if (!$json) {
				$json['success'] = $this->language->get('text_success');

				$session = new Session($this->config->get('session_engine'), $this->registry);
				$session->start();
				$session->data['api_id'] = $api_info['api_id'];
				
				$this->model_account_api->addSession($api_info['api_id'], $session->getId(), $this->request->server['REMOTE_ADDR']);
				
				// Create Token
				$json['api_token'] = $session->getId();
				
				// Opencart API Version
				$json['opencart_api_version'] = OPENCART_API_VERSION;
			} else {
				$json['error']['key'] = $this->language->get('error_key');
			}
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
