<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Image_Example extends Admin_Controller {


    function __construct() {
        parent::__construct();
        
        $this->load->helper(array('form', 'url', 'image'));
    }

	
	public function index()
	{	    
		$data['page'] = $this->config->item('ci_my_admin_template_dir_admin') . "image_example";
        $this->load->view($this->_container, $data);
	}
	
	public function do_upload()
    {
            $config['upload_path']          = '/home/ixayanet/app/public/media/';
            $config['allowed_types']        = 'gif|jpg|png';
            $config['max_size']             = 2048; //2MB (PHP Max in this config)
/*
            $config['max_width']            = 1024;
            $config['max_height']           = 1024;
*/
            $config['max_width']            = 0;
            $config['max_height']           = 0;

			$config['encrypt_name']			= true; 
			$config['remove_spaces']		= true; 
			

            $this->load->library('upload', $config);

            if ( ! $this->upload->do_upload('userfile'))
            {
		            $data['error'] = $this->upload->display_errors();
                    $data['page'] = $this->config->item('ci_my_admin_template_dir_admin') . "image_example";
					$this->load->view($this->_container, $data);
            }
            else
            {
					$data['upload_data'] = $this->upload->data();
                    $data['page'] = $this->config->item('ci_my_admin_template_dir_admin') . "image_example";
					$this->load->view($this->_container, $data);
            }
    }
    
    
}