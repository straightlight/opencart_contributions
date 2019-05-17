<?php
class ControllerExtensionModuleGeoZoneValidation extends Controller {
	// admin/model/localisation/geo_zone/getZone/before
	public function validate(&$route, &$args) {
		$query = $this->db->query("SELECT `gz`.`name` AS `geo_zone_name`, `z2gz`.`country_id`, COUNT(`z2gz`.`country_id`) AS `total_countries`, `z2gz`.`zone_id`, COUNT(`z2gz`.`zone_id`) AS `total_zones` FROM " . DB_PREFIX . "zone_to_geo_zone `z2gz` INNER JOIN `" . DB_PREFIX . "geo_zone` `gz` ON (`gz`.`geo_zone_id` = `z2gz`.`geo_zone_id`) WHERE `z2gz`.`date_modified` < DATE_SUB(NOW(), INTERVAL 1 DAY) GROUP BY `z2gz`.`country_id`, `z2gz`.`zone_id` HAVING (COUNT(`z2gz`.`country_id`) > 1) AND (COUNT(`z2gz`.`zone_id`) > 1) LIMIT 50");
		
		if ($query->num_rows) {
			foreach ($query->rows as $result) {
				$this->log->write('Geo Zone Validation for Geo Zone Name: ' . htmlspecialchars_decode($result['geo_zone_name']) . ' - Duplicated country ID: ' . (int)$result['country_id'] . ' and countries count: ' . (int)$result['total_countries'] . ' . Duplicated zone ID: ' . (int)$result['zone_id'] . ' and zones count: ' . (int)$result['total_zones'] . '.');
				
				$this->db->query("UPDATE " . DB_PREFIX . "zone_to_geo_zone SET date_modified = NOW() WHERE country_id = '" . (int)$result['country_id'] . "' AND zone_id = '" . (int)$result['zone_id'] . "'");
			}
		}
	}
}
