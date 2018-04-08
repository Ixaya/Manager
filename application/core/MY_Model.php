<?php (defined('BASEPATH')) OR exit('No direct script access allowed');

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

    public function __construct() {
        parent::__construct();

		if (count($this->connection_name)) {
         	$this->db = $this->load->database($this->connection_name, TRUE);   
		} else {
			$this->load->database();
		}
		
		if (count($this->database_name)) {
            $this->db->db_select($this->database_name);
            //log_message('info', 'Connecting to: '.$this->database_name);
        }
        $this->load->helper('inflector');

        if (!$this->table_name) {
//       $this->table_name = strtolower(plural(get_class($this)));
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
					$this->where_override = array($this->override_column => $_SESSION[$this->override_column]);
				}
			}

        }

/*
        if(count($this->client_id) && isset($_SESSION['client_id'])){
	        $this->client_id = $_SESSION['client_id'];
        }
*/
    }
    public function get($id) {
        return $this->db->get_where($this->table_name, array($this->primary_key => $id))->row();
    }

    public function get_all($fields = '', $where = array(), $table = '', $limit = '', $order_by = '', $group_by = '') {
        $data = array();
        if ($fields != '') {
            $this->db->select($fields);
        }

		if($this->where_override)
			$this->db->where($this->where_override);
		
        if (count($where)) {
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
    public function get_updated($last_update, $fields = '', $where = array(), $table = '', $limit = '', $order_by = '', $group_by = '') {
        $data = array();
        if ($fields != '') {
            $this->db->select($fields);
        }

		if($this->where_override)
			$this->db->where($this->where_override);
			
// 		$this->db->where(array('client_id' => $this->client_id));
		$this->db->where(array('last_update >' => $last_update));
		
		
        if (count($where)) {
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
// 		$data['client_id'] = $this->client_id;

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
    
		// if we don't have client_id in array then get this from constructor
		// note, system management modules, override client_id, (Ixaya Management)
		// that is why we use this validation.
/*
        if (!in_array("client_id", $data)) {
	        $data['client_id'] = $this->client_id;
        }
*/
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

        return $this->db->delete($this->table_name);
    }
    public function delete_array($params) {
        return $this->db->delete($this->table_name, $params);
    }
    
    public function query($query){
        return $this->db->query($query)->result();
	}
	public function query_as_array_auto($query, $arguments = NULL){
			$data = array();
//         $this->db->where(array('client_id' => $this->client_id));

		if($this->where_override)
			$this->db->where($this->where_override);
			
        $query = $this->db->query($query, $arguments);
		foreach ($query->result_array() as $row)
		{
		    $data[] = $row;
		}
		//echo($this->db->last_query());
        
        return $data;
	}
	public function query_as_array($query, $arguments = NULL){
        $data = array();
        
		if($this->where_override)
			$this->db->where($this->where_override);
			
        $query = $this->db->query($query, $arguments);
		foreach ($query->result_array() as $row)
		{
		    $data[] = $row;
		}
        return $data;
	}
}