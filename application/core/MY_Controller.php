<?php if (!defined('BASEPATH'))  exit('No direct script access allowed');

class MY_Controller extends CI_Controller {

    var $_container;
    var $_modules;

    function __construct() {
        parent::__construct();
        $this->load->helper('url');
        $this->load->config('ci_my_admin');
        
        // Set container variable
        $this->_container = $this->config->item('ci_my_admin_template_dir_public') . "layout.php";
        $this->_modules = $this->config->item('modules_locations');

		
        log_message('debug', 'CI My Admin : MY_Controller class loaded');
    }
    
/*
    function __construct() {
        parent::__construct();

        // Set container variable
        $this->_container = $this->config->item('ci_my_admin_template_dir_admin') . "layout.php";
        $this->_modules = $this->config->item('modules_locations');

        $this->load->library(array('ion_auth'));
        if (!$this->ion_auth->logged_in()) {
            redirect('/auth', 'refresh');
        }

        $this->is_admin = $this->ion_auth->is_admin();
        $user = $this->ion_auth->user()->row();
        $this->logged_in_name = $user->first_name;

        log_message('debug', 'Admin : Admin_Controller class loaded');
    }
*/
}