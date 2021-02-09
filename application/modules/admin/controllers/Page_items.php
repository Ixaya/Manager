<?php
require_once APPPATH . 'modules/admin/libraries/Admin_Controller.php';

class Page_items extends Admin_Controller {

	function __construct() {
		parent::__construct();

		
	}

	public function index() {
		$this->load->model('page_item');
		$this->page_item->set_override_column('page_section_id');
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
		$this->load->model('page_section');
		
		$this->page_item->set_override_column('page_section_id');
		$this->page_section->set_override_column('webpage_id');
		
		if ($this->input->post('title')) {
			$data['title'] = $this->input->post('title');
			$data['description'] = $this->input->post('description');
			$data['url'] = $this->input->post('url');
			$data['kind'] = $this->input->post('kind');
			$data['faicon'] = $this->input->post('faicon');
			$data['page_section_id'] = $this->input->post('page_section_id');
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
		$columns = 'page_section.id, page_section.webpage_id, webpage.title, order, page_section.content, slug, page_section.kind';
// 		$columns = '';
		$data['page_sections'] = $this->page_section->get_all_join($columns,'','','','','','webpage','webpage.id = page_section.webpage_id');
		
// 		var_dump($data['page_sections']);
		
/*
		  'id' => string '1' (length=1)
	      'webpage_id' => string '1' (length=1)
	      'order' => string '0' (length=1)
	      'kind' => string '1' (length=1)
	      'content' => null
	      'last_update' => string '2021-02-02 11:51:06' (length=19)
	      'create_date' => string '2021-02-02 11:51:06' (length=19)
	      'title' => string 'About' (length=5)
	      'slug' => string 'about' (length=5)
*/

		$this->load_view('page_item/page_item', $data);
	}

	public function delete($id) {
			$this->load->model('page_item');
		$this->page_item->delete($id);

		redirect('/admin/page_items', 'refresh');
	}

}
