<?php (defined('BASEPATH')) or exit('No direct script access allowed');

class MY_Model extends CI_Model {

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
	protected $soft_delete = false;

	public function __construct() {
		parent::__construct();

		if (!empty($this->connection_name)) {
			$this->db = $this->load->database($this->connection_name, TRUE);
		} else {
			$this->load->database();
		}

		if (!empty($this->database_name)) {
			$this->db->db_select($this->database_name);
			//log_message('info', 'Connecting to: '.$this->database_name);
		}
		$this->load->helper('inflector');

		if (!$this->table_name) {
				  $this->table_name = strtolower(plural(get_class($this)));
			$this->table_name = strtolower(get_class($this));
		}

		if($this->override_column && $this->where_override == null)
		{
			if($this->override_id != null)
				$this->where_override = array($this->override_column => $this->override_id);
			else
			{
				if(isset($_SESSION[$this->override_column]))
				{
					$this->override_id = $_SESSION[$this->override_column];
					$this->where_override = [$this->override_column => $this->override_id];
				}
			}
		}
	}


	public function get($id) {
		if($this->where_override)
			$this->db->where($this->where_override);

		if ($this->soft_delete == false)
			return $this->db->get_where($this->table_name, array($this->primary_key => $id))->row();

		return $this->db->get_where($this->table_name, array($this->primary_key => $id,'deleted' => 0))->row();
	}
		public function get_where($where) {
		if($this->where_override)
			$this->db->where($this->where_override);

		if ($this->soft_delete)
			$this->db->where('deleted', 0);

		return $this->db->get_where($this->table_name, $where)->row();
	}
	public function get_array($id, $table = null)
	{
		if(!$table)
			$table = $this->table_name;

		if($this->where_override)
			$this->db->where($this->where_override);

		return $this->db->get_where($table, array($this->primary_key => $id))->row_array();
	}


	public function get_all($fields = '', $where = array(), $table = '', $limit = '', $order_by = '', $group_by = '') {
		$data = array();
		if ($fields != '') {
			$this->db->select($fields);
		}

		if($this->where_override)
			$this->db->where($this->where_override);

		if ($this->soft_delete)
			$this->db->where('deleted', 0);

		if (!empty($where)) {
			$this->db->where($where);
		}

		if ($table != '') {
			$this->table_name = $table;
		}

		if ($limit != '') {
			$this->db->limit($limit);
		}

		if ($order_by != '') {
			$this->db->order_by($order_by);
		}

		if ($group_by != '') {
			$this->db->group_by($group_by);
		}

		$Q = $this->db->get($this->table_name);

		if ($Q->num_rows() > 0) {
			foreach ($Q->result_array() as $row) {
				$data[] = $row;
			}
		}
		$Q->free_result();

		return $data;
	}

	public function get_all_join($fields = '', $where = array(), $table = '', $limit = '', $order_by = '', $group_by = '', $join_table = '', $join_where = '', $join_method='left')
	{
		$data = array();
		if ($fields != '') {
			$this->db->select($fields);
		}

		if($this->where_override){
			$this->db->where($this->where_override);
		}

		if ($this->soft_delete)
			$this->db->where('deleted', 0);

		if (!empty($where)) {
			$this->db->where($where);
		}

		if ($table != '') {
			$this->table_name = $table;
		}

		if ($join_table != '' && $join_where != '') {

			$i = 0;
			if(is_array($join_table) && is_array($join_where))
			{

				foreach($join_table as $jt)
				{
					$this->db->join($join_table[$i], $join_where[$i], $join_method);
					$i++;
				}
			} else {
				$this->db->join($join_table, $join_where, $join_method);
			}


		}

		if ($limit != '') {
			$this->db->limit($limit);
		}

		if ($order_by != '') {
			$this->db->order_by($order_by);
		}

		if ($group_by != '') {
			$this->db->group_by($group_by);
		}

		$Q = $this->db->get($this->table_name);

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
		$count = 0;
		$this->db->select('count(id) AS count', FALSE);

		if (!empty($where)) {
			$this->db->where($where);
		}

		if($this->where_override){
			$this->db->where($this->where_override);
		}

		if ($this->soft_delete){
			$this->db->where('deleted', 0);
		}

		$Q = $this->db->get($this->table_name);

		if ($Q->num_rows() > 0) {
			$count = $Q->result_array()[0]['count'];
		}

		$Q->free_result();

		return $count;
	}

	public function get_updated($last_update, $fields = '', $where = array(), $table = '', $limit = '', $order_by = '', $group_by = '') {
		$data = array();
		if ($fields != '') {
			$this->db->select($fields);
		}

		if($this->where_override)
			$this->db->where($this->where_override);

		if ($this->soft_delete)
			$this->db->where('deleted', 0);

		$this->db->where(array('last_update >' => $last_update));


		if (!empty($where)) {
			$this->db->where($where);
		}

		if ($table != '') {
			$this->table_name = $table;
		}

		if ($limit != '') {
			$this->db->limit($limit);
		}

		if ($order_by != '') {
			$this->db->order_by($order_by);
		}

		if ($group_by != '') {
			$this->db->group_by($group_by);
		}

		$Q = $this->db->get($this->table_name);

		if ($Q->num_rows() > 0) {
			foreach ($Q->result_array() as $row) {
				$data[] = $row;
			}
		}
		$Q->free_result();

		return $data;
	}


	public function insert($data) {
		$data['last_update'] = date('Y-m-d H:i:s');
		//$data['created_from_ip'] = $data['updated_from_ip'] = $this->input->ip_address();

		if($this->override_column && $this->override_id)
		{
			$data[$this->override_column] = $this->override_id;
		}

		$success = $this->db->insert($this->table_name, $data);
		if ($success) {
			return $this->db->insert_id();
		} else {
			return FALSE;
		}
	}
	public function update($data, $id) {
		$data['last_update'] = date('Y-m-d H:i:s');

		if($this->where_override)
			$this->db->where($this->where_override);

		//$data['updated_from_ip'] = $this->input->ip_address();
		if (is_array($id))
			$this->db->where_in($this->primary_key, $id);
		else
			$this->db->where($this->primary_key, $id);
		return $this->db->update($this->table_name, $data);
	}
	public function update_where($data, $where) {
		if (empty($where))
			return false;

		if($this->where_override)
			$this->db->where($this->where_override);

		$this->db->where($where);

		$data['last_update'] = date('Y-m-d H:i:s');
		return $this->db->update($this->table_name, $data);
	}

	public function upsert($data, $id = null)
	{
		if($id){
			if ($this->update($data, $id))
				return $id;
		} else {
			return $this->insert($data);
		}

		return FALSE;
	}

	public function upsert_where($data, $where, $insert_data = [])
	{
		$row = $this->get_where($where);

		if(!empty($row)){
			if($this->update($data, $row->id));
				return $row->id;
		} else {
			return $this->insert(array_merge($data, $where, $insert_data));
		}

		return FALSE;
	}

	public function delete($id) {
		$this->db->where($this->primary_key, $id);

		if ($this->soft_delete == false)
			return $this->db->delete($this->table_name);


		$data['deleted'] = 1;
		$data['enabled'] = 0;
		$data['last_update'] = date('Y-m-d H:i:s');
		$data['deleted_by'] = $this->user_id;

		return $this->db->update($this->table_name, $data);

	}

	public function delete_array($params) {
		$this->db->where($params);

		if ($this->soft_delete == false)
			return $this->db->delete($this->table_name);

		$params['deleted'] = 1;
		$params['status'] = 0;
		$params['last_update'] = date('Y-m-d H:i:s');

		return $this->db->update($this->table_name, $params);
	}
	public function delete_where($where) {
		if (empty($where))
			return false;

		$this->db->where($where);

		if ($this->soft_delete == false)
			return $this->db->delete($this->table_name);

		$data['deleted'] = 1;
		$data['enabled'] = 0;
		$data['last_update'] = date('Y-m-d H:i:s');
		//$data['delete_by'] = $this->user_id;

		return $this->db->update($this->table_name, $data);
	}

	public function query($query, $arguments = NULL){
		$query = $this->db->query($query, $arguments);

		if ($query === true)
			return true;

		if (empty($query))
			return [];

		return $query->result();
	}

	public function query_auto($query, $arguments = NULL){
		$data = [];

		if($this->where_override)
			$this->db->where($this->where_override);

		if ($this->soft_delete)
			$this->db->where('deleted', 0);

		$query = $this->db->query($query, $arguments);
		$this->db->reset_query();
		if (empty($query))
			return [];

		foreach ($query->result() as $row)
		{
			$data[] = $row;
		}

		return $data;
	}
	public function query_as_array($query, $arguments = NULL){
		$query = $this->db->query($query, $arguments);
		if (empty($query))
			return [];

		return $query->result_array();
	}
	public function query_as_array_auto($query, $arguments = NULL){
		$data = [];

		if($this->where_override)
			$this->db->where($this->where_override);

		if ($this->soft_delete)
			$this->db->where('deleted', 0);

		$query = $this->db->query($query, $arguments);
		$this->db->reset_query();

		if (empty($query))
			return [];

		foreach ($query->result_array() as $row)
		{
			$data[] = $row;
		}

		return $data;
	}

	public function replace($data) {
		$data['last_update'] = date('Y-m-d H:i:s');
		//$data['created_from_ip'] = $data['updated_from_ip'] = $this->input->ip_address();

		if($this->override_column && $this->override_id)
		{
			$data[$this->override_column] = $this->override_id;
		}

		$success = $this->db->replace($this->table_name, $data);
		if ($success) {
			return $this->db->insert_id();
		} else {
			return FALSE;
		}
	}

	public function empty_object($properties = null, $include_id = TRUE)
	{
		if(!$properties)
		{
			$table = $this->table_name;
			$properties = $this->db->list_fields($table);

			$properties = array_flip($properties);
			//array_splice($properties, 0);
			if(!$include_id)
			{
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
		$clean = strtolower($clean);//Convert to lower case

		return preg_replace("/[^A-Za-z0-9_]/", '', $clean); // Remove special characters
	}

	public function get_datatable_json($custom = "", $where = ""){
		$where_like = array();
		$where_array = array();
		$search_query = "";
		$limit_query = "";
		$order_query = "";
		$response = array();


		if(!empty($this->table_columns)){
			if($where != ""){
				$where_array[] = $where;
			}

			if($this->where_override){
				foreach($this->where_override as $wk=>$wo){
					$where_array[] = $wk." = ".$wo;
				}
				$response['post'] = json_encode($this->where_override);
			}

			if(isset($_POST['search']) && !empty($_POST['search']['value'])){
				$word_post =htmlspecialchars($_POST['search']['value']);
				$words = explode(" ", $word_post);

				foreach($words as $word){
					$like = array();
					$types = array_column($this->table_columns, 'type');
					$colsKeys = array_keys($types, "STRING");

					if(!empty($colsKeys)){
						foreach($colsKeys as $key){
							$like[] ="lower(".$this->table_columns[$key]['column'].") like lower('%".$word."%')";
						}
					}

					$types = array_column($this->table_columns, 'type');
					$colsKeys = array_keys($types, "INT");

					if(!empty($colsKeys)){
						foreach($colsKeys as $key){
							$like[] ="CAST(".$this->table_columns[$key]['column']." as CHAR) LIKE '%".$word."%'";
						}
					}

					$where_like[] = implode(" OR ", $like);
				}

				if(count($where_like) > 0){
					$search_query = "( ".implode(") AND (", $where_like)." )";
					$where_array[] = $search_query;
				}
			}

			$length = 10;

			if(isset($_POST['length']))
			{
				$length = intval($_POST['length']);

			}
			if($_POST['length'] != '-1' && isset($_POST['start']))
			{
				$start = intval($_POST['start']);
				$limit_query .= "LIMIT $start, $length";
			}

			$colNames = array_column($this->table_columns, 'column');

			if(!empty($_POST['order'])){
				foreach($_POST['order'] as $col){
					if(isset($colNames[intval($col['column'])])){
						$order_query .= " ".$colNames[intval($col['column'])]." ".$col['dir'].",";
					}
				}
				if($order_query != ""){
					$order_query = " ORDER BY ".rtrim($order_query, ",");
				}
			}

			/*if($where != "" && $search_query != ""){
				$search_query = $where." AND ".$search_query;
			}elseif($where != "" && $search_query == ""){
				$search_query = $where;
			} */


			if(count($where_array) > 0){
				$search_query = " WHERE ".implode(" AND ", $where_array);
			}

			$result = $this->query_as_array_auto("SELECT *,
											   (select count(id) from ".$this->table_name."
											   ".$search_query.") as total from ".$this->table_name."
												".$search_query."
												".$order_query." ".$limit_query, null);

			if(count($result)>0){
				$list_results = array();
				$urlreferences = array();

				if($custom != ""){
					preg_match_all('#\modurl=(.+?)\]#s', $custom, $urlreferences);

				}
				$colNames = array_column($this->table_columns, 'column');
				foreach($result as $row){
					$item = array();

					foreach($colNames as $ky=>$col){
						if(isset($this->table_columns[$ky]['fx'])){
							$func = $this->table_columns[$ky]['fx'];
							eval('$item[] = '.$func.';');
						}else{
							$item[] = $row[$col];
						}

					}

					if($custom != ""){
						$custom_current = $custom;
						foreach($urlreferences[0] as $k=>$daturl){

							$columnreferences = array();
							$url_current= $daturl;
							preg_match_all('#\modcol=(.+?)\]#s', $daturl, $columnreferences);

							foreach($columnreferences[1] as $datcol){
								$url_current= str_replace("[modcol=".$datcol."]", $row[$datcol], $url_current);
							}
							$url_current= str_replace("modurl=", "", $url_current);

							$custom_current = str_replace(
								$daturl."]", base_url($url_current), $custom_current);


						}
						$item[]= str_replace("[","",$custom_current);
					}
					if(!empty($item)){
						$list_results[] = $item;
					}

				}
				$response['recordsTotal'] = intval($result[0]['total']);
				$response['recordsFiltered'] = intval($result[0]['total']);



			}else{
				$item = array();
				foreach($this->table_columns as $column){
					$item[]= "<td>No data</td>";
				}

				if($custom != ""){
					$item[]= "";
				}

				$list_results[] = $item;
				$response['recordsTotal'] = 0;
				$response['recordsFiltered'] = 0;
			}



			$response['draw'] = $_POST['draw'];
			$response['data'] = $list_results;



			$json_response = json_encode($response);
			echo($json_response);

			log_message('DEBUG', $json_response);

			exit;

		}else{
			$response['error']= "Not declared columns";
		}

	}

	public function get_datatable($config, $where = NULL){
		if (empty($config)){
			$dummy_post = '{"draw":"1","columns":[{"data":"0","name":"","searchable":"true","orderable":"true","search":{"value":"","regex":"false"}},{"data":"1","name":"","searchable":"true","orderable":"true","search":{"value":"","regex":"false"}}],"order":[{"column":"0","dir":"asc"}],"start":"0","length":"10","search":{"value":"","regex":"false"}}';
			$config = json_decode($dummy_post, true);
		}

		$where_like = [];
		$where_array = [];
		$search_query = "";
		$limit_query = "";
		$order_query = "";
		$response = [];

		if(empty($this->table_columns))
			return ['error'=> 'Columns not declared'];

		if($where != NULL)
			$where_array[] = $where;

		if($this->where_override){
			foreach($this->where_override as $wk=>$wo){
				$where_array[] = $wk." = ".$wo;
			}
			$response['post'] = json_encode($this->where_override);
		}

		if(isset($config['search']) && !empty($config['search']['value'])){
			$word_post =htmlspecialchars($config['search']['value']);
			$words = explode(" ", $word_post);

			foreach($words as $word){
				$like = array();

				//Restructure so its only one foreach
				$types = array_column($this->table_columns, 'type');
				$colsKeys = array_keys($types, "STRING");

				if(!empty($colsKeys)){
					foreach($colsKeys as $key){
						$like[] ="lower(`".$this->table_columns[$key]['column']."`) like lower('%".$word."%')";
					}
				}

				$types = array_column($this->table_columns, 'type');
				$colsKeys = array_keys($types, "INT");

				if(!empty($colsKeys)){
					foreach($colsKeys as $key){
						$like[] ="CAST(`".$this->table_columns[$key]['column']."` as CHAR) LIKE '%".$word."%'";
					}
				}

				$where_like[] = implode(" OR ", $like);
			}

			if(count($where_like) > 0){
				$search_query = "( ".implode(") AND (", $where_like)." )";
				$where_array[] = $search_query;
			}
		}

		$length = 10;
		if(isset($config['length']))
			$length = intval($config['length']);

		if($config['length'] != '-1' && isset($config['start']))
		{
			$start = intval($config['start']);
			$limit_query .= "LIMIT $start, $length";
		}

		$colNames = array_column($this->table_columns, 'column');

		if(!empty($config['order'])){
			foreach($config['order'] as $col){
				if(isset($colNames[intval($col['column'])])){
					$order_query .= " `".$colNames[intval($col['column'])]."` ".$col['dir'].",";
				}
			}
			if($order_query != ""){
				$order_query = " ORDER BY ".rtrim($order_query, ",");
			}
		}

		if(count($where_array) > 0)
			$search_query = " WHERE ".implode(" AND ", $where_array);

		$result = $this->query_as_array("SELECT *
												FROM ".$this->table_name."
												".$search_query."
												".$order_query." ".$limit_query, null);

		if(count($result)>0){
			$list_results = array();
			$urlreferences = array();

			$colNames = array_column($this->table_columns, 'column');
			foreach($result as $row){
				$item = array();

				foreach($colNames as $ky=>$col){
					if(isset($this->table_columns[$ky]['fx'])){
						$func = $this->table_columns[$ky]['fx'];
						eval('$item[] = '.$func.';');
					}else{
						$item[] = $row[$col];
					}
				}

				if(!empty($item)){
					$list_results[] = $item;
				}
			}

			$count_result = $this->query_as_array("SELECT count(id) AS total FROM ".$this->table_name." ".$search_query);
			$response['recordsTotal'] = intval($count_result[0]['total']);
			$response['recordsFiltered'] = intval($count_result[0]['total']);

		}else{
			$item = array();
			foreach($this->table_columns as $column){
				$item[]= "<td>No data</td>";
			}

			$list_results[] = $item;
			$response['recordsTotal'] = 0;
			$response['recordsFiltered'] = 0;
		}

		$response['draw'] = $config['draw'];
		$response['data'] = $list_results;

		return $response;
	}


}
