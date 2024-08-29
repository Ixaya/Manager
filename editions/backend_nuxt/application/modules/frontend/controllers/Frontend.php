<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Frontend extends App_Controller
{

	function __construct()
	{
		parent::__construct();

		$this->_view_folder = 'admin';
		$this->_container = 'admin';
	}

	public function index()
	{
		$this->load_app('index');
	}
}
