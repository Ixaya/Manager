<?php
//
//  Health_checks.php
//  Ixaya
//
// Created by Humberto Olavarrieta on 2/3/17.
// Copyright © 2017 Ixaya. All rights reserved.
//

if (! defined('BASEPATH')) exit('No direct script access allowed');

class Health_checks extends REST_Controller
{
	public function __construct()
	{
		$this->methods['*']['auth_override'] = 'none';

		parent::__construct();
	}
	public function index_get()
	{
		//Uncomment to send into frontend
		//redirect('https://www.example.com');

		$this->response(['status' => 1, 'message' => "API running"], REST_Controller::HTTP_OK);
	}
	public function not_found_get()
	{
		$this->response(['status' => -1, 'message' => "404 Not Found"], REST_Controller::HTTP_NOT_FOUND);
	}
}
