<?php

class Webpages extends Admin_Controller {

	function __construct() {
		
		$this->group_needed = 'members';
		
		parent::__construct();
	}

	public function index() {
		$this->load->model('webpage');
		
		$data['webpages'] = $this->webpage->get_all();
		$data['webpages_count'] = $this->webpage->count_all();
		$data['frontend_count'] 	= $this->webpage->count_all('kind = 1');
		$data['private_count']  	= $this->webpage->count_all('kind = 2');
		$data['admin_count'] 	= $this->webpage->count_all('kind = 3');

		$data['kinds'] = $this->webpage->kinds();
		$this->load_view("webpages/webpages", $data);
	}

	public function create() {
		$this->edit();
	}

	public function edit($id = NULL)
	{
		//cargo el modelo
		$this->load->model('webpage');
		
		if ($this->input->post('title')) {
			$data['title'] = $this->input->post('title');
			$data['slug'] = $this->input->post('slug');
			$data['kind'] = $this->input->post('kind');
			$data['content'] = $this->input->post('content');

			if ($id){
				$this->webpage->update($data, $id);
			} else{
				$data['create_date'] = date('Y-m-d H:i:s');
				$id = $this->webpage->insert($data);
			}

			redirect("/admin/webpages/edit/$id", 'refresh');
		}

		if ($id)
		{
			$data['webpage'] = $this->webpage->get($id);
			$_SESSION['webpage_id'] = $id;
		}
		else
			$data['webpage'] = $this->webpage->empty_object();
			
		


		$this->load->helper(array('form','ui'));
		
		//me traigo los kinds desde el modelo webpage
		$data['kinds'] = $this->webpage->kinds();
		
		$this->load_view('webpages/webpage', $data);
		
		
	}

	public function delete($id) {
			$this->load->model('webpage');
		$this->webpage->delete($id);

		redirect('/admin/webpages', 'refresh');
	}

}
