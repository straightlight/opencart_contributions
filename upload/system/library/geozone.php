<?php
class Geozone {
	protected $registry;

	public function validateGeoZone($registry, $address, $method, $code, $total) {
		$this->registry = $registry;

		$this->load->model('localisation/country');

		if ($this->config->get($method . '_' . $code . '_total') && $this->config->get($method . '_' . $code . '_total') > $total) {
			$status = false;
		} elseif ($this->config->get($method . '_' . $code . '_geo_address') == 'geo_zones' && !empty($address['country_id'])) {
			if (!$this->config->get($method . '_' . $code . '_geo_zone_id')) {
				$status = true;
			} else {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get($method . '_' . $code . '_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . $address['zone_id'] . "' OR zone_id = '0')");

				if ($query->row) {
					if (!empty($address['zone_id'])) {
						$country_info = $this->model_localisation_country->getCountry($address['country_id']);

						if ($country_info && $country_info['status']) {
							$this->load->model('localisation/zone');

							$zone_info = $this->model_localisation_zone->getZone($address['zone_id']);

							if ($zone_info && $zone_info['status'] && $zone_info['country_id'] == $address['country_id']) {
								$status = true;
							} else {
								$status = false;
							}
						} else {
							$status = false;
						}
					} else {
						$country_info = $this->model_localisation_country->getCountry($address['country_id']);
						
						if ($country_info && $country_info['status']) {
							$status = true;
						} else {
							$status = false;	
						}
				} else {
					$status = false;
				}
			}
		} elseif ($this->config->get($method . '_' . $code . '_geo_address') == 'addresses' && !empty($address['postcode']) && !empty($address['country_id'])) {
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
	
	public function __get($name) {
		return $this->registry->get($name);	
	}
}
