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
		$this->load->library(array('IX_Ion_auth', 'ion_auth'));
	}

	/**
	 * call authentication with normal login
	 * check if user valid or not */
	public function index_post()
	{

		$username   = $this->post('email');
		$password   = $this->post('password');

		$device_uuid = $this->post('device_uuid');

		$result = $this->ix_ion_auth->login($username, $password, false, true);


		if ($result != false) {
			$this->load->model('rest_user');
			$groups = $this->ix_ion_auth->get_users_groups($result->id)->result();
			$result->user_groups = array_column($groups, 'name');
			$result->user_groups_id = array_column($groups, 'id');
			$result->data = $this->rest_user->get($result->id);
			$json = $this->___processJSONResponse($result, null, $device_uuid);
			$this->response($json, REST_Controller::HTTP_OK);
		} else {
			$this->response(['status' => -1, 'message' => "Usuario o contraseña incorrectos."], REST_Controller::HTTP_OK);
		}
	}

	public function register_post()
	{
		//payment code if succes then this
		$username  = $this->post('email');
		$password  = $this->post('password');

		$extras	= array(
			'first_name' => $this->post('first_name'),
			'last_name'  => $this->post('last_name'),
			'company'    => $this->post('company'),
			'phone'      => $this->post('phone'),
			'terms_accepted' => $this->post('terms_accepted') == 'true' ? 1 : 0
		);

		$user_id = $this->ix_ion_auth->register($username, $password, $username, $extras, [GROUP_MEMBER_ID]);
		if ($user_id != false) {
			//Remove activate and login, if you wish to handle the activation by mail
			// $this->response(['status' => 1, 'message' => "User succesfully registered."], REST_Controller::HTTP_OK);

			$this->ix_ion_auth->activate($user_id);

			$result = $this->ix_ion_auth->login($username, $password, false, true);
			$groups = $this->ix_ion_auth->get_users_groups($result->id)->result();
			$result->user_groups = array_column($groups, 'name');
			$result->user_groups_id = array_column($groups, 'id');

			$this->load->model('rest_user');
			$result->data = $this->rest_user->get($result->id);
			$json = $this->___processJSONResponse($result);

			$this->response($json, REST_Controller::HTTP_OK);
		}

		$this->response(array('status' => -1, 'message' => "Usuario ya registrado."), REST_Controller::HTTP_BAD_REQUEST);
	}
	/**
	 * cleanup user
	 * @param type|array $objAcc
	 */
	private function ___processJSONResponse($objAcc, $apiKey = false, $device_uuid = null)
	{
		if (is_array($objAcc)){
			$objAcc = (object) $objAcc;
		}

		//Clean up user info
		unset($objAcc->password);
		unset($objAcc->active);
		unset($objAcc->last_login);
		$objAcc->full_name = $objAcc->data->first_name . " " . $objAcc->data->last_name;
		$objAcc->first_name = $objAcc->data->first_name;
		$objAcc->last_name = $objAcc->data->last_name;
		$objAcc->image = [
			'url'  => $objAcc->data->image_url == null ? null : 'https://' . ltrim(base_url($objAcc->data->image_url), '/'),
			'name' => $objAcc->data->image_name,
		];

		unset($objAcc->data);
		if ($apiKey == false) {
			$this->load->model('Rest_key_model', 'api_key');
			$apiKey = $this->api_key->get_user_key($objAcc->id, $device_uuid);
		}

		$json = array(
			'status'	  => 1,
			'info'        => $objAcc,
			'api_key'     => $apiKey,
			'device_uuid' => $device_uuid
		);

		return $json;
	}

	public function print_log($object)
	{
		log_message('debug', json_encode($object));
	}
}
