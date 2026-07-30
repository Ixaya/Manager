<?php

//  Created by Kevin Martinez on 29/08/24.
//  Copyright © 2024 Ixaya. All rights reserved.
//

class Sysusers extends APP_Rest_Controller
{
	public function __construct()
	{
		$this->group_methods['*']['level'] = LEVEL_ADMIN;
		// $this->group_methods['*']['group'] = GROUP_ADMIN;

		parent::__construct();
	}

	public function index_get()
	{
		$params = $this->build_list_params();

		$this->load->driver('cache');
		$cache_key = mgr_cache_key("sysusersidx", $params);
		$response = $this->cache->get($cache_key);
		if (!empty($response)) {
			$this->response($response, REST_Controller::HTTP_OK);
		}

		$this->load->model('user');
		$users = $this->user->get_list($params);

		if ($users['data'] === null) {
			$this->response(['status' => 0, 'message' => 'Failed to load users.'], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}

		$response = [
			'status' => 1,
			'response' => [
				'users' => $users['data'],
				'recordsTotal' => $users['total'],
				'recordsFiltered' => 0

			]
		];

		$this->cache->save($cache_key, $response);

		$this->response($response, REST_Controller::HTTP_OK);
	}

	public function create_post()
	{
		$this->load->model('admin/user');

		$data = $this->post();

		if (empty($data)) {
			$this->response([
				'status' => 0,
				'message' => 'No data provided, please try again.'
			], REST_Controller::HTTP_BAD_REQUEST);
		}

		$password = $this->post('password');
		$email    = $this->post('email');
		$group_id = [$this->post('role')];

		$additional_data = [
			'first_name' => $this->post('first_name'),
			'last_name'  => $this->post('last_name'),
			'username'   => $this->post('username'),
			'company'    => $this->post('company'),
			'phone'      => $this->post('phone'),
		];

		$this->load->library('ion_auth');
		$user = $this->ion_auth->register($email, $password, $email, $additional_data, $group_id);
		if (!$user) {
			$this->response([
				'status' => 0,
				'message' => 'Something went wrong while creating the user. Please try again.'
			], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}

		if ($this->post('status') == 1) {
			$this->ion_auth->activate($user);
		}

		if (!empty($_FILES['image']['name'])) {
			$resolution = [250, 250];
			$image_field = 'image';
			$relative_path = "media/user_profile/$user/";

			$image_data = $this->upload_image($relative_path, $image_field, false, $image_field, $resolution);
			unset($data);
			if ($image_data) {
				$data['image_name'] = $image_data['thumb_name'];
				$data['image_url'] = $image_data['thumb_url'];

				$this->user->update($data, $user);
			}
		}

		$this->response([
			'status' => 1,
			'message' => 'User created successfully.',
			'response' => ['id' => $user]
		], REST_Controller::HTTP_OK);
	}

	public function update_post()
	{
		$this->load->model('admin/user');

		$data = $this->post();

		if (empty($data)) {
			$this->response([
				'status' => 0,
				'message' => 'No data was provided, please try again.'
			], REST_Controller::HTTP_BAD_REQUEST);
		}

		$id = $this->post('id');
		$data = [
			'first_name' => $this->post('first_name'),
			'last_name'  => $this->post('last_name'),
			'email'      => $this->post('email'),
			'username'   => $this->post('username'),
			'company'    => $this->post('company'),
			'phone'      => $this->post('phone'),
		];

		$newPassword = $this->post('password');
		if (!empty($newPassword)) {
			//if you use: ion_auth->update there is no need to encrypt it, else it will double crypt it.
			$data['password'] = $newPassword;
		}

		$this->load->library('ion_auth');
		if ($this->post('status') == 1) {
			$this->ion_auth->activate($id);
		} else {
			$this->ion_auth->deactivate($id);
		}


		$this->ion_auth->remove_from_group('', $id);
		$this->ion_auth->add_to_group($this->post('role'), $id);

		if (!$this->ion_auth->update($id, $data)) {
			$this->response([
				'status' => 0,
				'message' => 'Something went wrong while updating the user. Please try again.'
			], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}

		if (!empty($_FILES['image']['name'])) {
			$resolution = [250, 250];
			$image_field = 'image';
			$relative_path = "media/user_profile/$id/";

			$image_data = $this->upload_image($relative_path, $image_field, false, $image_field, $resolution);
			unset($data);
			if ($image_data) {
				$data['image_name'] = $image_data['thumb_name'];
				$data['image_url'] = $image_data['thumb_url'];

				$this->user->update($data, $id);
			}
		}

		$this->response([
			'status' => 1,
			'message' => 'User updated successfully',
			'response' => ['id' => $id]
		], REST_Controller::HTTP_OK);
	}

	public function details_get()
	{
		$this->load->model(['user', 'user_key', 'login_attempt', 'ion_auth_model']);

		$id = $this->get('id');
		$response = [];

		if (empty($id)) {
			$this->response([
				'status' => 0,
				'message' => 'The user ID is required.'
			], REST_Controller::HTTP_BAD_REQUEST);
		}

		$api_key_obj = $this->user_key->get_where(['user_id' => $id]);
		$api_key = "User doesn't have an API Key";
		if (!empty($api_key_obj)) {
			$api_key = $api_key_obj['key'];
		}

		$data['user'] = $this->ion_auth_model->user($id)->row_array();
		if (empty($data['user'])) {
			$this->response([
				'status' => 0,
				'message' => 'The user ID not found.'
			], REST_Controller::HTTP_NOT_FOUND);
		}

		$data['api_key'] = $api_key;
		$data['user_groups'] = $this->ion_auth_model->get_users_groups($id)->result_array();
		$data['login_attempts'] = $this->login_attempt->get_by_user($id);

		$response['user'] = [
			'id'          => $data['user']['id'],
			'email'       => $data['user']['email'],
			'username'    => $data['user']['username'],
			'first_name'  => $data['user']['first_name'],
			'last_name'   => $data['user']['last_name'],
			'company'     => $data['user']['company'],
			'phone'       => $data['user']['phone'],
			'active'      => $data['user']['active'],
			'image'       => $this->get_file_base64($data['user']['image_url']),
			'user_groups' => array_map(
				static fn (array $g): array => ['id' => $g['id'], 'name' => $g['name']],
				$data['user_groups']
			),
			'api_key'     => $data['api_key'],
			'ip_address'  => $data['user']['ip_address'],
			'last_update' => $data['user']['last_update'],
			'login_attempts' => $data['login_attempts'],
		];

		$this->response([
			'status' => 1,
			'message' => 'User',
			'response' => $response
		], REST_Controller::HTTP_OK);
	}

	public function clear_login_attempts_post()
	{
		$username =  $this->post('username');
		if (!empty($username)) {
			$this->load->library('ion_auth');
			$response = $this->ion_auth->clear_login_attempts($username);
			if (!empty($response)) {
				$this->response(['status' => 1, 'message' => 'Cleared successfully'], REST_Controller::HTTP_OK);
			}

			$this->response(['status' => 0, 'message' => 'Username not found.'], REST_Controller::HTTP_NOT_FOUND);
		}

		$this->response(['status' => 0, 'message' => 'The username is required.'], REST_Controller::HTTP_BAD_REQUEST);
	}

	public function delete_post()
	{
		$this->load->model('admin/user');

		$id = $this->post('id');

		$this->load->library('ion_auth');
		$result = $this->ion_auth->delete_user($id);
		if ($result === true) {
			$this->response([
				'status' => 1,
				'message' => 'User deleted successfully'
			], REST_Controller::HTTP_OK);
		}

		$this->response([
			'status' => 0,
			'message' => 'Error deleting user'
		], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
	}

	public function roles_get()
	{
		$this->load->library('ion_auth');
		$roles = $this->ion_auth->groups()->result();
		if (empty($roles)) {
			$this->response([
				'status' => 0,
				'message' => 'Error getting roles'
			], REST_Controller::HTTP_NOT_FOUND);
		}

		$this->response([
			'status' => 1,
			'message' => 'Success',
			'response' => $roles
		], REST_Controller::HTTP_OK);
	}
}
