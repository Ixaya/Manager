<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Profile extends Private_Controller
{

	public function index()
	{
		$this->load_view('profile');
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
			if (!empty($newPassword) && $password_confirmation == $newPassword) {
				//if you use: ion_auth->update there is no need to encrypt it, else it will double crypt it.
				$data['password'] = $newPassword;
			}

			//save profile picture image
			$relative_path = "../private/user/";
			$result = $this->upload_image($relative_path);
			if (!empty($result['thumb_image_name'])) {
				$data['image_name'] = $result['thumb_image_name'];
				$data['image_url'] = base_url('private/profile/picture');

				$this->load->library('admin/amazon_aws');

				$this->amazon_aws->upload_file($relative_path . $result['thumb_image_name']);
				$this->amazon_aws->upload_file($relative_path . $result['fullsize_image_name']);
			}

			$this->ion_auth->update($current_user->id, $data);
			$current_user = $this->ion_auth->user()->row();
		}

		$data['user'] = $current_user;
		$this->load_view('profile_edit', $data);
	}
	public function picture()
	{
		$current_user = $this->ion_auth->user()->row();
		$relative_path = "../private/user/";
		$filename = $current_user->image_name;
		$file_path = FCPATH . $relative_path . $filename;

		if (!file_exists($file_path)) {
			$this->load->library('admin/amazon_aws');
			$result = $this->amazon_aws->save_file($filename, $relative_path);
			log_message('debug', "Get File from S3 $filename");
		} else {
			log_message('debug', "Get File from Local FileSystem $filename");
		}

		$this->display_image($file_path);
	}

	public function test()
	{
		$this->load->library('admin/amazon_aws');

		//$this->amazon_aws->upload_file('/home/manager/app/private/user/ef2d3c16686589d1dd8eff7b65a59cc8.jpg');
		//$result = $this->amazon_aws->list_files();
		$result = $this->amazon_aws->save_file('ef2d3c16686589d1dd8eff7b65a59cc8.jpg', '/home/manager/app/private/temp/');
		// print("<pre>");
		// print_r($result);
		// print("</pre>");
	}
}
