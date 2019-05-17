<?php
class ControllerExtensionModuleGeoZoneValidation extends Controller {
	// admin/model/localisation/geo_zone/getZone/before
	public function validate(&$route, &$args) {
		$query = "SELECT country_id, COUNT(country_id) AS total_countries, zone_id, COUNT(zone_id) AS total_zones FROM " . DB_PREFIX . "zone_to_geo_zone GROUP BY country_id, zone_id HAVING (COUNT(country_id) > 1) AND (COUNT(zone_id) > 1) AND date_modified < DATE_SUB(NOW(), INTERVAL 1 DAY) LIMIT 50";
		
		if ($query->num_rows) {
			foreach ($query->rows as $result) {
				$this->log->write('Geo Zone Validation: Duplicated countries count: ' . (int)$result['total_countries'] . ' . Duplicated countries count: ' . (int)$result['total_zones'] . '.');
				
				$this->db->query("UPDATE " . DB_PREFIX . "zone_to_geo_zone SET date_modified = NOW() WHERE country_id = '" . (int)$result['country_id'] . " AND zone_id = '" . (int)$result['zone_id'] . "'");
			}
		}
	}
}
