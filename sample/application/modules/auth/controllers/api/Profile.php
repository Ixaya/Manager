<?php

defined('BASEPATH') or exit('No direct script access allowed');

//  Created by Kevin Martinez on 29/08/24.
//  Copyright © 2024 Ixaya. All rights reserved.
//

class Profile extends APP_Rest_Controller
{
	public function __construct()
	{
		$this->group_methods['*']['level'] = LEVEL_MEMBER;

		parent::__construct();
		$this->load->library('ion_auth');
	}

	public function index_get()
	{
		$this->load->model('user');

		$response = [];

		$response['profile'] = $this->get_profile();
		$this->response([
			'status' => 1,
			'message' => 'Profile retrieved successfully.',
			'response' => $response
		], REST_Controller::HTTP_OK);
	}

	public function edit_post()
	{
		$this->load->model('user');

		$response = [];

		$data = [
			'first_name' => $this->post('first_name'),
			'last_name' => $this->post('last_name'),
			'username' => $this->post('username'),
			'company' => $this->post('company'),
			'phone' => $this->post('phone')
		];

		$password = $this->post('password');
		if (!empty($password)) {
			// Si se usa ion_auth->update no es necesario encriptar la contraseña aquí, ya que se encriptará automáticamente.
			$data['password'] = $password;
		}

		if (!$this->ion_auth->update($this->user_id, $data)) {
			$this->response([
				'status' => 0,
				'message' => 'Something went wrong while updating the profile. Please try again.'
			], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}

		$response['profile'] = $this->get_profile();

		$this->response([
			'status' => 1,
			'response' => $response
		], REST_Controller::HTTP_OK);
	}

	/**
	 * Uploads the caller's own profile image.
	 */
	public function image_post(): void
	{
		$this->load->model('user');

		$image_data = $this->upload_image(
			relative_path: "media/user_profile/$this->user_id/",
			desired_file_name: 'image',
			delete_original: false,
			field_name: 'image',
			resolution: [250, 250],
			error: $error
		);
		if ($image_data === null) {
			$this->response([
				'status' => 0,
				'message' => strip_tags($error)
			], REST_Controller::HTTP_BAD_REQUEST);
		}

		$saved = $this->user->update([
			'image_name' => $image_data['thumb_name'],
			'image_url' => $image_data['thumb_url'],
		], $this->user_id);
		if (!$saved) {
			$this->response([
				'status' => 0,
				'message' => 'Something went wrong while saving the image. Please try again.'
			], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}

		$this->response([
			'status' => 1,
			'message' => 'Image uploaded successfully.',
			'response' => ['image_url' => image_url($image_data['thumb_url'], 'https:')]
		], REST_Controller::HTTP_OK);
	}

	private function get_profile()
	{
		$user = $this->user->get($this->user_id);
		$user_groups = $this->ion_auth->get_users_groups($this->user_id)->row();

		if (!empty($user)) {
			return [
				'id' => $user['id'],
				'email' => $user['email'],
				'first_name' => $user['first_name'],
				'last_name' => $user['last_name'],
				'username' => $user['username'],
				'company' => $user['company'],
				'phone' => $user['phone'],
				'status' => $user['active'],
				'user_group' => $user_groups,
				'ip_address' => $user['ip_address'],
				'image_url' => $user['image_url'] ? image_url($user['image_url'], 'https:') : null,
				'last_update' => $user['last_update'],
			];
		}

		return null;
	}
}
