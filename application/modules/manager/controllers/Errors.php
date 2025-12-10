<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

class Errors extends CI_Controller
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