<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Frontend extends MY_Controller {
	
	function __construct() {
        parent::__construct();
    }
    
	public function index()
	{
		$data['page'] = $this->config->item('ci_my_admin_template_dir_public') . "frontend";
        $this->load->view($this->_container, $data);
	}
	
}
