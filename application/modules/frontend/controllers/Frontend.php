<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Frontend extends MY_Controller {


	var $_social_networks = [];
	var $_footer_links = [];
	
	function __construct() {
				
		//you can change the theme from here, or from manager.php inside /application/config/
		//$this->_theme = 'default';
		//$this->_theme = 'soon';
		
		parent::__construct();
		
		$this->load->model('admin/page_item');
		$this->_social_networks = $this->page_item->get_all('','kind = 4');
		$this->_footer_links = $this->page_item->get_all('','kind = 6');

	}

/*
	public function index()
	{
		
		$data['icon_items'] 	= $this->page_item->get_all('','kind = 1');
		$data['showcases']  	= $this->page_item->get_all('','kind = 2');
		$data['testimonials']	= $this->page_item->get_all('','kind = 3');
// 		$data['social_networks'] = $this->page_item->get_all('','kind = 4');
// 		$data['about_items'] 	= $this->page_item->get_all('','kind = 5');
		$this->load_view('frontend', $data);
	}
*/

	public function index()
	{
		$this->webpage('frontend');
	}
	
	public function webpage($slug)
	{
		$sections = [];
		
		//cargar el webpage referidop
		$this->load->model('admin/webpage');
		$webpage = $this->webpage->get_all('',"slug = '$slug'");
		
		if(!empty($webpage))
		{
			//cargar la sección referida en el webpage
			$webpage_id = $webpage[0]['id'];
			$this->load->model('admin/page_section');
			$sections =  $this->page_section->get_all('',"webpage_id = '$webpage_id'");
		}
		//cargar los page_items de esa seccion
		$this->load->model('admin/page_item');		
		foreach($sections as &$section)
		{
			$page_section_id = $section['id'];
			$section['page_items'] = $this->page_item->get_all('',"page_section_id = $page_section_id");
// 			$section['page_items'] = [];
		}
		$data ['sections'] = $sections;
		$this->load_view('webpage',$data);
	}

}
