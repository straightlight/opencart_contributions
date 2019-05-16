<?php
class Geozone {
	public function validateGeoZone($registry, $address, $code, $total) {
		parent::__construct($registry);
		
		$this->load->model('localisation/country');
		
		if ($this->config->get('payment_' . $code . '_total') > 0 && $this->config->get('payment_' . $code . '_total') > $total) {
			$status = false;
		} elseif (!$this->config->get('payment_' . $code . '_geo_zone_id')) {
			$status = true;
		} elseif ($this->config->get('payment_' . $code . '_geo_address') == 'geo_zones' && !empty($address['country_id'])) {
			$country_info = $this->model_localisation_country->getCountry($address['country_id']);
			
			if ($country_info && $country_info['status'] && $country_info['postcode_required']) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE country_id = '" . (int)$country_info['country_id'] . "' AND ((zone_id = '" . (int)$address['zone_id'] . "') OR (zone_id = '0')) AND status = '1'")->rows;
				
				$country_implode = array();
				
				$zone_implode = array();
				
				foreach ($query as $result) {
					$country_implode[] = "country_id = '" . (int)$result['country_id'] . "'";
					
					$zone_implode[] = "zone_id = '" . (int)$result['zone_id'] . "'";
				}
				
				if ($country_implode && $zone_implode) {
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_' . $code . '_geo_zone_id') . "' AND (" . implode(" OR ", $country_implode) . ") AND (" . implode(" OR ", $zone_implode) . ")");
					
					if ($query->num_rows) {
						$status = true;
					} else {
						$status = false;
					}
				} else {
					$status = false;
				}
			}
			
		} elseif ($this->config->get('payment_' . $code . '_geo_address') == 'addresses' && !empty($address['postcode']) && !empty($address['country_id'])) {
			$country_info = $this->model_localisation_country->getCountry($address['country_id']);

			if ($country_info && $country_info['status'] && $country_info['postcode_required']) {
				$status = true;
			} else {
				$status = false;
			}
		} else {
			$status = false;
		}
		
		return $status;
	}
}
