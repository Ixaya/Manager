<?php
//
//  Login.php
//  Ixaya
//
// Created by Humberto Olavarrieta on 2/3/17.
//  Copyright © 2017 Ixaya. All rights reserved.
//

if (! defined('BASEPATH')) exit('No direct script access allowed');

class Login extends REST_Controller
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

		$device_uuid = $this->post('device_uuid');

		$result = $this->ion_auth->login($username, $password, false, true);




		if ($result != false) {
			$json = $this->___processJSONResponse($result, null, $device_uuid);
			$this->response($json, REST_Controller::HTTP_OK);
		} else {
			$this->response(['status' => -1, 'message' => "Username/password incorrect"], REST_Controller::HTTP_OK);
		}
	}

	public function register_post()
	{
		$username  = $this->post('username');
		$password  = $this->post('password');

		$extras	= $this->post('extras');

		$groups = [GROUP_MEMBER_ID];

		$user_id = $this->ion_auth->register($username, $password, $username, $extras, $groups);
		if ($user_id != false) {
			//Remove activate and login, if you wish to handle the activation by mail
			// $this->response(['status' => 1, 'message' => "User succesfully registered."], REST_Controller::HTTP_OK);

			$this->ion_auth->activate($user_id);

			$result = $this->ion_auth->login($username, $password, false, true);
			$json = $this->___processJSONResponse($result);

			$this->response($json, REST_Controller::HTTP_OK);
		}

		$this->response(['status' => -1, 'message' => "User previously registered."], REST_Controller::HTTP_OK);
	}

	/**
	 * Cleans and formats the JSON response for the given account data.
	 *
	 * @param array|object $objAcc      Account data to process.
	 * @param string|false $apiKey      API key associated with the request, or false if not used.
	 * @param string|null  $device_uuid Optional device identifier.
	 *
	 * @return array The processed JSON response data.
	 */
	private function ___processJSONResponse($objAcc, $apiKey = null, $device_uuid = null)
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


		$json = [
			'status'		=> 1,
			'info'   => $objAcc,
			'api_key'  => $apiKey,
			'device_uuid' => $device_uuid
		];

		return $json;
	}

	public function print_log($object)
	{
		log_message('debug', json_encode($object));
	}
}
