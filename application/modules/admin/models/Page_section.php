<?php (defined('BASEPATH')) or exit('No direct script access allowed');

class Page_section extends MY_Model {

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
		$kinds[1] = 'Featured Icon';
		$kinds[2] = 'Showcase';
		$kinds[3] = 'Testimonial';
		$kinds[4] = 'Social Networks';
		$kinds[5] = 'HTML Content';
		$kinds[6] = 'Menu Link';
		return $kinds;
		
	}
	
	public function count_icons()
	{
		$query = 'SELECT count(id) as count FROM page_section WHERE kind = 1';
		
		//obtener en modo objeto
		$result = $this->query($query);
		return $result[0]->count;
		
		
		//obtener en modo arreglo
		$result = $this->query_as_array($query);
		return $result[0]['count'];
	}
}