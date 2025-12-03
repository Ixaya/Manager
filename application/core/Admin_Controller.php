<?php if (!defined('BASEPATH'))  exit('No direct script access allowed');

class Admin_Controller extends MY_Controller {
	
	public $is_admin;
	//public $client_id; //
	public $logged_in_name;
	public $language_file;
	protected $group_needed = 'admin';
	
	

	function __construct() {
		$this->_container = 'admin';
		$this->_use_domain = false;
		$this->_theme_kind = 'admin';

		$this->session_enabled = true;
		
		//construct defaults in case no overrides are setup
	

		parent::__construct();
		
		
		
		$this->load->library('ion_auth');
		if (!$this->ion_auth->logged_in()) {
			$this->session->set_userdata('auth_redirect', uri_string());
			redirect('/auth', 'refresh');
		}
		
		if (!$this->ion_auth->in_group($this->group_needed))
		{
			$this->session->set_flashdata('message-kind', "danger");
			$this->session->set_flashdata('message', "You don't have the correct permissions to access that page.");
			redirect('/', 'refresh');
		}


/*
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
		if (empty($user)){
			$this->ion_auth->logout();
			$this->session->set_userdata('auth_redirect', uri_string());
			redirect('/auth', 'refresh');
		}

		$this->logged_in_name = $user->first_name;
		//$this->client_id = $user->client_id;
		//$_SESSION['client_id'] = $this->client_id;
	}
}

/* End of Admin_controller.php */
/* Location: ./application/modules/admin/libraries/Admin_controller.php */
