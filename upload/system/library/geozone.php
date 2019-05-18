<?php

class Geozone {
	protected $registry;

	public function validateGeoZone($registry, $address, $method, $code, $total) {
		$this->registry = $registry;

		$this->load->model('localisation/country');

		if ($this->config->get($method . '_' . $code . '_total') && $this->config->get($method . '_' . $code . '_total') > $total) {
			if ($this->config->get($method . '_' . $code . '_debug')) {
				$this->log->write(':: ' . strtoupper($code) . ' DEBUG :: TOTAL :: Total amount configured is bigger than order total amount!');	
			}
			
			$status = false;
		} elseif ($this->config->get($method . '_' . $code . '_location') == 'geo_zone' && !empty($address['country_id'])) {
			if (!$this->config->get($method . '_' . $code . '_geo_zone_id')) {
				if ($this->config->get($method . '_' . $code . '_debug')) {
					$this->log->write(':: ' . strtoupper($code) . ' DEBUG :: GEO ZONE :: Geo Zone ID not configured!');	
				}
				
				$status = true;
			} else {
				$this->load->model('localisation/geo_zone');
				
				$zone_to_geo_zone = $this->model_localisation_geo_zone->getZoneToGeoZoneByKey($address, $method . '_' . $code . '_geo_zone_id');

				if ($zone_to_geo_zone) {
					if (!empty($address['zone_id'])) {
						$country_info = $this->model_localisation_country->getCountry($address['country_id']);

						if ($country_info && $country_info['status']) {
							$this->load->model('localisation/zone');

							$zone_info = $this->model_localisation_zone->getZone($address['zone_id']);

							if ($zone_info && $zone_info['status'] && $zone_info['country_id'] == $address['country_id']) {
								if ($this->config->get($method . '_' . $code . '_debug')) {
									$this->log->write(':: ' . strtoupper($code) . ' DEBUG :: GEO ZONE :: Zone: ' . htmlspecialchars_decode($zone_info['name']) . ' is active!');
								}
								
								$status = true;
							} else {
								if ($this->config->get($method . '_' . $code . '_debug')) {
									$this->log->write(':: ' . strtoupper($code) . ' DEBUG :: GEO ZONE :: Relative zone ID: ' . (int)$address['zone_id'] . ' is not active for country name: ' . htmlspecialchars_decode($country_info['name']) . '!');
								}
								
								$status = false;
							}
						} else {
							if ($this->config->get($method . '_' . $code . '_debug')) {
								$this->log->write(':: ' . strtoupper($code) . ' DEBUG :: GEO ZONE :: Relative country ID: ' . (int)$address['country_id'] . ' is not active!');
							}
							
							$status = false;
						}
					} else {
						$country_info = $this->model_localisation_country->getCountry($address['country_id']);
						
						if ($country_info && $country_info['status']) {
							if ($this->config->get($method . '_' . $code . '_debug')) {
								$this->log->write(':: ' . strtoupper($code) . ' DEBUG :: GEO ZONE :: Relative country: ' . htmlspecialchars_decode($country_info['name']) . ' is active!');
							}
							
							$status = true;
						} else {
							if ($this->config->get($method . '_' . $code . '_debug')) {
								$this->log->write(':: ' . strtoupper($code) . ' DEBUG :: GEO ZONE :: Relative country ID: ' . (int)$address['country_id'] . ' is not active!');
							}
							
							$status = false;	
						}
					}
				} else {
					if ($this->config->get($method . '_' . $code . '_debug')) {
						$this->log->write(':: ' . strtoupper($code) . ' DEBUG :: GEO ZONE :: Could not be captured!');
					}
						
					$status = false;
				}
			}
		} elseif ($this->config->get($method . '_' . $code . '_location') == 'address' && !empty($address['postcode']) && !empty($address['country_id'])) {
			$country_info = $this->model_localisation_country->getCountry($address['country_id']);

			if ($country_info && $country_info['status'] && $country_info['postcode_required']) {
				if ($this->config->get($method . '_' . $code . '_debug')) {
					$this->log->write(':: ' . strtoupper($code) . ' DEBUG :: ADDRESS :: Country: ' . htmlspecialchars_decode($country_info['name']) . ' is active!');
				}
				
				$status = true;
			} else {
				if ($this->config->get($method . '_' . $code . '_debug')) {
					$this->log->write(':: ' . strtoupper($code) . ' DEBUG :: ADDRESS :: Relative country ID: ' . (int)$address['country_id'] . ' is not active!');
				}
				
				$status = false;
			}
		} else {
			if ($this->config->get($method . '_' . $code . '_debug')) {
				$this->log->write(':: ' . strtoupper($code) . ' DEBUG :: ADDRESS :: Information could not be captured!');
			}
				
			$status = false;
		}
		
		return $status;
	}
	
	public function __get($name) {
		return $this->registry->get($name);	
	}
}
