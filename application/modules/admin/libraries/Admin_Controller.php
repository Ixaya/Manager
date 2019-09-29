<?php if (!defined('BASEPATH'))  exit('No direct script access allowed');

class Admin_Controller extends MY_Controller {
	public $is_admin;
	//public $client_id; //UNCOMENT THIS SECTION TO REQUIRE client_id (make it multi-tenant)
	public $logged_in_name;
	public $language_file;

	function __construct() {
		parent::__construct();

		// Set container variable
		$this->_container = $this->config->item('ci_my_admin_template_dir_admin') . "layout.php";
		$this->_modules = $this->config->item('modules_locations');
		
		$this->load->library(array('ion_auth'));
		if (!$this->ion_auth->logged_in()) {
			redirect('/auth', 'refresh');
		}
		
/*
		//UNCOMENT THIS SECTION TO REQUIRE TRANSLATION FILE PER CONTROLLER IN ADMIN SECTION
		$this->load->helper('inflector');
		if (!$this->language_file) {
		 	$this->language_file = strtolower(get_class($this));
		}
		if(isset($_SESSION['language']))
		{
			$this->config->set_item('language', $_SESSION['language']);
		}
		$this->lang->load($this->language_file);
*/


		$this->is_admin = $this->ion_auth->is_admin();
		$user = $this->ion_auth->user()->row();
		$this->logged_in_name = $user->first_name;
		
		//UNCOMENT THIS SECTION TO REQUIRE client_id (make it multi-tenant)
		//$this->client_id = $user->client_id;
		//$_SESSION['client_id'] = $this->client_id;

/*
		print("<pre>");
		print_r($user);
		print("</pre>");
*/


		log_message('debug', 'Admin : Admin_Controller class loaded');
	}
}

/* End of Admin_controller.php */
/* Location: ./application/libraries/Admin_controller.php */