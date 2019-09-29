<?php (defined('BASEPATH')) OR exit('No direct script access allowed');

class API_Model extends MY_Model
{
	public $user_id = '';
	public function __construct()
	{
		parent::__construct();
		
		$this->db->query("SET SESSION time_zone='-00:00'");
		date_default_timezone_set('UTC');
	}

	
	public function insert($data) {
		
		$now = new DateTime(null, new DateTimeZone('UTC'));
		$data['last_update'] = $now->format('Y-m-d H:i:s');
		
		$success = $this->db->insert($this->table_name, $data);
		if ($success) {
			return $this->db->insert_id();
		} else {
			return FALSE;
		}
	}

	public function update($data, $id) {
		$now = new DateTime(null, new DateTimeZone('UTC'));
		$data['last_update'] = $now->format('Y-m-d H:i:s');

		//$data['updated_from_ip'] = $this->input->ip_address();
		if (is_array($id)) 
			$this->db->where_in($this->primary_key, $id);
		else
			$this->db->where($this->primary_key, $id);
		return $this->db->update($this->table_name, $data);
	}
}