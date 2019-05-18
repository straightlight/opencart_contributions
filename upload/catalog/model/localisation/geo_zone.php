<?php
class ModelLocalisationGeoZone extends Model {
	public function getZoneToGeoZoneByKey($address, $key) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get($key) . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . $address['zone_id'] . "' OR zone_id = '0')");
	
		return $query->row;
	}
}
