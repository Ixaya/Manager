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

	public function index()
	{
		
		$data['icon_items'] 	= $this->page_item->get_all('','kind = 1');
		$data['showcases']  	= $this->page_item->get_all('','kind = 2');
		$data['testimonials']	= $this->page_item->get_all('','kind = 3');
// 		$data['social_networks'] = $this->page_item->get_all('','kind = 4');
// 		$data['about_items'] 	= $this->page_item->get_all('','kind = 5');
		$this->load_view('frontend', $data);
	}
	
	public function contact()
	{
		$data = [];
		$this->load_view('contact', $data);
	}

}
