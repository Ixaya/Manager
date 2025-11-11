<?php (defined('BASEPATH')) or exit('No direct script access allowed');

class Login_attempt extends MY_Model
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
            if ($offset)
                $query .= " offset $offset ";
        }

        return $this->query_as_array_auto($query, $bindings);
    }

    public function get_by_user($id){
        $query = "SELECT u.id AS user, u.first_name, u.last_name, u.email, la.id, la.ip_address, la.login, from_unixtime(la.time) AS TIME
                    FROM
                        login_attempt AS la
                        INNER JOIN user AS u ON u.email = la.login
                    WHERE
                        u.id = ?";
        
        return $this->query_as_array_auto($query, [$id]);
    }
    
}