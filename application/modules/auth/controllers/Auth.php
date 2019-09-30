<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class Auth extends MY_Controller {

	function __construct() {
		parent::__construct();
		$this->load->database();
		$this->load->library(array('ion_auth', 'form_validation','session'));//Add facebook to use in login
		$this->load->helper(array('url', 'language'));

		$this->form_validation->set_error_delimiters($this->config->item('error_start_delimiter', 'ion_auth'), $this->config->item('error_end_delimiter', 'ion_auth'));

		log_message('debug', 'Admin : Auth class loaded');
	}

	public function index() {
		if ($this->ion_auth->logged_in()) {
			$this->_redirect_to_backend();
		} else {
			$this->load_view('login_form');
		}
	}

	public function login() {
		$this->form_validation->set_rules('email', 'Email', 'required');
		$this->form_validation->set_rules('password', 'Password', 'required');

		if ($this->form_validation->run() == true) {
			$remember = (bool) $this->input->post('remember');

			if ($this->ion_auth->login($this->input->post('email'), $this->input->post('password'), $remember)) {
				$this->session->set_flashdata('message', $this->ion_auth->messages());

				$this->_redirect_to_backend();
			} else {
				$this->session->set_flashdata('message', $this->ion_auth->errors());
				redirect('auth', 'refresh');
			}
		} else {
			$this->session->set_flashdata('message', validation_errors());
			redirect('auth', 'refresh');
		}
	}

	public function logout() {
		$this->load->library('session');

		$this->ion_auth->logout();

		redirect('auth', 'refresh');
	}

	private function _redirect_to_backend(){
		$redirect_url = $this->session->userdata('auth_redirect');
		if (!empty($redirect_url)){
			$this->session->unset_userdata('auth_redirect');
			redirect($redirect_url, 'refresh');
		} else {
			redirect("/admin/dashboard", 'refresh');
		}

	}
}

/* End of file auth.php */
/* Location: ./modules/auth/controllers/auth.php */
