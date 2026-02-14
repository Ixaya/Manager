<?php (defined('BASEPATH')) or exit('No direct script access allowed');

class User extends MY_Model
{
	public function get_list($params)
	{
		$fields = [
			'id',
			'ip_address',
			'email',
			'first_name',
			'last_name',
			'last_activity_date',
			'FROM_UNIXTIME(created_on) AS created_on'
		];

		$where = [];
		if (!empty($params['search'])) {
			$seach = [];
			$seach[MY_Model_Clause::OR_LIKE] = [
				'first_name' => $params['search'],
				'last_name' => $params['search'],
				'email' => $params['search']
			];
			$seach[MY_Model_Clause::OR_EQUAL] = [
				'id' => $params['search']
			];

			$where[MY_Model_Clause::OR_GROUP] = $seach;
		}

		if ($params['active']) {
			$where[MY_Model_Clause::EQUAL] = ['active' => $params['active']];
		}

		$allowed_order = [
			'ip_address',
			'email',
			'first_name',
			'last_name',
			'last_activity_date',
			'created_on'
		];
		$limit_page = mngr_build_limit_page($params['limit'], $params['page']);
		$order_by = mngr_build_order_by($params['order_by'], $params['order'], $allowed_order);

		$rows = $this->get_all_dynamic($fields, $where, $limit_page, $order_by);
		$this->debug_query();
		$count_rows = $this->get_all_dynamic('count(*) AS count', $where);

		$total = isset($count_rows[0]['count']) ? $count_rows[0]['count'] : 0;

		return ['data' => $rows, 'total' => $total];
	}
}
