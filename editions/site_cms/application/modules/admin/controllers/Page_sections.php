<?php

class Page_sections extends Admin_Controller {

	function __construct() {
		parent::__construct();

		
	}

	public function index() {
		
		$this->load->model('webpage');
		
		$webpages = $this->webpage->get_all('id, title');
		

		//estructura sencilla para la lista		
		$webpages_simple = [];
		foreach($webpages as $wp)
		{
			$webpages_simple[$wp['id']] = $wp['title'];
		}

		$data['webpages'] = $webpages_simple;

		$this->load->model('page_section');
		$this->page_section->set_override_column('webpage_id');
		
		$data['page_sections'] = $this->page_section->get_all();
		$data['page_sections_count'] = $this->page_section->count_all();
		$data['icon_sections'] 	= $this->page_section->count_all('kind = 1');
		$data['showcases']  	= $this->page_section->count_all('kind = 2');
		$data['testimonials']	= $this->page_section->count_all('kind = 3');
		$data['social_networks'] = $this->page_section->count_all('kind = 4');
		$data['about_sections'] 	= $this->page_section->count_all('kind = 5');

		$data['kinds'] = $this->page_section->kinds();
		
		
		$this->load_view("page_section/page_sections", $data);
	}

	public function create() {
		$this->edit();
	}

	public function edit($id = NULL)
	{
		//cargo el modelo
		$this->load->model('webpage');
		$this->load->model('page_section');
		$this->page_section->set_override_column('webpage_id');
		
		
		if ($this->input->post('webpage_id')) {
			$data['webpage_id'] = $this->input->post('webpage_id');
			$data['kind'] = $this->input->post('kind');
			$data['order'] = $this->input->post('order');
			$data['content'] = $this->input->post('content');

			if ($id){
				$this->page_section->update($data, $id);
			} else{
				$data['create_date'] = date('Y-m-d H:i:s');
				$id = $this->page_section->insert($data);
			}
			
			if($this->input->post('webpage_id') != $_SESSION['webpage_id'])
			{
				$_SESSION['webpage_id'] = $this->input->post('webpage_id');
			}
			redirect("/admin/page_sections/edit/$id", 'refresh');
		}

		if ($id)
		{
			$data['page_section'] = $this->page_section->get($id);
			$_SESSION['page_section_id'] = $id;
		}
		else
			$data['page_section'] = $this->page_section->empty_object();


		$this->load->helper(array('form','ui'));
		
		//me traigo los kinds desde el modelo page_section
		$data['kinds'] = $this->page_section->kinds();
		$data['webpages'] = $this->webpage->get_all('id, title');

		$this->load_view('page_section/page_section', $data);
	}

	public function delete($id) {
			$this->load->model('page_section');
		$this->page_section->delete($id);

		redirect('/admin/page_sections', 'refresh');
	}

}
