<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Profile extends Private_Controller {
	
	public function index()
	{
		$this->load_view('profile/profile');
	}
	public function webpage($slug)
	{
		$sections = [];
		
		//cargar el webpage referidop
		$this->load->model('admin/webpage');
		$webpage = $this->webpage->get_all('',"slug = '$slug' and kind = 2");
		
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
		$_SESSION['page_title'] = $slug;
		$this->load_view('webpage',$data);
	}
	public function edit()
	{
		
// 		http://manager.vps102.ixaya.net/user/dcde3ece8f00cb9913ad606717181626.jpeg
// 		http://manager.vps102.ixaya.net/user/dcde3ece8f00cb9913ad606717181626.jpg
		
		
		$data = [];
		$current_user = $this->ion_auth->user()->row();
		$_SESSION['page_title'] = 'Edit Profile';

		
		if ($this->input->post('first_name')) {
			$data['username'] = $this->input->post('username');
			$data['first_name'] = $this->input->post('first_name');
			$data['last_name'] = $this->input->post('last_name');
			$data['email'] = $this->input->post('email');
// 			$data['company'] = $this->input->post('company');

			$newPassword = $this->input->post('password');
			$password_confirmation = $this->input->post('password_confirmation');
			if(!empty($newPassword) && $password_confirmation == $newPassword){
				//if you use: ion_auth->update there is no need to encrypt it, else it will double crypt it.
				$data['password'] = $newPassword;
			}
			
			$relative_path = "../private/user/";
			$result = $this->upload_image($relative_path);
			if(!empty($result['thumb_image_name']))
			{
				$data['image_name'] = $result['thumb_image_name'];
				$data['image_url'] = base_url('private/profile/picture');
			}
			
			$this->ion_auth->update($current_user->id, $data);
			$current_user = $this->ion_auth->user()->row();
		}
		
		$data['user'] = $current_user;
		$this->load_view('profile_edit',$data);
	}
	public function picture()
	{
		$current_user = $this->ion_auth->user()->row();
		$relative_path = "../private/user/";
		$filename = $current_user->image_name;
		$file_path = FCPATH.$relative_path.$filename;

		$this->display_image($file_path);
	}
}
