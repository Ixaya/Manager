<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Login_attempts extends APP_Rest_Controller
{
	public function __construct()
	{
		$this->group_methods['*']['level'] = LEVEL_ADMIN;

		parent::__construct();
	}

	public function index_get()
	{
		$params = $this->build_list_params(default_order_by: 'user.id');

		$this->load->model('login_attempt');
		$login_attempts = $this->login_attempt->get_list($params);

		if ($login_attempts === null) {
			$this->response(['status' => 0, 'message' => 'Failed to load login attempts.'], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}

		$response = [
			'status' => 1,
			'response' => [
				'login_attempts' => $login_attempts['data'],
				'recordsTotal' => $login_attempts['total'],
				'recordsFiltered' => 0
			]
		];

		$this->response($response, REST_Controller::HTTP_OK);
	}

	public function details_get()
	{
		$id = $this->get('id');

		if (empty($id)) {
			$this->response(['status' => 0, 'message' => 'The user ID is required.'], REST_Controller::HTTP_BAD_REQUEST);
		}

		$this->load->model('login_attempt');
		$login_attempts = $this->login_attempt->get_by_user($id);

		if ($login_attempts === null) {
			$this->response(['status' => 0, 'message' => 'Failed to load login attempts.'], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}

		$this->response(['status' => 1, 'response' => ['login_attempts' => $login_attempts]], REST_Controller::HTTP_OK);
	}

	public function clear_login_post()
	{
		$login = $this->post('login');

		if (empty($login)) {
			$this->response(['status' => 0, 'message' => 'The login is required.'], REST_Controller::HTTP_BAD_REQUEST);
		}

		$this->load->model('login_attempt');
		if (!$this->login_attempt->delete_by_login($login)) {
			$this->response(['status' => 0, 'message' => 'Error clearing login attempts.'], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}

		$this->response(['status' => 1, 'message' => 'Login attempts cleared successfully.'], REST_Controller::HTTP_OK);
	}
}
