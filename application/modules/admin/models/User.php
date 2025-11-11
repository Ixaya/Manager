<?php (defined('BASEPATH')) or exit('No direct script access allowed');

class User extends MY_Model
{
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
