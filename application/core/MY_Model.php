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

	public function query($query){
		$query = $this->db->query($query, $arguments);
		if (empty($query))
			return [];

		return $query->result();
	}
	public function query_auto($query, $arguments = NULL){
		$data = array();

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
		$data = array();

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
			'/[áàâãªä]/u'   =>   'a',
			'/[íìîï]/u'     =>   'i',
			'/[éèêë]/u'     =>   'e',
			'/[óòôõºö]/u'   =>   'o',
			'/[úùûü]/u'     =>   'u',
			'/ç/'           =>   'c',
			'/ñ/'           =>   'n',
			'/–/'           =>   '_', // UTF-8 hyphen to "normal" hyphen
			'/[’‘‹›‚]/u'    =>   '_', // Literally a single quote
			'/[“”«»„]/u'    =>   '_', // Double quote
			'/ /'           =>   '_', // nonbreaking space (equiv. to 0x160)
		);
		$clean = strtolower(rtrim($text));//Remove right spaces and convert to lower case
		$clean = preg_replace(array_keys($utf8), array_values($utf8), $clean); //Convert special letters
		return preg_replace("/[^A-Za-z0-9_]/", '', $clean); // Remove special characters
	}
}