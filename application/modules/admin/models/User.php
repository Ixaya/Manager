<?php (defined('BASEPATH')) OR exit('No direct script access allowed');

class User extends MY_Model {

    public function __construct() {
	    //overrides
        //$this->connection_name = 'catalog';
		//$this->client_id = 1;
// 		$this->table_name = 'ic_user';
		
		//initialize after overriding
		parent::__construct();
    }	
}