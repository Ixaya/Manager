<?php
//
//  Errors.php
//  Ixaya
//
// Created by Humberto Olavarrieta on 2/3/17.
//  Copyright © 2017 Ixaya. All rights reserved.
//

if (! defined('BASEPATH')) exit('No direct script access allowed');

class Errors extends REST_Controller
{
	public function __construct()
	{
		$this->methods['*']['auth_override'] = 'none';

		parent::__construct();
	}
	public function index_get()
	{
		$this->response(['status' => -1, 'message' => "Unknown method"], REST_Controller::HTTP_BAD_REQUEST);
	}
	public function not_found_get()
	{
		$this->response(['status' => -1, 'message' => "404 Not Found"], REST_Controller::HTTP_NOT_FOUND);
	}
}
