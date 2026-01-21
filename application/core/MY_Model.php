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

	// Uncomment for legacy mode
	// protected $legacy_mode = false;

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

	public function set_override($id = null)
	{
		if (!$this->override_column || $this->where_override !== null) {
			return;
		}

		if ($id !== null) {
			$this->override_id = $id;
		}
		if ($this->override_id === null && isset($_SESSION[$this->override_column])) {
			$this->override_id = $_SESSION[$this->override_column];
		}
		if ($this->override_id !== null) {
			$this->where_override = ["{$this->table_name}.{$this->override_column}" => $this->override_id];
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
		$this->apply_common_filters();

		$this->my_db->where($this->primary_key, $id);
		return $this->execute_row();
	}
	public function get_where($where)
	{
		$this->apply_common_filters();

		$this->my_db->where($where);
		return $this->execute_row();
	}

	public function get_all($fields = '', $where = [], $table = '', $limit = '', $order_by = '', $group_by = '')
	{
		$this->apply_list_filters($fields, $where, $limit, $order_by, $group_by);

		return $this->excecute_list($table);
	}

	public function get_all_join($fields = '', $where = [], $table = '', $limit = '', $order_by = '', $group_by = '', $join_table = '', $join_where = '', $join_method = 'left')
	{
		$this->apply_list_filters($fields, $where, $limit, $order_by, $group_by);

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

		return $this->excecute_list($table);
	}

	public function get_all_like($fields = '', $where = array(), $table = '', $limit = '', $order_by = '', $group_by = '')
	{
		$this->apply_list_filters($fields, [], $limit, $order_by, $group_by);

		if (!empty($where)) {
			$this->my_db->like($where);
		}

		return $this->excecute_list($table);
	}

	public function get_all_or_like($fields = '', $where = [], $table = '', $limit = '', $order_by = '')
	{
		$this->apply_list_filters($fields, [], $limit, $order_by);

		if (!empty($where)) {
			$this->my_db->or_like($where);
		}

		return $this->excecute_list($table);
	}

	public function get_all_in($field, $values = [], $table = '', $limit = '', $order_by = '')
	{
		$this->apply_list_filters('', [], $limit, $order_by);

		if (!empty($values)) {
			$this->my_db->where_in($field, $values);
		}

		return $this->excecute_list($table);
	}

	public function get_all_updated($last_update, $fields = '', $where = [], $table = '', $limit = '', $order_by = '', $group_by = '')
	{
		$where = ['last_update >' => $last_update];
		return $this->get_all($fields, $where, $table, $limit, $order_by, $group_by);
	}

	public function count_all($where = NULL)
	{
		$this->apply_common_filters();

		
		$this->my_db->select('count(id) AS count', FALSE);

		if (!empty($where)) {
			$this->my_db->where($where);
		}

		$data = $this->excecute_list();

		return (int)($data[0]['count'] ?? 0);
	}


	public function insert($data)
	{
		$this->check_connect();

		$this->set_alter_keys($data);

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
			$this->set_alter_keys($row);

			if ($this->override_column && $this->override_id) {
				$row[$this->override_column] = $this->override_id;
			}
		}

		$this->my_db->insert_batch($this->table_name, $rows);

		return $this->my_db->affected_rows();
	}

	public function update($data, $id)
	{
		$this->apply_alter_filters();
		$this->set_alter_keys($data);

		if (is_array($id))
			$this->my_db->where_in($this->primary_key, $id);
		else
			$this->my_db->where($this->primary_key, $id);

		return $this->my_db->update($this->table_name, $data);
	}
	public function update_where($data, $where)
	{
		if (empty($where)){
			return false;
		}

		$this->apply_alter_filters();
		$this->set_alter_keys($data);

		$this->my_db->where($where);

		return $this->my_db->update($this->table_name, $data);
	}

	public function upsert($data, $id = null)
	{
		if ($id) {
			if ($this->update($data, $id)) {
				return $id;
			}
		} else {
			return $this->insert($data);
		}

		return FALSE;
	}

	public function upsert_where($data, $where, $insert_data = [])
	{
		$row = $this->get_where($where);

		if (empty($row)) {
			return $this->insert(array_merge($data, $where, $insert_data));	
		}

		if ($this->update($data, $row['id'])) {
			return $row['id'];
		}

		return FALSE;
	}

	public function sync_update_insert($data, $where, $insert = true, $add_sync = false, $add_import = true, $extra_data = [], &$modified = false)
	{
		$this->check_connect();

		$this->cleanup_columns($where, true);
		$row = $this->get_where($where);

		$this->cleanup_columns($data);
		if (!empty($row)) {
			$update_data = [];
			foreach (array_keys($data) as $key) {
				if ($row[$key] != $data[$key])
					$update_data[$key] = $data[$key];
			}

			if (count($update_data) > 0) {
				$this->set_alter_keys($update_data);

				$update_data = array_merge($extra_data, $update_data);
			} else if (!$add_sync) {
				return $row['id'];
			}

			if ($add_sync) {
				$update_data['sync_enabled'] = 1;
			}

			$this->apply_alter_filters();
			$result = $this->my_db->update($this->table_name, $update_data, array('id' => $row['id']));
			if ($result == true) {
				$modified = true;
				return $row['id'];
			}
		} else if ($insert) {
			$this->set_alter_keys($data);

			if ($add_import){
				$data['import_date'] = $data['last_update'];
			}
			if ($add_sync){
				$data['sync_enabled'] = 1;
			}

			$result = $this->my_db->insert($this->table_name, array_merge($data, $where, $extra_data));
			if ($result == true) {
				$modified = true;
				return $this->my_db->insert_id();
			}
		}

		return false;
	}

	public function sync_update($id, $data, $timestamp = true, $row = false, $default_count = 0)
	{
		$this->check_connect();
		$this->cleanup_columns($data);

		if ($row !== false) {
			$update_data = [];

			foreach (array_keys($data) as $key) {
				if ($row[$key] != $data[$key]) {
					$update_data[$key] = $data[$key];
				}
			}

			$update_count = count($update_data);
			if ($update_count == 0) {
				return false;
			}

			if ($timestamp === true && $update_count <= $default_count){
				$timestamp = false;
			}

			$id =  $row['id'];
			$data = $update_data;
		}

		if ($this->use_last_update && $timestamp === true) {
			$data['last_update'] = date('Y-m-d H:i:s');
		}

		$this->apply_alter_filters();
		$this->my_db->where('id', $id);

		return $this->my_db->update($this->table_name, $data);
	}
	public function sync_update_enabled($id, $status)
	{
		$this->check_connect();

		$query = "UPDATE {$this->table_name} SET sync_enabled = $status";
		if ($id !== false)
			$query = "$query WHERE id = $id";

		return $this->my_db->query($query);
	}
	public function sync_commit_enabled()
	{
		$this->check_connect();

		$query = "UPDATE $this->table_name SET enabled = sync_enabled, deleted = !sync_enabled, last_update = ? WHERE enabled != sync_enabled AND (enabled = 0 || enabled = 1)";

		$now = date('Y-m-d H:i:s');
		return $this->my_db->query($query, [$now]);
	}
	public function cleanup_columns(&$data, $only_trim = false)
	{
		foreach ($data as &$row) {
			if (is_string($row)) {
				$row = trim($row);
			}

			if (!$only_trim && $row != 0 && empty($row))
				$row = NULL;
		}
	}

	public function delete($id)
	{
		$this->apply_alter_filters();

		$this->my_db->where($this->primary_key, $id);

		if ($this->soft_delete == false) {
			return $this->my_db->delete($this->table_name);
		}
		
		$this->set_alter_keys($data, $delete = true);

		return $this->my_db->update($this->table_name, $data);
	}

	public function delete_where($where)
	{
		$this->check_connect();

		if (empty($where))
			return false;

		$this->apply_alter_filters();
		$this->my_db->where($where);

		if ($this->soft_delete == false)
			return $this->my_db->delete($this->table_name);

		$data = [];
		$this->set_alter_keys($data, $delete = true);

		return $this->my_db->update($this->table_name, $data);
	}

	public function query($query, $arguments = NULL)
	{
		$this->check_connect();

		$Q = $this->my_db->query($query, $arguments);

		if (is_object($Q)) {
			$data = $Q->result_array();
			$Q->free_result();
			return $data;
		}

		if ($Q === true) {
			return $this->my_db->affected_rows();
		}

		return false;
	}

	public function replace($data)
	{
		$this->apply_alter_filters();
		$this->set_alter_keys($data);

		$success = $this->my_db->replace($this->table_name, $data);
		if ($success) {
			return $this->my_db->insert_id();
		} else {
			return FALSE;
		}
	}

	public function empty_row($properties = null, $include_id = true)
	{
		$this->check_connect();

		if (!$properties) {
			$properties = $this->my_db->list_fields($this->table_name);
			$properties = array_flip($properties);

			if (!$include_id && isset($properties['id'])) {
				unset($properties['id']);
			}
		}

		return array_fill_keys(array_keys($properties), '');
	}

	public function empty_object($properties = null, $include_id = true)
	{
		return (object) $this->empty_row($properties, $include_id);
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

	public function get_unique_hash($length = 13, $field = 'hash')
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
		return $this->get_where([$field => $hash]);
	}

	public function debug_query($return = false)
	{
		$last_query = $this->my_db->last_query();
		if ($return) {
			log_message('debug', $last_query);
			return $last_query;
		}

		echo $last_query;
	}

	public function set_database_time_zone($time_zone)
	{
		$offset = mngr_get_time_zone_offset($time_zone);
		if ($offset !== false) {
			$this->my_db->query("SET SESSION time_zone='$offset'");
		}
	}

	/**
	 * Apply common filters to all queries
	 * 
	 * Ensures database connection is established and applies standard WHERE conditions
	 * that should be present in all queries:
	 * - Override conditions from $this->where_override (e.g., tenant filtering, user scope)
	 * - Soft delete filter to exclude deleted records (if enabled)
	 * 
	 * @return void
	 */
	private function apply_common_filters()
	{
		$this->check_connect();

		if ($this->where_override) {
			$this->my_db->where($this->where_override);
		}

		if ($this->soft_delete) {
			$this->my_db->where('deleted', 0);
		}
	}

	/**
	 * Apply common filters for list/collection queries
	 * Includes field selection, where conditions, pagination, sorting, and grouping
	 * 
	 * @param string $fields Comma-separated field names for SELECT clause (empty = SELECT *)
	 * @param array $where Additional WHERE conditions as associative array
	 * @param string $limit LIMIT clause (e.g., "10" or "10, 20" for offset)
	 * @param string $order_by ORDER BY clause (e.g., "created_at DESC")
	 * @param string $group_by GROUP BY clause (e.g., "category_id")
	 * 
	 */
	private function apply_list_filters($fields = '', $where = [], $limit = '', $order_by = '', $group_by = '')
	{
		$this->apply_common_filters();

		if ($limit != '') {
			$this->my_db->limit($limit);
		}

		if ($order_by != '') {
			$this->my_db->order_by($order_by);
		}

		if ($group_by != '') {
			$this->my_db->group_by($group_by);
		}

		if ($fields != '') {
			$this->my_db->select($fields);
		}

		if (!empty($where)) {
			$this->my_db->where($where);
		}
	}

	/**
	 * Apply WHERE conditions for UPDATE/DELETE operations
	 *
	 * Ensures operations respect override column for data isolation
	 * Also applies where_override if set
	 * 
	 */
	private function apply_alter_filters()
	{
		$this->check_connect();

		if ($this->where_override) {
			$this->my_db->where($this->where_override);
		}
	}

	private function set_alter_keys(&$data, $delete = false)
	{
		if ($this->use_last_update) {
			$data['last_update'] = date('Y-m-d H:i:s');
		}

		if ($delete === true){
			$data['deleted'] = 1;
			$data['enabled'] = 0;
			// $data['deleted_by'] = $this->user_id;
		}
	}

	/**
	 * Execute a single-row query and return result safely
	 * 
	 * Executes the built query on the specified table, handles query failures gracefully,
	 * frees memory after fetching the result, and returns data as an associative array.
	 * This is a helper method for get* methods that fetch a single record.
	 * 
	 * @param string $table The table name to query
	 * @return array|object|null Associative array of the row, null if not found or query fails
	 */
	private function execute_row($table = '')
	{
		if ($table !== ''){
			$Q = $this->my_db->get($table);
		} else {
			$Q = $this->my_db->get($this->table_name);
		}
		

		if ($Q === false) {
			return null;
		}

		// Uncomment for legacy mode
		// $row = $this->legacy_mode ? $Q->row() : $Q->row_array();
		$row = $Q->row();

		$Q->free_result();
		return $row;
	}

	/**
	 * Execute a list query and return results safely
	 * 
	 * Executes the built query on the specified table, handles query failures gracefully,
	 * frees memory after fetching results, and returns data as an array.
	 * This is a helper method for get_all* methods that need to execute queries
	 * with different WHERE conditions (LIKE, IN, BETWEEN, etc.)
	 * 
	 * @param string $table The table name to query
	 * @return array Array of result rows, empty array if query fails or no results found
	 */
	private function excecute_list($table = '')
	{
		if ($table !== '') {
			$Q = $this->my_db->get($table);
		} else {
			$Q = $this->my_db->get($this->table_name);
		}
		if ($Q === false) {
			return [];
		}

		$data = $Q->result_array();
		$Q->free_result();

		return $data;
	}
}
