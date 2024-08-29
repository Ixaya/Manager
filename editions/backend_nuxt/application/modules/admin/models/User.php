<?php (defined('BASEPATH')) or exit('No direct script access allowed');

class User extends MY_Model
{
	private $user_groups = NULL;

	public function __construct()
	{
		//overrides
		//$this->connection_name = '';
		//$this->table_name = '';
		//$this->override_column = '';
		//$this->soft_delete = true;

		//initialize after overriding
		parent::__construct();
	}

	public function validate_group($user_id, $group, $url = false)
	{
		if ($this->user_groups === NULL) {
			$this->user_groups = $this->get_user_group_names($user_id);
		}

		if (!is_array($group)) {
			if (in_array($group, $this->user_groups))
				return TRUE;
		} else {
			$result = array_intersect($group, $this->user_groups);
			if (!empty($result))
				return TRUE;
		}

		if ($url == false) {
			return FALSE;
		} else {
			redirect($url);
		}
	}

	public function get_user_group_names($user_id)
	{
		$query = "SELECT g.name FROM user_group
		 					LEFT JOIN `group` AS g ON g.id = user_group.group_id
							WHERE user_id = ? ";
		$user_groups = $this->query_as_array_auto($query, [$user_id]);

		$result = [];
		foreach ($user_groups as $row) {
			$result[] = $row['name'];
		}
		return $result;
	}

	public function get_highest_level($user_id)
	{
		$query = "SELECT g.level FROM user_group
		 					LEFT JOIN `group` AS g ON g.id = user_group.group_id
							WHERE user_id = ?
							ORDER BY g.level DESC
							LIMIT 1";
		$user_group = $this->query_as_array_auto($query, [$user_id]);

		if (!empty($user_group))
			return $user_group[0]['level'];

		return 0;
	}

	public function get_list($params)
	{
		$limit = $params['limit'];
		$offset = ($params['page'] - 1) * $limit;

		$query = "SELECT SQL_CALC_FOUND_ROWS 
                 u.id, 
                 MAX(u.last_update) AS last_activity_date, 
				 MAx(FROM_UNIXTIME(u.created_on)) AS created_on_date,
				 MAX(u.ip_address) AS ip_address, 
                 MAX(u.email) AS email, 
                 MAX(u.first_name) AS first_name, 
				 MAX(u.last_name) AS last_name
          FROM user AS u
          WHERE 1=1 ";

		$binds = [];

		if (!empty($params['search'])) {
			$query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.id LIKE ?) ";
			$binds[] = "%" . $params['search'] . "%";
			$binds[] = "%" . $params['search'] . "%";
			$binds[] = "%" . $params['search'] . "%";
			$binds[] = "%" . $params['search'] . "%";
		}

		$query .= " GROUP BY u.id ORDER BY " . $params['order_by'] . " " . $params['order'] . " LIMIT ? OFFSET ? ";
		$binds[] = $limit;
		$binds[] = $offset;

		$data = $this->query_as_array_auto($query, $binds);

		$total = $this->query_as_array_auto("SELECT FOUND_ROWS() AS total");
		$total = $total[0]['total'];

		return ['data' => $data, 'total' => $total];
	}
}
