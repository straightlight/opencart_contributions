<?php
class ModelAccountSearch extends Model {
	public function addSearch($data) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_search` SET `store_id` = '" . (int)$this->config->get('config_store_id') . "', `language_id` = '" . (int)$this->config->get('config_language_id') . "', `customer_id` = '" . (int)$data['customer_id'] . "', `keyword` = '" . $this->db->escape($data['keyword']) . "', `category_id` = '" . (int)$data['category_id'] . "', `sub_category` = '" . (int)$data['sub_category'] . "', `description` = '" . (int)$data['description'] . "', `products` = '" . (int)$data['products'] . "', `ip` = '" . $this->db->escape($data['ip']) . "', `date_added` = NOW()");
	}
	
	public function deleteSearch($data, $setting) {
		if (!empty($setting['database_transaction']) && $setting['database_transaction'] == 'delete') {
			$recurring_category_ids = array();
			
			foreach ($data as $result) {
				if (!empty($result['recurring_status']) && $result['recurring_status']) {
					$recurring_category_ids[] = "`category_id` != '" . (int)$result['category_id'] . "' AND `sub_category` != '" . (int)$result['sub_category'] . "'";
				}
			}
			
			if ($recurring_category_ids) {
				$this->db->query("DELETE FROM `" . DB_PREFIX . "customer_search` WHERE ('" . implode(" AND ", $recurring_category_ids) . "') AND `date_added` < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
			} else {
				$this->db->query("DELETE FROM `" . DB_PREFIX . "customer_search` WHERE `date_added` < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
			}
		}
	}
}
