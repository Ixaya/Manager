<?php
require_once APPPATH . 'modules/admin/libraries/Admin_Controller.php';

class Page_items extends Admin_Controller {

	function __construct() {
		parent::__construct();

		
	}

	public function index() {
		$this->load->model('page_item');
		$data['page_items'] = $this->page_item->get_all();
		$data['page_items_count'] = $this->page_item->count_all();
		$data['icon_items'] 	= $this->page_item->count_all('kind = 1');
		$data['showcases']  	= $this->page_item->count_all('kind = 2');
		$data['testimonials']	= $this->page_item->count_all('kind = 3');
		$data['social_networks'] = $this->page_item->count_all('kind = 4');
		$data['about_items'] 	= $this->page_item->count_all('kind = 5');

		$data['kinds'] = $this->page_item->kinds();
		$this->load_view("page_item/page_items", $data);
	}

	public function create() {
		$this->edit();
	}

	public function edit($id = NULL)
	{
		//cargo el modelo
		$this->load->model('page_item');
		
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
		
		//me traigo los kinds desde el modelo page_item
		$data['kinds'] = $this->page_item->kinds();
		

		$this->load_view('page_item/page_item', $data);
	}

	public function delete($id) {
			$this->load->model('page_item');
		$this->page_item->delete($id);

		redirect('/admin/page_items', 'refresh');
	}

}
