<?php
class ModelExtensionReportCustomer extends Model {
	public function getTotalCustomersByDay() {
		$customer_data = array();

		for ($i = 0; $i < 24; $i++) {
			$customer_data[$i] = array(
				'hour'  => $i,
				'total' => 0
			);
		}

		$query = $this->db->query("SELECT COUNT(*) AS total, HOUR(date_added) AS hour FROM `" . DB_PREFIX . "customer` WHERE DATE(date_added) = DATE(NOW()) GROUP BY HOUR(date_added) ORDER BY date_added ASC");

		foreach ($query->rows as $result) {
			$customer_data[$result['hour']] = array(
				'hour'  => $result['hour'],
				'total' => $result['total']
			);
		}

		return $customer_data;
	}

	public function getTotalCustomersByWeek() {
		$customer_data = array();

		$date_start = strtotime('-' . date('w') . ' days');

		for ($i = 0; $i < 7; $i++) {
			$date = date('Y-m-d', $date_start + ($i * 86400));

			$customer_data[date('w', strtotime($date))] = array(
				'day'   => date('D', strtotime($date)),
				'total' => 0
			);
		}

		$query = $this->db->query("SELECT COUNT(*) AS total, date_added FROM `" . DB_PREFIX . "customer` WHERE DATE(date_added) >= DATE('" . $this->db->escape(date('Y-m-d', $date_start)) . "') GROUP BY DAYNAME(date_added)");

		foreach ($query->rows as $result) {
			$customer_data[date('w', strtotime($result['date_added']))] = array(
				'day'   => date('D', strtotime($result['date_added'])),
				'total' => $result['total']
			);
		}

		return $customer_data;
	}

	public function getTotalCustomersByMonth() {
		$customer_data = array();

		for ($i = 1; $i <= date('t'); $i++) {
			$date = date('Y') . '-' . date('m') . '-' . $i;

			$customer_data[date('j', strtotime($date))] = array(
				'day'   => date('d', strtotime($date)),
				'total' => 0
			);
		}

		$query = $this->db->query("SELECT COUNT(*) AS total, date_added FROM `" . DB_PREFIX . "customer` WHERE DATE(date_added) >= '" . $this->db->escape(date('Y') . '-' . date('m') . '-1') . "' GROUP BY DATE(date_added)");

		foreach ($query->rows as $result) {
			$customer_data[date('j', strtotime($result['date_added']))] = array(
				'day'   => date('d', strtotime($result['date_added'])),
				'total' => $result['total']
			);
		}

		return $customer_data;
	}

	public function getTotalCustomersByYear() {
		$customer_data = array();

		for ($i = 1; $i <= 12; $i++) {
			$customer_data[$i] = array(
				'month' => date('M', mktime(0, 0, 0, $i)),
				'total' => 0
			);
		}

		$query = $this->db->query("SELECT COUNT(*) AS total, date_added FROM `" . DB_PREFIX . "customer` WHERE YEAR(date_added) = YEAR(NOW()) GROUP BY MONTH(date_added)");

		foreach ($query->rows as $result) {
			$customer_data[date('n', strtotime($result['date_added']))] = array(
				'month' => date('M', strtotime($result['date_added'])),
				'total' => $result['total']
			);
		}

		return $customer_data;
	}

	public function getOrders($data = array()) {
		$sql = "SELECT c.customer_id, CONCAT(c.firstname, ' ', c.lastname) AS customer, c.email, cgd.name AS customer_group, c.status, o.order_id, SUM(op.quantity) as products, o.total AS total FROM `" . DB_PREFIX . "order` o LEFT JOIN `" . DB_PREFIX . "order_product` op ON (o.order_id = op.order_id) LEFT JOIN `" . DB_PREFIX . "customer` c ON (o.customer_id = c.customer_id) LEFT JOIN `" . DB_PREFIX . "customer_group_description` cgd ON (c.customer_group_id = cgd.customer_group_id) WHERE o.customer_id > 0 AND cgd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(o.date_added) >= '" . $this->db->escape($data['filter_date_start']) . "'";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(o.date_added) <= '" . $this->db->escape($data['filter_date_end']) . "'";
		}

		if (!empty($data['filter_customer'])) {
			$sql .= " AND CONCAT(c.firstname, ' ', c.lastname) LIKE '" . $this->db->escape($data['filter_customer']) . "'";
		}

		if (!empty($data['filter_order_status_id'])) {
			$sql .= " AND o.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		} else {
			$sql .= " AND o.order_status_id > '0'";
		}

		$sql .= " GROUP BY o.order_id";

		$sql = "SELECT t.customer_id, t.customer, t.email, t.customer_group, t.status, COUNT(DISTINCT t.order_id) AS orders, SUM(t.products) AS products, SUM(t.total) AS total FROM (" . $sql . ") AS t GROUP BY t.customer_id ORDER BY total DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalOrders($data = array()) {
		$sql = "SELECT COUNT(DISTINCT o.customer_id) AS total FROM `" . DB_PREFIX . "order` o LEFT JOIN `" . DB_PREFIX . "customer` c ON (o.customer_id = c.customer_id) WHERE o.customer_id > '0'";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(o.date_added) >= '" . $this->db->escape($data['filter_date_start']) . "'";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(o.date_added) <= '" . $this->db->escape($data['filter_date_end']) . "'";
		}

		if (!empty($data['filter_customer'])) {
			$sql .= " AND CONCAT(c.firstname, ' ', c.lastname) LIKE '" . $this->db->escape($data['filter_customer']) . "'";
		}

		if (!empty($data['filter_order_status_id'])) {
			$sql .= " AND o.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		} else {
			$sql .= " AND o.order_status_id > '0'";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getRewardPoints($data = array()) {
		$sql = "SELECT cr.customer_id, CONCAT(c.firstname, ' ', c.lastname) AS customer, c.email, cgd.name AS customer_group, c.status, SUM(cr.points) AS points, COUNT(o.order_id) AS orders, SUM(o.total) AS total FROM " . DB_PREFIX . "customer_reward cr LEFT JOIN `" . DB_PREFIX . "customer` c ON (cr.customer_id = c.customer_id) LEFT JOIN " . DB_PREFIX . "customer_group_description cgd ON (c.customer_group_id = cgd.customer_group_id) LEFT JOIN `" . DB_PREFIX . "order` o ON (cr.order_id = o.order_id) WHERE cgd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(cr.date_added) >= '" . $this->db->escape($data['filter_date_start']) . "'";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(cr.date_added) <= '" . $this->db->escape($data['filter_date_end']) . "'";
		}

		if (!empty($data['filter_customer'])) {
			$sql .= " AND CONCAT(c.firstname, ' ', c.lastname) LIKE '" . $this->db->escape($data['filter_customer']) . "'";
		}

		$sql .= " GROUP BY cr.customer_id ORDER BY points DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalRewardPoints($data = array()) {
		$sql = "SELECT COUNT(DISTINCT cr.customer_id) AS total FROM `" . DB_PREFIX . "customer_reward` cr LEFT JOIN `" . DB_PREFIX . "customer` c ON (cr.customer_id = c.customer_id)";

		$implode = array();

		if (!empty($data['filter_date_start'])) {
			$implode[] = "DATE(cr.date_added) >= '" . $this->db->escape($data['filter_date_start']) . "'";
		}

		if (!empty($data['filter_date_end'])) {
			$implode[] = "DATE(cr.date_added) <= '" . $this->db->escape($data['filter_date_end']) . "'";
		}

		if (!empty($data['filter_customer'])) {
			$implode[] = "CONCAT(c.firstname, ' ', c.lastname) LIKE '" . $this->db->escape($data['filter_customer']) . "'";
		}

		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getCustomerActivities($data = array()) {
		$sql = "SELECT ca.customer_activity_id, ca.customer_id, ca.key, ca.data, ca.ip, ca.date_added FROM " . DB_PREFIX . "customer_activity ca LEFT JOIN " . DB_PREFIX . "customer c ON (ca.customer_id = c.customer_id)";

		$implode = array();

		if (!empty($data['filter_date_start'])) {
			$implode[] = "DATE(ca.date_added) >= '" . $this->db->escape($data['filter_date_start']) . "'";
		}

		if (!empty($data['filter_date_end'])) {
			$implode[] = "DATE(ca.date_added) <= '" . $this->db->escape($data['filter_date_end']) . "'";
		}

		if (!empty($data['filter_customer'])) {
			$implode[] = "CONCAT(c.firstname, ' ', c.lastname) LIKE '" . $this->db->escape($data['filter_customer']) . "'";
		}

		if (!empty($data['filter_ip'])) {
			$implode[] = "ca.ip LIKE '" . $this->db->escape($data['filter_ip']) . "'";
		}

		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}

		$sql .= " ORDER BY ca.date_added DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalCustomerActivities($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "customer_activity` ca LEFT JOIN " . DB_PREFIX . "customer c ON (ca.customer_id = c.customer_id)";

		$implode = array();

		if (!empty($data['filter_date_start'])) {
			$implode[] = "DATE(ca.date_added) >= '" . $this->db->escape($data['filter_date_start']) . "'";
		}

		if (!empty($data['filter_date_end'])) {
			$implode[] = "DATE(ca.date_added) <= '" . $this->db->escape($data['filter_date_end']) . "'";
		}

		if (!empty($data['filter_customer'])) {
			$implode[] = "CONCAT(c.firstname, ' ', c.lastname) LIKE '" . $this->db->escape($data['filter_customer']) . "'";
		}

		if (!empty($data['filter_ip'])) {
			$implode[] = "ca.ip LIKE '" . $this->db->escape($data['filter_ip']) . "'";
		}

		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getCustomerSearches($data = array()) {
		$sql = "SELECT MIN(`cs`.`date_added`) AS `date_start`, MAX(`cs`.`date_added`) AS `date_end`, COUNT(`cs`.`category_id`) AS `categories`, `o`.`payment_country_id`, `o`.`payment_zone_id`, `o`.`payment_method` AS `payment_method`, `o`.`shipping_method` AS `shipping_method`, `o`.`store_id` AS `store_id`, COUNT(*) AS `searches`, SUM((SELECT SUM(`op1`.`quantity`) FROM `" . DB_PREFIX . "order_product` `op1` WHERE `op1`.`product_id` = `p2c`.`product_id` GROUP BY `op1`.`product_id`)) AS `products`, SUM((SELECT SUM(`or`.`product_quantity`) FROM `" . DB_PREFIX . "order_recurring` `or` WHERE `or`.`product_id` = `op`.`product_id` AND `p2c`.`product_id` = `or`.`product_id` AND `or`.`order_id` = `op`.`order_id` AND `or`.`status` = '1' GROUP BY `or`.`product_id`)) AS `recurring_status`, SUM((SELECT SUM(`ot`.`value`) FROM `" . DB_PREFIX . "order_total` `ot` WHERE `ot`.`order_id` = `o`.`order_id` AND `ot`.`code` = 'tax' GROUP BY `ot`.`order_id`)) AS `tax`, SUM(`o`.`total`) AS `total` FROM `" . DB_PREFIX . "customer_search` `cs` INNER JOIN `" . DB_PREFIX . "product_to_category` `p2c` ON (`p2c`.`category_id` = `cs`.`category_id`) INNER JOIN `" . DB_PREFIX . "order_product` `op` ON (`op`.`product_id` = `p2c`.`product_id`) INNER JOIN `" . DB_PREFIX . "order` `o` ON (`o`.`order_id` = `op`.`order_id`) INNER JOIN `" . DB_PREFIX . "language` `l` ON (`l`.`language_id` = `o`.`language_id`)";
					
		$complete_implode = array();
				
		$order_statuses = $this->config->get('config_complete_status');
				
		foreach ($order_statuses as $order_status_id) {
			$complete_implode[] = "`o`.`order_status_id` = '" . (int)$order_status_id . "'";
		}
					
		$processing_implode = array();
					
		$order_statuses = $this->config->get('config_processing_status');
				
		foreach ($order_statuses as $order_status_id) {
			$processing_implode[] = "`o`.`order_status_id` = '" . (int)$order_status_id . "'";
		}
				
		$sql .= " WHERE (" . implode(" OR ", $complete_implode) . ")";
		$sql .= " OR (" . implode(" OR ", $processing_implode) . ")";
					
		$sql .= " AND `cs`.`customer_id` = `o`.`customer_id`";					
		$sql .= " AND `cs`.`language_id` = `o`.`language_id`";
		$sql .= " AND `cs`.`store_id` = `o`.`store_id`";
				
		$sql .= " AND `o`.`language_id` = '" . (int)$this->config->get('config_language_id') . "'";
				
		if (!empty($data['filter_product'])) {
			$sql .= " AND `op`.`product_id` = '" . (int)$data['filter_product'] . "'";
		}
				
		if (!empty($data['filter_country_id'])) {
			$sql .= " AND `o`.`payment_country_id` = '" . (int)$data['filter_country_id'] . "'";
		}
				
		if (!empty($data['filter_zone_id'])) {
			$sql .= " AND `o`.`payment_zone_id` = '" . (int)$data['filter_zone_id'] . "'";
		}
				
		$implode = array();
				
		if (!empty($data['filter_keyword'])) {
			$implode[] = "`cs`.`keyword` LIKE '" . $this->db->escape($data['filter_keyword']) . "%'";
		}

		if (!empty($data['filter_customer'])) {
			$implode[] = "CONCAT(`c`.`firstname`, ' ', `c`.`lastname`) LIKE '" . $this->db->escape($data['filter_customer']) . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}
				
		if (!empty($data['filter_ip'])) {
			$sql .= "AND (`cs`.`ip` LIKE '" . $this->db->escape($data['filter_ip']) . "') OR (`o`.`ip` LIKE '" . $this->db->escape($data['filter_ip']) . "')";
		}
				
		$sql .= " AND `o`.`currency_code` = '" . $this->db->escape($this->config->get('config_currency')) . "'";
				
		$sql .= " AND `o`.`payment_code` NOT LIKE '%free%'";
				
		$sql .= " AND `o`.`total` > '0.10'";
					
		if (!empty($data['filter_group'])) {
			$group = $data['filter_group'];
		} else {
			$group = 'week';	
		}
				
		switch($group) {
			case 'day';
				$sql .= " GROUP BY YEAR(`o`.`date_added`), MONTH(`o`.`date_added`), DAY(`o`.`date_added`), `cs`.`store_id`, `o`.`payment_method`, `o`.`shipping_method`, `o`.`payment_country_id`, `o`.`payment_zone_id` HAVING COUNT(`op`.`quantity`) = MAX(`op`.`quantity`)";
				break;
			default:
			case 'week':
				$sql .= " GROUP BY YEAR(`o`.`date_added`), WEEK(`o`.`date_added`), `cs`.`store_id`, `o`.`payment_method`, `o`.`shipping_method`, `o`.`payment_country_id`, `o`.`payment_zone_id` HAVING COUNT(`op`.`quantity`) = MAX(`op`.`quantity`)";
				break;
			case 'month':
				$sql .= " GROUP BY YEAR(`o`.`date_added`), MONTH(`o`.`date_added`), `cs`.`store_id`, `o`.`payment_method`, `o`.`shipping_method`, `o`.`payment_country_id`, `o`.`payment_zone_id` HAVING COUNT(`op`.`quantity`) = MAX(`op`.`quantity`)";
				break;
			case 'year':
				$sql .= " GROUP BY YEAR(`o`.`date_added`), `cs`.`store_id`, `o`.`payment_method`, `o`.`shipping_method`, `o`.`payment_country_id`, `o`.`payment_zone_id` HAVING COUNT(`op`.`quantity`) = MAX(`op`.`quantity`)";
				break;
		}
			
		$sql .= " ORDER BY `op`.`quantity` DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}
				
		$query = $this->db->query($sql);
			
		return $query->rows;
	}

	public function getTotalCustomerSearches($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "customer_search` cs LEFT JOIN " . DB_PREFIX . "customer c ON (cs.customer_id = c.customer_id)";

		$implode = array();

		if (!empty($data['filter_date_start'])) {
			$implode[] = "DATE(cs.date_added) >= '" . $this->db->escape($data['filter_date_start']) . "'";
		}

		if (!empty($data['filter_date_end'])) {
			$implode[] = "DATE(cs.date_added) <= '" . $this->db->escape($data['filter_date_end']) . "'";
		}

		if (!empty($data['filter_keyword'])) {
			$implode[] = "cs.keyword LIKE '" . $this->db->escape($data['filter_keyword']) . "%'";
		}

		if (!empty($data['filter_customer'])) {
			$implode[] = "CONCAT(c.firstname, ' ', c.lastname) LIKE '" . $this->db->escape($data['filter_customer']) . "'";
		}

		if (!empty($data['filter_ip'])) {
			$implode[] = "cs.ip LIKE '" . $this->db->escape($data['filter_ip']) . "'";
		}

		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}
}
