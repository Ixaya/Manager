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

		$this->output->cache(10);

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



			//save profile picture image
			$relative_path = "../private/user/";
			$result = $this->upload_image($relative_path);
			if(!empty($result['thumb_image_name']))
			{
				$data['image_name'] = $result['thumb_image_name'];
				$data['image_url'] = base_url('private/profile/picture');

				$this->load->library('admin/amazons3');
				$aws_bucket = $this->config->item('aws_bucket');
				$aws_accesskey = $this->config->item('aws_accesskey');
				$aws_secretkey = $this->config->item('aws_secretkey');

				$this->amazons3->aws_bucket = $aws_bucket;
				$this->amazons3->aws_accesskey = $aws_accesskey;
				$this->amazons3->aws_secretkey = $aws_secretkey;

				$this->amazons3->upload_file($relative_path.$result['thumb_image_name']);
				$this->amazons3->upload_file($relative_path.$result['fullsize_image_name']);
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

		if(!file_exists($file_path))
		{
			$this->load->library('admin/amazons3');

			$this->amazons3->aws_bucket = $this->config->item('aws_bucket');
			$this->amazons3->aws_accesskey = $this->config->item('aws_accesskey');
			$this->amazons3->aws_secretkey = $this->config->item('aws_secretkey');
			$result = $this->amazons3->save_file($filename, $relative_path);
			log_message('debug',"Get File from S3 $filename");
		} else {
			log_message('debug',"Get File from Local FileSystem $filename");
		}




		$this->display_image($file_path);
	}
}
