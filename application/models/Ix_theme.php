<?php (defined('BASEPATH')) OR exit('No direct script access allowed');

class Ix_theme extends MY_Model {

	public function __construct() {
		$this->table_name = 'theme';

		//initialize after overriding
		parent::__construct();
	}
}
