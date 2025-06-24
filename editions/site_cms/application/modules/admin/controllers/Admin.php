<?php

class Admin extends Admin_Controller
{



	function __construct()
	{

		$this->group_needed = 'members';

		parent::__construct();
	}

	public function index()
	{
		$this->load_view('dashboard/dashboard');
	}

	public function dashboard_admin_json()
	{
		$this->load->model('admin/page_item');
		$this->load->model('admin/user');
		$this->load->model('admin/webpage');
		$this->load->model('admin/page_section');
		$data = [];
		$data['page_items_count'] = $this->page_item->count_all();

		$webpages = $this->webpage->get_all();


		$section_kinds = $this->page_section->kinds();

		foreach ($webpages as &$webpage) {


			$sections = $this->page_section->get_all('', ['webpage_id' => $webpage['id']]);

			foreach ($sections as &$section) {

				$section['title'] = $section_kinds[$section['kind']];
			}

			$webpage['sections'] = $sections;
		}

		$data['webpages'] = $webpages;
		$data['webpage_count'] = $this->webpage->count_all();
		$data['users_count'] = $this->user->count_all();
		$data['users'] = $this->user->get_all('id, email, first_name, last_name, image_name');
		$response = ['status' => 0, 'response' => $data];
		$this->json_response($response);
	}
}
