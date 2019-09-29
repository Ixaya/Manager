<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'modules/api/libraries/REST_Controller.php';

class IX_Rest_Controller extends REST_Controller {	
	protected $user_id = '';
	
	public function __construct() {
		parent::__construct();
		
		date_default_timezone_set('UTC');
		
		if (isset($this->_apiuser)){
			$this->rest->db->query("SET SESSION time_zone='-00:00'");
			
			$this->user_id = $this->_apiuser->user_id;
			
			$this->load->library('user_agent');
			
			$now = new DateTime(null, new DateTimeZone('UTC'));
			$data['last_activity_date'] = $now->format('Y-m-d H:i:s');
			$data['last_activity_os'] = $this->getPlatform();
			
			$this->rest->db->where('id', $this->user_id);
			$this->rest->db->update('user', $data);
		}
	}

	public function setupModel($model = false, $modelName = false){
		if ($modelName == false){
			$this->main_model->db->query("SET SESSION time_zone='-00:00'");
			
			if (is_a($this->main_model, 'API_Model'))
				$this->main_model->user_id = $this->user_id;
		} else {
			$this->load->model($model, $modelName);
			$this->{$modelName}->db->query("SET SESSION time_zone='-00:00'");
			
			if (is_a($this->{$modelName}, 'API_Model'))
				$this->{$modelName}->user_id = $this->user_id;
		} 
	}

	public function addAgentData(&$data)
	{
		$data['user_agent'] = $this->agent->agent_string();
		$data['os_kind'] = $this->getPlatform();
	}
	
	public function getPlatform()
	{
		$platform = $this->agent->platform();
		if ($platform == 'iOS')
			return 1;
		if ($platform == 'Android')
			return 2;
		
		return 0;
	}
	
		
	public function print_log($object)
	{
		$now = new DateTime(null, new DateTimeZone('UTC'));
		$timestamp = $now->format('Y-m-d H:i:s');
		echo(PHP_EOL.$timestamp.'('.get_called_class().'): '.json_encode($object));
	}
	
	   
}