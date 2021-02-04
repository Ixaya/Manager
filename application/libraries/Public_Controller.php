<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Public_Controller extends MY_Controller {


	var $_social_networks = [];
	var $_footer_links = [];
	var $_is_logged_in;
	
	function __construct() {
				
		//you can change the theme from here, or from manager.php inside /application/config/
		//$this->_theme = 'default';
		//$this->_theme = 'soon';
		
		parent::__construct();
		
		$this->load->model('admin/page_item');
		$this->_social_networks = $this->page_item->get_all('','kind = 4');
		$this->_footer_links = $this->page_item->get_all('','kind = 6');
		$this->load->library('ion_auth');
		$this->_is_logged_in = $this->ion_auth->logged_in();
	}
}
