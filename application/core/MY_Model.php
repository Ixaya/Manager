<?php (defined('BASEPATH')) or exit('No direct script access allowed');

class MY_Model extends CI_Model
{
	protected $my_db = null;

	protected $table_name = '';
	protected $primary_key = 'id';
	protected $database_name = '';

	protected $connection_name = '';

	//example: $where_override = array('client_id' => $this->override_id);
	//example: $override_column = 'client_id';
	//example: $override_id = 1;
	protected $where_override = NULL;
	protected $override_column = NULL;
	protected $override_id = NULL;

	protected $save_history = false;
	protected $soft_delete = false;
	protected $use_last_update = TRUE;

	protected $lazy_connect = false;
	protected $connected = false;

	public function __construct()
	{
		$this->load->helper('inflector');

		parent::__construct();

		if (!$this->lazy_connect) {
			$this->connect();
		}
	}

	public function connect($connection_name = NULL)
	{
		if ($connection_name) {
			$this->connection_name = $connection_name;
		}

		if (!empty($this->connection_name)) {
			$this->my_db = $this->load->database_cache($this->connection_name);
		} else {
			$this->my_db = $this->load->database_cache();
		}

		if (strlen($this->database_name)) {
			$this->my_db->db_select($this->database_name);
			//log_message('info', 'Connecting to: '.$this->database_name);
		}

		if (!$this->table_name) {
			$this->generate_table_name();
		}

		$time_zone = date_default_timezone_get();
		$this->set_database_time_zone($time_zone);

		$this->set_override();
		$this->connected = TRUE;
	}

	public function set_connection($db_connection)
	{
		$this->my_db = $db_connection;

		if (!$this->table_name) {
			$this->generate_table_name();
		}

		$this->set_override();
		$this->connected = TRUE;
	}

	public function reconnect_database($connection_name, $database_name, $generate_table_name = FALSE)
	{
		$needs_reload = false;
		if (!empty($database_name) && $this->database_name != $database_name) {
			$this->database_name = $database_name;

			$needs_reload = true;
		}

		if (!empty($connection_name) && $this->connection_name != $connection_name) {
			$this->connection_name = $connection_name;

			$needs_reload = true;
		}

		if ($needs_reload) {
			$this->connect($connection_name);
		}

		if ($generate_table_name) {
			$this->generate_table_name();
		}
	}

	public function check_connect()
	{
		if (!$this->connected) {
			$this->connect();
		}
	}

	protected function generate_table_name()
	{
		$this->table_name = strtolower(get_class($this));
	}

	public function set_override_column($column_name)
	{
		$this->override_column = $column_name;
		$this->set_override();
	}

	public function set_override()
	{
		if ($this->override_column && $this->where_override == null) {
			if ($this->override_id == null && isset($_SESSION[$this->override_column])) {
				$this->override_id = $_SESSION[$this->override_column];
			}

			if ($this->override_id != null) {
				$this->where_override = ["{$this->table_name}.{$this->override_column}" => $this->override_id];
			}
		}
	}

	public function del_override()
	{
		$this->where_override = NULL;
		$this->override_column = NULL;
		$this->override_id = NULL;
	}

	public function get($id)
	{
		$this->check_connect();

		if ($this->where_override)
			$this->my_db->where($this->where_override);

		if ($this->soft_delete == false)
			return $this->my_db->get_where($this->table_name, array($this->primary_key => $id))->row();

		return $this->my_db->get_where($this->table_name, array($this->primary_key => $id, 'deleted' => 0))->row();
	}
	public function get_where($where)
	{
		$this->check_connect();

		if ($this->where_override)
			$this->my_db->where($this->where_override);

		if ($this->soft_delete)
			$this->my_db->where('deleted', 0);

		return $this->my_db->get_where($this->table_name, $where)->row();
	}
	public function get_array($id, $table = null)
	{
		$this->check_connect();

		if (!$table)
			$table = $this->table_name;

		if ($this->where_override)
			$this->my_db->where($this->where_override);

		return $this->my_db->get_where($table, array($this->primary_key => $id))->row_array();
	}


	public function get_all($fields = '', $where = [], $table = '', $limit = '', $order_by = '', $group_by = '')
	{
		$this->check_connect();

		$data = [];
		if ($fields != '') {
			$this->my_db->select($fields);
		}

		if ($this->where_override)
			$this->my_db->where($this->where_override);

		if ($this->soft_delete)
			$this->my_db->where('deleted', 0);

		if (!empty($where)) {
			$this->my_db->where($where);
		}

		if ($table != '') {
			$this->table_name = $table;
		}

		if ($limit != '') {
			$this->my_db->limit($limit);
		}

		if ($order_by != '') {
			$this->my_db->order_by($order_by);
		}

		if ($group_by != '') {
			$this->my_db->group_by($group_by);
		}

		$Q = $this->my_db->get($this->table_name);

		if ($Q->num_rows() > 0) {
			foreach ($Q->result_array() as $row) {
				$data[] = $row;
			}
		}
		$Q->free_result();

		return $data;
	}

	public function get_all_join($fields = '', $where = [], $table = '', $limit = '', $order_by = '', $group_by = '', $join_table = '', $join_where = '', $join_method = 'left')
	{
		$this->check_connect();

		$data = [];
		if ($fields != '') {
			$this->my_db->select($fields);
		}

		if ($this->where_override) {
			$this->my_db->where($this->where_override);
		}

		if ($this->soft_delete)
			$this->my_db->where('deleted', 0);

		if (!empty($where)) {
			$this->my_db->where($where);
		}

		if ($table != '') {
			$this->table_name = $table;
		}

		if ($join_table != '' && $join_where != '') {

			$i = 0;
			if (is_array($join_table) && is_array($join_where)) {

				foreach ($join_table as $jt) {
					$this->my_db->join($join_table[$i], $join_where[$i], $join_method);
					$i++;
				}
			} else {
				$this->my_db->join($join_table, $join_where, $join_method);
			}
		}

		if ($limit != '') {
			$this->my_db->limit($limit);
		}

		if ($order_by != '') {
			$this->my_db->order_by($order_by);
		}

		if ($group_by != '') {
			$this->my_db->group_by($group_by);
		}

		$Q = $this->my_db->get($this->table_name);

		if ($Q->num_rows() > 0) {
			foreach ($Q->result_array() as $row) {
				$data[] = $row;
			}
		}
		$Q->free_result();

		return $data;
	}

	public function get_all_like($fields = '', $where = array(), $table = '', $limit = '', $order_by = '', $group_by = '')
	{
		$this->check_connect();

		$data = array();
		if ($fields != '') {
			$this->my_db->select($fields);
		}

		if ($this->where_override)
			$this->my_db->where($this->where_override);

		if (!empty($where)) {
			$this->my_db->like($where);
		}

		if ($table != '') {
			$this->table_name = $table;
		}

		if ($limit != '') {
			$this->my_db->limit($limit);
		}

		if ($order_by != '') {
			$this->my_db->order_by($order_by);
		}

		if ($group_by != '') {
			$this->my_db->group_by($group_by);
		}

		$Q = $this->my_db->get($this->table_name);

		if ($Q->num_rows() > 0) {
			foreach ($Q->result_array() as $row) {
				$data[] = $row;
			}
		}
		$Q->free_result();

		return $data;
	}

	public function count_all($where = NULL)
	{
		$this->check_connect();

		$count = 0;
		$this->my_db->select('count(id) AS count', FALSE);

		if (!empty($where)) {
			$this->my_db->where($where);
		}

		if ($this->where_override) {
			$this->my_db->where($this->where_override);
		}

		if ($this->soft_delete) {
			$this->my_db->where('deleted', 0);
		}

		$Q = $this->my_db->get($this->table_name);

		if ($Q->num_rows() > 0) {
			$count = $Q->result_array()[0]['count'];
		}

		$Q->free_result();

		return $count;
	}

	public function get_all_updated($last_update, $fields = '', $where = [], $table = '', $limit = '', $order_by = '', $group_by = '')
	{
		$this->check_connect();

		$data = [];
		if ($fields != '') {
			$this->my_db->select($fields);
		}

		if ($this->where_override)
			$this->my_db->where($this->where_override);

		if ($this->soft_delete)
			$this->my_db->where('deleted', 0);

		$this->my_db->where(array('last_update >' => $last_update));


		if (!empty($where)) {
			$this->my_db->where($where);
		}

		if ($table != '') {
			$this->table_name = $table;
		}

		if ($limit != '') {
			$this->my_db->limit($limit);
		}

		if ($order_by != '') {
			$this->my_db->order_by($order_by);
		}

		if ($group_by != '') {
			$this->my_db->group_by($group_by);
		}

		$Q = $this->my_db->get($this->table_name);

		if ($Q->num_rows() > 0) {
			foreach ($Q->result_array() as $row) {
				$data[] = $row;
			}
		}
		$Q->free_result();

		return $data;
	}


	public function insert($data)
	{
		$this->check_connect();

		if ($this->use_last_update) {
			$data['last_update'] = date('Y-m-d H:i:s');
		}

		//$data['created_from_ip'] = $data['updated_from_ip'] = $this->input->ip_address();

		if ($this->override_column && $this->override_id) {
			$data[$this->override_column] = $this->override_id;
		}

		$success = $this->my_db->insert($this->table_name, $data);
		if ($success) {
			return $this->my_db->insert_id();
		} else {
			return FALSE;
		}
	}

	public function insert_bulk($rows)
	{
		if (empty($rows) || !is_array($rows)) {
			return 0;
		}

		foreach ($rows as &$row) {
			$row['last_update'] = date('Y-m-d H:i:s');
			//$row['created_from_ip'] = $row['updated_from_ip'] = $this->input->ip_address();
			//$row['client_id'] = $this->client_id;

			if ($this->override_column && $this->override_id) {
				$row[$this->override_column] = $this->override_id;
			}
		}

		$this->db->insert_batch($this->table_name, $rows);

		return $this->db->affected_rows();
	}

	public function update($data, $id)
	{
		$this->check_connect();

		if ($this->use_last_update) {
			$data['last_update'] = date('Y-m-d H:i:s');
		}

		if ($this->where_override)
			$this->my_db->where($this->where_override);

		//$data['updated_from_ip'] = $this->input->ip_address();
		if (is_array($id))
			$this->my_db->where_in($this->primary_key, $id);
		else
			$this->my_db->where($this->primary_key, $id);
		return $this->my_db->update($this->table_name, $data);
	}
	public function update_where($data, $where)
	{
		$this->check_connect();

		if (empty($where))
			return false;

		if ($this->where_override){
			$this->my_db->where($this->where_override);
		}

		$this->my_db->where($where);

		if ($this->use_last_update) {
			$data['last_update'] = date('Y-m-d H:i:s');
		}

		return $this->my_db->update($this->table_name, $data);
	}

	public function upsert($data, $id = null)
	{
		$this->check_connect();

		if ($id) {
			if ($this->update($data, $id)){
				return $id;
			}
		} else {
			return $this->insert($data);
		}

		return FALSE;
	}

	public function upsert_where($data, $where, $insert_data = [])
	{
		$this->check_connect();

		$row = $this->get_where($where);

		if (!empty($row)) {
			if ($this->update($data, $row->id)){
				return $row->id;
			}
		} else {
			return $this->insert(array_merge($data, $where, $insert_data));
		}

		return FALSE;
	}

	public function delete($id)
	{
		$this->check_connect();

		$this->my_db->where($this->primary_key, $id);

		if ($this->soft_delete == false){
			return $this->my_db->delete($this->table_name);
		}


		if ($this->use_last_update) {
			$data['last_update'] = date('Y-m-d H:i:s');
		}

		$data['deleted'] = 1;
		$data['enabled'] = 0;
		// $data['deleted_by'] = $this->user_id;

		return $this->my_db->update($this->table_name, $data);
	}

	public function delete_array($params)
	{
		$this->check_connect();

		$this->my_db->where($params);

		if ($this->soft_delete == false)
			return $this->my_db->delete($this->table_name);

		$params['deleted'] = 1;
		$params['status'] = 0;
		$params['last_update'] = date('Y-m-d H:i:s');

		return $this->my_db->update($this->table_name, $params);
	}
	public function delete_where($where)
	{
		$this->check_connect();

		if (empty($where))
			return false;

		$this->my_db->where($where);

		if ($this->soft_delete == false)
			return $this->my_db->delete($this->table_name);

		if ($this->use_last_update) {
			$data['last_update'] = date('Y-m-d H:i:s');
		}

		$data['deleted'] = 1;
		$data['enabled'] = 0;
		//$data['delete_by'] = $this->user_id;

		return $this->my_db->update($this->table_name, $data);
	}

	public function query($query, $arguments = NULL)
	{
		$this->check_connect();

		$query = $this->my_db->query($query, $arguments);

		if ($query === true)
			return true;

		if (empty($query))
			return [];

		return $query->result();
	}
	public function query_auto($query, $arguments = NULL)
	{
		return $this->query($query, $arguments);
	}

	public function query_as_array($query, $arguments = NULL)
	{
		$this->check_connect();

		$query = $this->my_db->query($query, $arguments);
		if (empty($query))
			return [];

		return $query->result_array();
	}
	public function query_as_array_auto($query, $arguments = NULL)
	{
		return $this->query_as_array($query, $arguments);
	}

	public function replace($data)
	{
		$this->check_connect();

		if ($this->use_last_update) {
			$data['last_update'] = date('Y-m-d H:i:s');
		}

		//$data['created_from_ip'] = $data['updated_from_ip'] = $this->input->ip_address();

		if ($this->override_column && $this->override_id) {
			$data[$this->override_column] = $this->override_id;
		}

		$success = $this->my_db->replace($this->table_name, $data);
		if ($success) {
			return $this->my_db->insert_id();
		} else {
			return FALSE;
		}
	}

	public function empty_object($properties = null, $include_id = TRUE)
	{
		$this->check_connect();

		if (!$properties) {
			$table = $this->table_name;
			$properties = $this->my_db->list_fields($table);

			$properties = array_flip($properties);
			//array_splice($properties, 0);
			if (!$include_id) {
				if (in_array('id', $properties)) {
					unset($properties['id']);
				}
			}
		}
		//clean any value from array
		$properties = array_fill_keys(array_keys($properties), '');
		$obj = (object)$properties;
		return $obj;
	}

	public function clean_string($text)
	{
		$utf8 = array(
			'/[áàâãªäÁÀÂÃªÄ]/u'	 =>	 'a',
			'/[íìîïÍÌÎÏ]/u'		 =>	 'i',
			'/[éèêëÉÈÊË]/u'		 =>	 'e',
			'/[óòôõºöÓÒÔÕºÖ]/u'	 =>	 'o',
			'/[úùûüÚÙÛÜ]/u'		 =>	 'u',
			'/[çÇ]/u'					 =>	 'c',
			'/[ñÑ]/u'					 =>	 'n',
			'/-/'					 =>	 '_', // UTF-8 hyphen to "normal" hyphen
			'/[’‘‹›‚]/u'		=>	 '_', // Literally a single quote
			'/[“”«»„]/u'		=>	 '_', // Double quote
			'/ /'					 =>	 '_', // nonbreaking space (equiv. to 0x160)
		);

		$clean = preg_replace(array_keys($utf8), array_values($utf8), rtrim($text)); //Remove right spaces and convert special letters
		$clean = strtolower($clean); //Convert to lower case

		return preg_replace("/[^A-Za-z0-9_]/", '', $clean); // Remove special characters
	}

	public function get_datatable_json($custom = "", $where = "")
	{
		$this->check_connect();

		$where_like = [];
		$where_array = [];
		$search_query = "";
		$limit_query = "";
		$order_query = "";
		$response = [];


		if (!empty($this->table_columns)) {
			if ($where != "") {
				$where_array[] = $where;
			}

			if ($this->where_override) {
				foreach ($this->where_override as $wk => $wo) {
					$where_array[] = $wk . " = " . $wo;
				}
				$response['post'] = json_encode($this->where_override);
			}

			if (isset($_POST['search']) && !empty($_POST['search']['value'])) {
				$word_post = htmlspecialchars($_POST['search']['value']);
				$words = explode(" ", $word_post);

				foreach ($words as $word) {
					$like = [];
					$types = array_column($this->table_columns, 'type');
					$colsKeys = array_keys($types, "STRING");

					if (!empty($colsKeys)) {
						foreach ($colsKeys as $key) {
							$like[] = "lower(" . $this->table_columns[$key]['column'] . ") like lower('%" . $word . "%')";
						}
					}

					$types = array_column($this->table_columns, 'type');
					$colsKeys = array_keys($types, "INT");

					if (!empty($colsKeys)) {
						foreach ($colsKeys as $key) {
							$like[] = "CAST(" . $this->table_columns[$key]['column'] . " as CHAR) LIKE '%" . $word . "%'";
						}
					}

					$where_like[] = implode(" OR ", $like);
				}

				$search_query = "( " . implode(") AND (", $where_like) . " )";
				$where_array[] = $search_query;
			}

			$length = 10;

			if (isset($_POST['length'])) {
				$length = intval($_POST['length']);
			}
			if ($_POST['length'] != '-1' && isset($_POST['start'])) {
				$start = intval($_POST['start']);
				$limit_query .= "LIMIT $start, $length";
			}

			$colNames = array_column($this->table_columns, 'column');

			if (!empty($_POST['order'])) {
				foreach ($_POST['order'] as $col) {
					if (isset($colNames[intval($col['column'])])) {
						$order_query .= " " . $colNames[intval($col['column'])] . " " . $col['dir'] . ",";
					}
				}
				if ($order_query != "") {
					$order_query = " ORDER BY " . rtrim($order_query, ",");
				}
			}

			/*if($where != "" && $search_query != ""){
				$search_query = $where." AND ".$search_query;
			}elseif($where != "" && $search_query == ""){
				$search_query = $where;
			} */


			if (count($where_array) > 0) {
				$search_query = " WHERE " . implode(" AND ", $where_array);
			}

			$result = $this->query_as_array("SELECT *,
											   (select count(id) from " . $this->table_name . "
											   " . $search_query . ") as total from " . $this->table_name . "
												" . $search_query . "
												" . $order_query . " " . $limit_query, null);

			if (count($result) > 0) {
				$list_results = [];
				$urlreferences = [];

				if ($custom != "") {
					preg_match_all('#modurl=(.+?)\]#s', $custom, $urlreferences);
				}
				$colNames = array_column($this->table_columns, 'column');
				foreach ($result as $row) {
					$item = [];

					foreach ($colNames as $ky => $col) {
						if (isset($this->table_columns[$ky]['fx'])) {
							$func = $this->table_columns[$ky]['fx'];
							eval('$item[] = ' . $func . ';');
						} else {
							$item[] = $row[$col];
						}
					}

					if ($custom != "") {
						$custom_current = $custom;
						foreach ($urlreferences[0] as $k => $daturl) {

							$columnreferences = [];
							$url_current = $daturl;
							preg_match_all('#modcol=(.+?)\]#s', $daturl, $columnreferences);

							foreach ($columnreferences[1] as $datcol) {
								$url_current = str_replace("[modcol=" . $datcol . "]", $row[$datcol], $url_current);
							}
							$url_current = str_replace("modurl=", "", $url_current);

							$custom_current = str_replace(
								$daturl . "]",
								base_url($url_current),
								$custom_current
							);
						}
						$item[] = str_replace("[", "", $custom_current);
					}
					if (!empty($item)) {
						$list_results[] = $item;
					}
				}
				$response['recordsTotal'] = intval($result[0]['total']);
				$response['recordsFiltered'] = intval($result[0]['total']);
			} else {
				$item = [];
				foreach ($this->table_columns as $column) {
					$item[] = "<td>No data</td>";
				}

				if ($custom != "") {
					$item[] = "";
				}

				$list_results[] = $item;
				$response['recordsTotal'] = 0;
				$response['recordsFiltered'] = 0;
			}



			$response['draw'] = $_POST['draw'];
			$response['data'] = $list_results;



			$json_response = json_encode($response);
			echo ($json_response);

			log_message('DEBUG', $json_response);

			exit;
		} else {
			$response['error'] = "Not declared columns";
		}
	}

	public function get_datatable($config, $where = NULL)
	{
		$this->check_connect();

		if (empty($config)) {
			$dummy_post = '{"draw":"1","columns":[{"data":"0","name":"","searchable":"true","orderable":"true","search":{"value":"","regex":"false"}},{"data":"1","name":"","searchable":"true","orderable":"true","search":{"value":"","regex":"false"}}],"order":[{"column":"0","dir":"asc"}],"start":"0","length":"10","search":{"value":"","regex":"false"}}';
			$config = json_decode($dummy_post, true);
		}

		$where_like = [];
		$where_array = [];
		$search_query = "";
		$limit_query = "";
		$order_query = "";
		$response = [];

		if (empty($this->table_columns))
			return ['error' => 'Columns not declared'];

		if ($where != NULL)
			$where_array[] = $where;

		if ($this->where_override) {
			foreach ($this->where_override as $wk => $wo) {
				$where_array[] = $wk . " = " . $wo;
			}
			$response['post'] = json_encode($this->where_override);
		}

		if (isset($config['search']) && !empty($config['search']['value'])) {
			$word_post = htmlspecialchars($config['search']['value']);
			$words = explode(" ", $word_post);

			foreach ($words as $word) {
				$like = [];

				//Restructure so its only one foreach
				$types = array_column($this->table_columns, 'type');
				$colsKeys = array_keys($types, "STRING");

				if (!empty($colsKeys)) {
					foreach ($colsKeys as $key) {
						$like[] = "lower(`" . $this->table_columns[$key]['column'] . "`) like lower('%" . $word . "%')";
					}
				}

				$types = array_column($this->table_columns, 'type');
				$colsKeys = array_keys($types, "INT");

				if (!empty($colsKeys)) {
					foreach ($colsKeys as $key) {
						$like[] = "CAST(`" . $this->table_columns[$key]['column'] . "` as CHAR) LIKE '%" . $word . "%'";
					}
				}

				$where_like[] = implode(" OR ", $like);
			}

			$search_query = "( " . implode(") AND (", $where_like) . " )";
			$where_array[] = $search_query;
		}

		$length = 10;
		if (isset($config['length']))
			$length = intval($config['length']);

		if ($config['length'] != '-1' && isset($config['start'])) {
			$start = intval($config['start']);
			$limit_query .= "LIMIT $start, $length";
		}

		$colNames = array_column($this->table_columns, 'column');

		if (!empty($config['order'])) {
			foreach ($config['order'] as $col) {
				if (isset($colNames[intval($col['column'])])) {
					$order_query .= " `" . $colNames[intval($col['column'])] . "` " . $col['dir'] . ",";
				}
			}
			if ($order_query != "") {
				$order_query = " ORDER BY " . rtrim($order_query, ",");
			}
		}

		if (count($where_array) > 0)
			$search_query = " WHERE " . implode(" AND ", $where_array);

		$result = $this->query_as_array("SELECT *
												FROM " . $this->table_name . "
												" . $search_query . "
												" . $order_query . " " . $limit_query, null);

		if (count($result) > 0) {
			$list_results = [];

			$colNames = array_column($this->table_columns, 'column');
			foreach ($result as $row) {
				$item = [];

				foreach ($colNames as $ky => $col) {
					if (isset($this->table_columns[$ky]['fx'])) {
						$func = $this->table_columns[$ky]['fx'];
						eval('$item[] = ' . $func . ';');
					} else {
						$item[] = $row[$col];
					}
				}

				if (!empty($item)) {
					$list_results[] = $item;
				}
			}

			$count_result = $this->query_as_array("SELECT count(id) AS total FROM " . $this->table_name . " " . $search_query);
			$response['recordsTotal'] = intval($count_result[0]['total']);
			$response['recordsFiltered'] = intval($count_result[0]['total']);
		} else {
			$item = [];
			foreach ($this->table_columns as $column) {
				$item[] = "<td>No data</td>";
			}

			$list_results[] = $item;
			$response['recordsTotal'] = 0;
			$response['recordsFiltered'] = 0;
		}

		$response['draw'] = $config['draw'];
		$response['data'] = $list_results;

		return $response;
	}

	public function get_hash($length = 13)
	{
		return mngr_generate_hash($length);
	}

	function get_unique_hash($length = 13, $field = 'hash')
	{
		$hash = mngr_generate_hash($length);
		$row = $this->by_hash($hash, $field);
		if (!empty($row)) {
			return $this->get_unique_hash($length, $field);
		}

		return $hash;
	}

	public function by_hash($hash, $field = 'hash')
	{
		return $this->get_where("$field = '$hash'");
	}

	public function set_database_time_zone($time_zone)
	{
		$offset = mngr_get_time_zone_offset($time_zone);
		if ($offset !== false) {
			$this->my_db->query("SET SESSION time_zone='$offset'");
		}
	}
}
