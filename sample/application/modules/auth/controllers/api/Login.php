<?php

defined('BASEPATH') or exit('No direct script access allowed');

//
//  Login.php
//  Ixaya
//
// Created by Humberto Olavarrieta on 2/3/17.
//  Copyright © 2017 Ixaya. All rights reserved.
//

class Login extends APP_Rest_Controller
{
	public function __construct()
	{
		$this->methods['*']['auth_override'] = 'none';

		parent::__construct();

		$this->load->database();
		$this->load->library('ion_auth');
	}

	/**
	 * call authentication with normal login
	 * check if user valid or not */
	public function index_post()
	{

		$username   = $this->post('username');
		$password   = $this->post('password');

		if (empty($username) || empty($password)) {
			$this->response(['status' => 0, 'message' => "Username/password incorrect"], REST_Controller::HTTP_UNAUTHORIZED);
		}

		$device_uuid = $this->post('device_uuid');

		$this->ion_auth->disable_session();
		$result = $this->ion_auth->login($username, $password);




		if ($result != false) {
			$response = $this->_processResponse(objAcc: $result, device_uuid: $device_uuid);
			$this->response($response, REST_Controller::HTTP_OK);
		}

		$this->response(['status' => 0, 'message' => "Username/password incorrect"], REST_Controller::HTTP_UNAUTHORIZED);
	}

	public function register_post()
	{
		// Self-registration is deliberate for this sample's member portal. Before
		// real production use, add 2FA/email verification and stricter rate-limiting
		// beyond the global IP limiter.
		$username  = $this->post('username');
		$password  = $this->post('password');

		$extras	= $this->post('extras') ?? [];

		$groups = [GROUP_MEMBER_ID];

		$user_id = $this->ion_auth->register($username, $password, $username, $extras, $groups);
		if ($user_id != false) {
			//Remove activate and login, if you wish to handle the activation by mail
			// $this->response(['status' => 1, 'message' => "User succesfully registered."], REST_Controller::HTTP_OK);

			$this->ion_auth->activate($user_id);

			$this->ion_auth->disable_session();
			$result = $this->ion_auth->login($username, $password);
			$response = $this->_processResponse(objAcc: $result);

			$this->response($response, REST_Controller::HTTP_OK);
		}

		$this->response(['status' => 0, 'message' => "Unable to register."], REST_Controller::HTTP_BAD_REQUEST);
	}

	public function password_recovery_post()
	{
		$username  = $this->post('username');

		$data = $this->ion_auth->forgotten_password($username);

		//Implement send email using
		//$data['forgottenPasswordCode'];
		//$data['email'];

		$this->response(['status' => 1, 'message' => "Email succesfully sent."], REST_Controller::HTTP_OK);
	}

	/**
	 * Cleans and formats the response for the given account data.
	 *
	 * @param array|object $objAcc      Account data to process.
	 * @param string|false $apiKey      API key associated with the request, or false if not used.
	 * @param string|null  $device_uuid Optional device identifier.
	 *
	 * @return array The processed response data.
	 */
	private function _processResponse($objAcc, $apiKey = null, $device_uuid = null)
	{
		if (is_array($objAcc)) {
			$objAcc = (object) $objAcc;
		}

		//Clean up user info
		unset($objAcc->password);
		unset($objAcc->active);
		unset($objAcc->last_login);

		if ($apiKey == null) {
			$this->load->model('Rest_key_model', 'api_key');
			$apiKey = $this->api_key->get_user_key($objAcc->id, $device_uuid);
		}


		return [
			'status' => 1,
			'response' => [
				'profile' => $objAcc,
				'api_key' => $apiKey,
				'device_uuid' => $device_uuid
			]
		];
	}
}
