<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Language extends CI_Controller
{
	public function __construct() {
		parent::__construct();
		$this->load->library('session');
		$this->load->helper('url');
	}
	function change($language = "")
	{
		if (!empty($language)){
			$languages = $this->config->item('languages');
			if (empty($languages) || in_array($language, $languages))
				$valid = $language;
		}

		if (empty($language_v))
			$valid = $this->config->item('language');

		$this->session->set_userdata('language', $valid);

		if (!empty($_SERVER['HTTP_REFERER']))
			redirect($_SERVER['HTTP_REFERER']);

		redirect('/');
	}
}
