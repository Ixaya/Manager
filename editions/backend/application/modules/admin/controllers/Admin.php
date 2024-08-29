<?php

class Admin extends Admin_Controller {
	function __construct() {
		
		$this->group_needed = GROUP_ADMIN;
		
		parent::__construct();
	}

	public function index() {
		$this->load_view('dashboard/dashboard');
		
	}
	
	public function dashboard_admin_json()
	{
		$this->load->model('admin/user');

		$data = [];
		
		$data['users_count'] = $this->user->count_all();
		$data['users'] = $this->user->get_all('id, email, first_name, last_name, image_name');

		$response = ['status' => 0, 'response' => $data];
		$this->json_response($response);
	}
}
