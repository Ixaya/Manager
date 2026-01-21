<?php (defined('BASEPATH')) or exit('No direct script access allowed');

class Page_item extends MY_Model {

	public function __construct() {
		//overrides
		//$this->connection_name = '';
		//$this->table_name = '';
// 		$this->override_column = 'page_section_id';
		//$this->soft_delete = true;

		//initialize after overriding
		parent::__construct();
	}
	public function kinds()
	{
		$kinds = [];
		$kinds[1] = 'Featured Icon';
		$kinds[2] = 'Showcase';
		$kinds[3] = 'Testimonial';
		$kinds[4] = 'Social Networks';
		$kinds[5] = 'About';
		$kinds[6] = 'Menu Link';
		return $kinds;
		
	}
	
	public function count_icons()
	{
		return $this->count_all(['kind' => 1]);
	}
}