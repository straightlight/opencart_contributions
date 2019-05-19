<?php
class ModelLocalisationGeoZone extends Model {
	public function getZoneToGeoZoneByKey($address, $key) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get($key) . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . $address['zone_id'] . "' OR zone_id = '0')");
	
		return $query->row;
	}
	
	public function getZoneToGeoZoneLocation($location, $geocode) {
		if (empty($geocode)) {
			return false;			
		} else {
			$this->load->model('localisation/location');
			
			$geocode_info = $this->model_localisation_location->getLocationByGeocode($geocode);
			
			if (!$geocode_info) {
				return false;
			} else {
				$this->load->model('setting/setting');
				
				$payment_info = $this->model_setting_setting->getSettingValue('payment', $geocode_info['store_id']);
				
				$shipping_info = $this->model_setting_setting->getSettingValue('payment', $geocode_info['store_id']);
				
				if (!$payment_info) {
					return false;
				} else {
					$this->load->model('setting/extension');

					// Totals
					$totals = array();
					$taxes = $this->cart->getTaxes();
					$total = 0;

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

					// Payment Methods
					$address_data = array();

					$results = $this->model_setting_extension->getExtensions('payment');

					foreach ($results as $result) {
						if (!empty($payment_info['payment_' . $result['code'] . '_status']) && $payment_info['payment_' . $result['code'] . '_status']) {
							// Accounts
							if (!empty($payment_info['payment_' . $result['code'] . '_location']) && $location == $payment_info['payment_' . $result['code'] . '_location'] && $payment_info['payment_' . $result['code'] . '_location'] == 'account') {
								$address_data['payment_account'][$result['code']] = true;
							}
							
							// Addresses
							if (!empty($payment_info['payment_' . $result['code'] . '_location']) && $location == $payment_info['payment_' . $result['code'] . '_location'] && $payment_info['payment_' . $result['code'] . '_location'] == 'address') {
								$address_data['payment_address'][$result['code']] = true;
							}
						}
					}

					if ($shipping_info) {
						$results = $this->model_setting_extension->getExtensions('shipping');

						foreach ($results as $result) {
							if (!empty($shipping_info['shipping_' . $result['code'] . '_status']) && $shipping_info['shipping_' . $result['code'] . '_status']) {
								// Addresses
								if (!empty($shipping_info['shipping_' . $result['code'] . '_location']) && $location == $shipping_info['shipping_' . $result['code'] . '_location'] && $shipping_info['shipping_' . $result['code'] . '_location'] == 'address') {
									$address_data['shipping_address'][$result['code']] = true;
								}
							}
						}
					}

					return $address_data;
				}
			}
		}
	}
}
