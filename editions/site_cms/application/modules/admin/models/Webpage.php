<?php (defined('BASEPATH')) or exit('No direct script access allowed');

class Webpage extends MY_Model {

	public function __construct() {
		//overrides
		//$this->connection_name = '';
		//$this->table_name = '';
		//$this->override_column = '';
		//$this->soft_delete = true;

		//initialize after overriding
		parent::__construct();
	}
	public function kinds()
	{
		$kinds = [];
		$kinds[1] = 'Frontend';
		$kinds[2] = 'Private';
		$kinds[3] = 'Admin';
		return $kinds;
		
	}
}