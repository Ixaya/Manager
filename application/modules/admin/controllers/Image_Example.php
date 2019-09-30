<?php
require_once APPPATH . 'modules/admin/libraries/Admin_Controller.php';

class Image_Example extends Admin_Controller {


	function __construct() {
		parent::__construct();

		$this->load->helper(['form', 'url', 'image']);
	}

	public function index()
	{
		$this->load_view('image_example', $data);
	}

	public function do_upload()
	{
			$config['upload_path']		  = '/home/ixayanet/app/public/media/';
			$config['allowed_types']		= 'gif|jpg|png';
			$config['max_size']			 = 2048; //2MB (PHP Max in this config)
/*
			$config['max_width']			= 1024;
			$config['max_height']		   = 1024;
*/
			$config['max_width']			= 0;
			$config['max_height']		   = 0;

			$config['encrypt_name']			= true;
			$config['remove_spaces']		= true;


			$this->load->library('upload', $config);

			if ( ! $this->upload->do_upload('userfile'))
			{
					$data['error'] = $this->upload->display_errors();
					$this->load_view('image_example', $data);
			}
			else
			{
					$data['upload_data'] = $this->upload->data();
					$this->load_view('image_example', $data);
			}
	}


}
