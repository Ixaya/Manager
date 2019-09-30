<?php
require_once APPPATH . 'modules/admin/libraries/Admin_Controller.php';

class Examples extends Admin_Controller {

	function __construct() {
		parent::__construct();

		$this->load->model(['admin/example']);
	}

	public function index() {
		$data['examples'] = $this->example->get_all();

		$this->load_view("example/examples", $data);
	}

	public function create() {
		$this->edit();
	}

	public function edit($id = NULL)
	{
		if ($this->input->post('title')) {
			$data['title'] = $this->input->post('title');
			$data['example'] = $this->input->post('example');

			if ($id){
				$this->example->update($data, $id);
			} else{
				$data['create_date'] = date('Y-m-d H:i:s');
				$id = $this->example->insert($data);
			}

			redirect("/admin/examples/edit/$id", 'refresh');
		}

		if ($id)
			$data['example'] = $this->example->get($id);
		else
			$data['example'] = $this->example->empty_object();


		$this->load->helper(array('form','ui'));
		$this->load_view('example/example', $data);
	}

	public function delete($id) {
		$this->example->delete($id);

		redirect('/admin/examples', 'refresh');
	}

}
