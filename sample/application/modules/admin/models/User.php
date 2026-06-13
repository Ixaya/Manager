<?php

defined('BASEPATH') or exit('No direct script access allowed');

class User extends APP_Model_Dyn
{
	public function get_list(array $params)
	{
		$fields = [
			'id',
			'ip_address',
			'email',
			'first_name',
			'last_name',
			'last_api_date',
			$this->build_field_select('created_on', MgrFunctionType::FromUnixtime)
		];

		$where = [];
		if (!empty($params['search'])) {
			$seach = [];
			$seach[MGR_Model_Dyn_clause::OR_LIKE] = [
				'first_name' => $params['search'],
				'last_name' => $params['search'],
				'email' => $params['search']
			];

			if (is_numeric($params['search']) && intval($params['search']) == $params['search']) {
				$search[MGR_Model_Dyn_clause::OR_EQUAL] = [
					'id' => (int)$params['search']
				];
			}

			$where[MGR_Model_Dyn_clause::OR_GROUP] = $seach;
		}

		if (isset($params['active'])) {
			$where[MGR_Model_Dyn_clause::EQUAL] = ['active' => $params['active']];
		}

		$allowed_order = [
			'ip_address',
			'email',
			'first_name',
			'last_name',
			'last_activity_date',
			'created_on'
		];
		$limit_page = mgr_build_limit_page($params['limit'], $params['page']);
		$order_by = mgr_build_order_by($params['order_by'], $params['order'], $allowed_order);

		$rows = $this->get_all_dynamic(fields: $fields, where: $where, limit: $limit_page, order_by: $order_by);

		$count_rows = $this->get_all_dynamic(fields: 'count(*) AS count', where: $where);

		$total = isset($count_rows[0]['count']) ? $count_rows[0]['count'] : 0;

		return ['data' => $rows, 'total' => $total];
	}
}
