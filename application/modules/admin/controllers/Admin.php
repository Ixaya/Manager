<?php
require_once APPPATH . 'modules/admin/libraries/Admin_Controller.php';

class Admin extends Admin_Controller {

	function __construct() {
		parent::__construct();
		$this->load->model('admin/example');
	}

	public function index() {
		$data['example_count'] = $this->example->count_all();

		$this->load_view('dashboard', $data);
	}
}
