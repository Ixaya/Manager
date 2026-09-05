<?php

defined('BASEPATH') or exit('No direct script access allowed');

class User extends APP_Model_Dyn
{
	private const ALLOWED_ORDER = [
		'id',
		'ip_address',
		'email',
		'first_name',
		'last_name',
		'last_api_date',
		'created_on'
	];

	/**
	 * Validates list params before get_list(); currently only order_by.
	 *
	 * @return ?string Error message if invalid, null if valid.
	 */
	public function get_list_validate(array $params): ?string
	{
		$order_by = $params['order_by'] ?? 'id';
		if (mgr_validate_order_by($order_by, self::ALLOWED_ORDER) === null) {
			return "Invalid order_by column: {$order_by}.";
		}

		return null;
	}

	/**
	 * Paginated user list.
	 *
	 * @return ?array{data: array, total: int} null on a failed query.
	 */
	public function get_list(array $params): ?array
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
			$search = [];
			$search[MGR_Model_Dyn_clause::OR_LIKE] = [
				'first_name' => $params['search'],
				'last_name' => $params['search'],
				'email' => $params['search']
			];

			if (is_numeric($params['search']) && intval($params['search']) == $params['search']) {
				$search[MGR_Model_Dyn_clause::OR_EQUAL] = [
					'id' => (int)$params['search']
				];
			}

			$where[MGR_Model_Dyn_clause::OR_GROUP] = $search;
		}

		if (isset($params['active'])) {
			$where[MGR_Model_Dyn_clause::EQUAL] = ['active' => $params['active']];
		}

		$limit_page = mgr_build_limit_page($params['limit'], $params['page']);
		$order_by = mgr_build_order_by($params['order_by'], $params['order'], self::ALLOWED_ORDER);
		if ($order_by === null) {
			return null;
		}

		$rows = $this->get_all_dynamic(fields: $fields, where: $where, limit: $limit_page, order_by: $order_by);
		if ($rows === null) {
			return null;
		}

		$count_rows = $this->get_all_dynamic(fields: 'count(*) AS count', where: $where);
		$total = (int)($count_rows[0]['count'] ?? 0);

		return ['data' => $rows, 'total' => $total];
	}
}
