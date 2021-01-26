<?php
require_once APPPATH . 'modules/admin/libraries/Admin_Controller.php';

class Page_items extends Admin_Controller {

	function __construct() {
		parent::__construct();

		$this->load->model(['admin/page_item']);
	}

	public function index() {
		$data['page_items'] = $this->page_item->get_all();

		$this->load_view("page_item/page_items", $data);
	}

	public function create() {
		$this->edit();
	}

	public function edit($id = NULL)
	{
		if ($this->input->post('title')) {
			$data['title'] = $this->input->post('title');
			$data['description'] = $this->input->post('description');
			$data['url'] = $this->input->post('url');
			$data['kind'] = $this->input->post('kind');
			$data['faicon'] = $this->input->post('faicon');
			$data['image_name'] = $this->input->post('image_name');

			if ($id){
				$this->page_item->update($data, $id);
			} else{
				$data['create_date'] = date('Y-m-d H:i:s');
				$id = $this->page_item->insert($data);
			}

			redirect("/admin/page_items/edit/$id", 'refresh');
		}

		if ($id)
			$data['page_item'] = $this->page_item->get($id);
		else
			$data['page_item'] = $this->page_item->empty_object();


		$this->load->helper(array('form','ui'));
		$this->load_view('page_item/page_item', $data);
	}

	public function delete($id) {
		$this->page_item->delete($id);

		redirect('/admin/page_items', 'refresh');
	}

}
