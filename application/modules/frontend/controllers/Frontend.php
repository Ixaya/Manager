<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Frontend extends MY_Controller {

	function __construct() {
		parent::__construct();
	}

	public function index()
	{
		$this->load_view('frontend');
	}

}
