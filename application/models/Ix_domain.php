<?php (defined('BASEPATH')) OR exit('No direct script access allowed');

class Ix_domain extends MY_Model {

	public function __construct() {
		$this->table_name = 'domain';

		//initialize after overriding
		parent::__construct();
	}
}
