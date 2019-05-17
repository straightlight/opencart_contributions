<?php
class Geozone {
	protected $registry;
	
	public function validateGeoZone($registry, $address, $method, $code, $total) {
		$this->registry = (object)$registry;
		
		$this->load->model('localisation/country');
		
		if ($this->config->get($method . '_' . $code . '_total') && $this->config->get($method . '_' . $code . '_total') > $total) {
			$status = false;
		} elseif (!$this->config->get($method . '_' . $code . '_geo_zone_id')) {
			$status = true;
		} elseif ($this->config->get($method . '_' . $code . '_geo_address') == 'geo_zones' && !empty($address['country_id'])) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get($method . '_' . $code . '_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . $address['zone_id'] . "' OR zone_id = '0')");

			if ($query->row) {
				if (!empty($address['zone_id'])) {
					$result = $this->db->query("SELECT * FROM " . DB_PREFIX . "country c LEFT JOIN " . DB_PREFIX . "zone z ON (c.country_id = z.country_id) WHERE c.country_id = '" . (int)$address['country_id'] . "' AND z.zone_id = '" . (int)$address['zone_id'] . "' AND c.status = '1' AND z.status = '1'");
				} else {
					$result = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$address['country_id'] . "' AND status = '1'");
				}

				$status = ($result->num_rows) ? true : false;
			} else {
				$status = false;
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
