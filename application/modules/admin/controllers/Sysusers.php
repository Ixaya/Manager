<?php
require_once APPPATH . 'modules/admin/libraries/Admin_Controller.php';

class Sysusers extends Admin_Controller {

	function __construct() {
		parent::__construct();

		$group = 'admin';
		$this->load->model('admin/user');

		$this->load->helper('form');

		if (!$this->ion_auth->in_group($group))
		{
			$this->session->set_flashdata('message', 'You must be an administrator to view the users page.');
			redirect('admin/dashboard');
		}
		$this->load->helper(array('form', 'url'));
	}

	public function index() {
		$users = $this->user->get_all('id,first_name,last_name,email,ip_address,FROM_UNIXTIME(created_on) as created_on_date, last_activity_date');
		//print_r($users);

		$data['users'] = $users;
		$this->load_view('sysusers_list', $data);
	}

	public function index_ajax() {
		$users = $this->user->get_all('id,first_name,last_name,email,ip_address,FROM_UNIXTIME(created_on) as created_on_date,last_activity_date',null,null,10);
		$this->json_response($users);
	}

	public function create() {
		if ($this->input->post('username')) {
			$username = $this->input->post('username');
			$password = $this->input->post('password');
			$email = $this->input->post('email');
			$group_id = array( $this->input->post('group_id'));

			$additional_data = array(
				'first_name' => $this->input->post('first_name'),
				'last_name' => $this->input->post('last_name'),
				'username' 	=> $this->input->post('username'),
				'company' 	=> $this->input->post('company'),
			);

			$user = $this->ion_auth->register($email, $password, $email, $additional_data, $group_id);

			if(!$user)
			{
				$errors = $this->ion_auth->errors();
				echo $errors;
				die('done');
			}
			else
			{
				if ($this->input->post('active') == 1)
					$this->ion_auth->activate($user);

				redirect('/admin/sysusers', 'refresh');
			}
		}

		$data['groups'] = $this->ion_auth->groups()->result();

		$this->load_view('sysusers_create', $data);
	}

	public function edit($id) {
		if ($this->input->post('first_name')) {
			$data['username'] = $this->input->post('username');
			$data['first_name'] = $this->input->post('first_name');
			$data['last_name'] = $this->input->post('last_name');
			$data['email'] = $this->input->post('email');
			$data['company'] = $this->input->post('company');

			$newPassword = $this->input->post('password');
			if(!empty($newPassword)){
				//if you use: ion_auth->update there is no need to encrypt it, else it will double crypt it.
				$data['password'] = $newPassword;
			}

			if ($this->input->post('active') == 1)
				$this->ion_auth->activate($id);
			else
				$this->ion_auth->deactivate($id);

			$this->ion_auth->remove_from_group('', $id);
			$this->ion_auth->add_to_group($this->input->post('group_id'), $id);

			$this->ion_auth->update($id, $data);

			redirect('/admin/sysusers', 'refresh');
		}

		$this->load->helper('ui');

		$data['groups'] = $this->ion_auth->groups()->result();
		$data['user'] = $this->ion_auth->user($id)->row();
		$data['user_group'] = $this->ion_auth->get_users_groups($id)->row();

		$this->load_view('sysusers_edit', $data);
	}

	public function delete($id) {
		$this->ion_auth->delete_user($id);

		redirect('/admin/sysusers', 'refresh');
	}


	public function do_upload($id)
	{
			$config['upload_path']		  = '_base_route_/app/public/media/user';
			$config['allowed_types']		= 'gif|jpg|png';
			$config['max_size']			 = 2048; //2MB (PHP Max in this config)
//			 $config['max_width']			= 1024;
//			 $config['max_height']		   = 1024;
			$config['max_width']			= 0; // no size restriction
			$config['max_height']		   = 0; // no size restriction

			$config['encrypt_name']			= true;
			$config['remove_spaces']		= true;

			$this->load->library('upload', $config);

			if (!$this->upload->do_upload('userfile'))
			{
					$this->session->set_flashdata('message', $this->upload->display_errors());
					$this->edit($id);
			}
			else
			{		$upload_data = $this->upload->data();
					$data['image_url']   = base_url("/media/user/".$upload_data['file_name']);
					$data['image_name']  = $upload_data['file_name'];
					$this->user->update($data, $id);
					redirect('/admin/sysusers', 'refresh');
			}
	}
}
