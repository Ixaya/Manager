<?php

(defined('BASEPATH')) or exit('No direct script access allowed');

class Login_attempt extends IX_Model_Dyn
{
	public function __construct()
	{

		parent::__construct();
	}

	public function get_list($limit, $offset, $order, $search_text)
	{
		$where = "TRUE";
		$bindings = [];

		if (!empty($search_text)) {
			$search_text = htmlspecialchars($search_text);
			$words = array_filter(explode(" ", trim($search_text)));

			$search_conditions = [];

			foreach ($words as $word) {
				$search_conditions[] = "(u.id LIKE ? OR  la.ip_address LIKE ? OR  la.login LIKE ?)";

				$search_term = '%' . $word . '%';

				$bindings[] = $search_term;
				$bindings[] = $search_term;
				$bindings[] = $search_term;
			}

			if (!empty($search_conditions)) {
				$where .= " AND (" . implode(" AND ", $search_conditions) . ")";
			}
		}


		$query = "SELECT
                        COALESCE(u.id, 0) AS id,
                        la.login,
                        COUNT(*) AS attempts,
                        GREATEST(0, 5 - COUNT(*)) AS remaining_attempts
                    FROM login_attempt AS la
                    LEFT JOIN user AS u ON u.email = la.login
                    WHERE $where
                    GROUP BY la.login, u.id";

		if (!empty($order)) {
			$query .= " order by $order ";
		}

		if (!empty($limit)) {
			$query .= " limit $limit ";
			if ($offset) {
				$query .= " offset $offset ";
			}
		}

		return $this->query($query, $bindings);
	}

	// public function get_by_user($id)
	// {
	// 	$time_field = $this->build_field_select('time', MgrFunctionType::FromUnixtime, ['la.time']);
	// 	$query = "SELECT u.id AS user, u.first_name, u.last_name, u.email, la.id, la.ip_address, la.login, $time_field AS `time`
	//                 FROM
	//                     login_attempt AS la
	//                     INNER JOIN user AS u ON u.email = la.login
	//                 WHERE
	//                     u.id = ?";

	// 	return $this->query($query, [$id]);
	// }
	public function get_by_user($id)
	{
		$fields = [
			'login_attempt.id',
			'u.id AS user_id',
			'u.first_name',
			'u.last_name',
			'u.email',
			'login_attempt.id',
			'login_attempt.ip_address',
			'login_attempt.login',
			$this->build_field_select('time', MgrFunctionType::FromUnixtime, ['login_attempt.time'])
		];

		$join = [
			$this->build_join(
				table: 'user AS u',
				type: IX_Model_Dyn_join_type::Inner,
				on: [
					IX_Model_Dyn_clause::EQUAL => ['u.email' => 'login_attempt.login']
				],
			)
		];

		$where = [];
		$where[IX_Model_Dyn_clause::EQUAL] = ['u.id' => $id];


		$order_by = mngr_build_order_by('login_attempt.time', 'DESC');

		return $this->get_all_dynamic(fields: $fields, join: $join, where: $where, order_by: $order_by);
	}
}
