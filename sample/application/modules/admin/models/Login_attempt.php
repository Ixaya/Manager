<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Login_attempt extends APP_Model_Dyn
{
	public function get_list(array $params): array
	{
		$fields = [
			'login_attempt.login',
			'user.id',
			'COUNT(*) AS attempts',
			'GREATEST(0, 5 - COUNT(*)) AS remaining_attempts',
		];

		$where = [];

		if (!empty($params['search'])) {
			$search = [];
			$search[MGR_Model_Dyn_clause::OR_LIKE] = [
				'login_attempt.login'    => $params['search'],
				'login_attempt.ip_address' => $params['search'],
			];

			if (is_numeric($params['search']) && intval($params['search']) == $params['search']) {
				$search[MGR_Model_Dyn_clause::OR_EQUAL] = [
					'user.id' => (int)$params['search'],
				];
			}

			$where[MGR_Model_Dyn_clause::OR_GROUP] = $search;
		}

		$allowed_order = [
			'login',
			'id',
			'attempts',
			'remaining_attempts',
		];

		$join = [
			new MGR_Model_Dyn_join(
				table: 'user',
				type:  MGR_Model_Dyn_join_type::Left,
				on: [
					MGR_Model_Dyn_clause::EQUAL_COL => ['user.email' => 'login_attempt.login']
				],
			),
		];

		$limit_page = mgr_build_limit_page($params['limit'] ?? 0, $params['page'] ?? 1);
		$order_by   = mgr_build_order_by($params['order_by'] ?? null, $params['order'] ?? null, $allowed_order);

		$rows = $this->get_all_dynamic(
			fields:   $fields,
			where:    $where,
			join:     $join,
			limit:    $limit_page,
			order_by: $order_by,
			group_by: 'login_attempt.login, user.id',
		);

		$count_rows = $this->get_all_dynamic(
			fields:   ['COUNT(*) AS count'],
			where:    $where,
			join:     $join,
			group_by: 'login_attempt.login, user.id',
		);

		$total = (int)($count_rows[0]['count'] ?? 0);

		return ['data' => $rows, 'total' => $total];
	}

	public function get_by_user(string|int $id)
	{
		$fields = [
			'login_attempt.id',
			'user.id AS user_id',
			'user.first_name',
			'user.last_name',
			'user.email',
			'login_attempt.id',
			'login_attempt.ip_address',
			'login_attempt.login',
			$this->build_field_select('time', MgrFunctionType::FromUnixtime, ['login_attempt.time'])
		];

		$join = [
			$this->build_join(
				table: 'user',
				type: MGR_Model_Dyn_join_type::Inner,
				on: [
					MGR_Model_Dyn_clause::EQUAL_COL => ['user.email' => 'login_attempt.login']
				],
			)
		];

		$where = [];
		$where[MGR_Model_Dyn_clause::EQUAL] = ['user.id' => $id];


		$order_by = mgr_build_order_by('login_attempt.time', 'DESC');

		return $this->get_all_dynamic(fields: $fields, join: $join, where: $where, order_by: $order_by);
	}
}
