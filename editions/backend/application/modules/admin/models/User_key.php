<?php (defined('BASEPATH')) or exit('No direct script access allowed');

class User_key extends MY_Model {

	public function __construct() {
		//overrides
		//$this->connection_name = '';
		//$this->table_name = '';
		//$this->override_column = 'user_id';
		//$this->soft_delete = true;

		//initialize after overriding
		parent::__construct();
	}
	
	
}