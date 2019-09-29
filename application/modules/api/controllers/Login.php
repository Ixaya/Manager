<?php
//
//  Login.php
//  Ixaya
//
// Created by Humberto Olavarrieta on 2/3/17.
//  Copyright © 2017 Ixaya. All rights reserved.
//

if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH . 'modules/api/libraries/REST_Controller.php';

class Login extends REST_Controller {

	public function __construct() {
		parent::__construct('rest', true, 'none');

		$this->load->database();
		$this->load->library(array('IX_Ion_auth'));
	}

	/**
	 * call authentication with normal login
	 * check if user valid or not */
	public function index_post()
	{
		$username   = $this->post('username');
		$password   = $this->post('password');

		$result = $this->ix_ion_auth->login($username, $password, false, true);

		if ($result != false){
			$json = $this->___processJSONResponse($result);
			$this->response($json, REST_Controller::HTTP_OK);
		} else {
			$this->response(array('status' => -1, 'message' => "Username/password incorrect"), REST_Controller::HTTP_OK);
		}
	}
	
	public function register_post()
	{
		//payment code if succes then this
		$username  = $this->post('username');
		$password  = $this->post('password');

		$extras	= $this->post('extras');

		$user_id = $this->ix_ion_auth->register($username, $password, $username, $extras, [3]);
		if ($user_id != false)
		{
// 			$this->ix_ion_auth->activate($user_id);

			$result = $this->ix_ion_auth->login($username, $password, false, true);
			$json = $this->___processJSONResponse($result);

			$this->response($json, REST_Controller::HTTP_OK);
		}

		$this->response(array('status' => -1, 'message' => "Usuario ya registrado."), REST_Controller::HTTP_OK);
	}
	
	
	
	public function facebook_post()
	{
		$this->load->library('facebook');
		$accessToken = $this->post('facebook_token');

		$this->print_log(array('facebook_1'=>$accessToken));
		if (empty($accessToken)){
			$this->response(array('status' => -1, 'message' => "No token"), REST_Controller::HTTP_OK);
			return;
		}
		$oAuth2Client = $this->facebook->fb->getOAuth2Client();
		$tokenMetadata = $oAuth2Client->debugToken($accessToken);
		$tokenMetadata->validateAppId('919435211512477');
		$tokenMetadata->validateExpiration();

		$this->facebook->fb->setDefaultAccessToken($accessToken);
		$response = $this->facebook->fb->get('/me?locale=en_US&fields=email');
		$basicUserNode = $response->getGraphUser();

		$userFacebookID = $basicUserNode['id'];
		$this->print_log(array('facebook_2'=>$userFacebookID));
		//no email sometimes, quick fix;
		//		 if (isset($basicUserNode['email']))
		//			 $userEmail = $basicUserNode['email'];
		//		 else
		//			 $userEmail = $userFacebookID;

		$result = $this->ix_ion_auth->login_facebook($userFacebookID, $accessToken, false, true);
		if ($result != false){
			$json = $this->___processJSONResponse($result);

			$this->print_log(array('facebook_3_l'=>$result->id));
			$this->sync_user_image($result->id);

			$this->response($json, REST_Controller::HTTP_OK);

		} else {
			$this->facebook->fb->setDefaultAccessToken($accessToken);
			//$response = $this->facebook->fb->get('/me?locale=en_US&fields=first_name,last_name,name,email,picture');

			$response = $this->facebook->fb->get('me?locale=en_US&fields=first_name,middle_name,last_name,name,email,picture,likes{category,affiliation},gender,age_range,birthday,devices,friends{devices,gender}');


			//me?fields=likes{about,category,product_catalogs,category_list},gender
			//me?fields=likes{category,affiliation},gender,age_range,birthday,email,devices,friends{devices,gender}
			$userNode = $response->getGraphUser();
			$this->print_log(array('facebook_3_r'=>$userNode['first_name']));

			//$userImage = $this->facebook->fb->get('/me/picture?width=512');
			//$userImage = $userImage->getHeaders();

			/*
			 $friends=$this->facebook->fb->get('/me/friends?fields=name,picture');
			 $friends=$friends->getBody();
			 $friends=json_decode($friends);
			 */

			$additional_data["gender"] = $userNode['gender'];
			$additional_data["first_name"] = $userNode['first_name'];
			if (array_key_exists('middle_name', $userNode))
				$additional_data["last_name"]  = $userNode['middle_name']." " .$userNode['last_name'];
			else
				$additional_data["last_name"]  = $userNode['last_name'];
			$additional_data["image_name"] = "f:". $userFacebookID;
			$additional_data["image_url"]  = "https://graph.facebook.com/". $userFacebookID ."/picture?height=512";
			$additional_data["fb_token"]   = $accessToken;
			$additional_data["fb_login"]   = 1;

			$userEmail = '';
			if (isset($basicUserNode['email'])){
				$additional_data["email"] = $basicUserNode['email'];
				$userEmail = $basicUserNode['email'];
			}

			$userID = $this->ix_ion_auth->register_facebook($userFacebookID, $additional_data, NULL);

			$this->load->model('api/slack');
			$this->slack->new_signup($userID);

			$this->sync_user_image($userID);

			$this->print_log(array('facebook_4_r'=>$userID));



			if ($userID != false){
				$userData = array('email'=> $userEmail , 'id' => $userID, 'facebook_id' => $userFacebookID);
				$json = $this->___processJSONResponse($userData);

				$this->response($json, REST_Controller::HTTP_OK);

			}
			else
			{
				$this->response(array('status' => -1, 'message' => "Username already registered"), REST_Controller::HTTP_OK);
			}
		}
	}

	/**
	 * cleanup user
	 * @param type $objAcc
	 */
	private function ___processJSONResponse($objAcc, $apiKey = false) {

		//Clean up user info
		unset($objAcc->password);
		unset($objAcc->active);
		unset($objAcc->last_login);

		if ($apiKey == false){
			if (is_array($objAcc))
				$userID = $objAcc['id'];
			else
				$userID = $objAcc->id;
			$this->load->model('api/Rest_key_model','api_key');
			$apiKey = $this->api_key->get_user_key($userID);
		}


		$json = array(
			'status'		=> 1,
			'info'   => $objAcc,
			'api_key'  => $apiKey
		);

		return $json;
	}

	public function print_log($object)
	{
		log_message('debug', json_encode($object));
	}
}
