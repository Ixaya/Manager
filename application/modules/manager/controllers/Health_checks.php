<?php
//
//  Health_checks.php
//  Ixaya
//
// Created by Humberto Olavarrieta on 2/3/17.
// Copyright © 2017 Ixaya. All rights reserved.
//

if (! defined('BASEPATH')) exit('No direct script access allowed');

class Health_checks extends CI_Controller
{
	public function index()
	{
		echo "running\r\n";
	}
	public function not_found()
	{
		echo "not_found\r\n";
	}
}