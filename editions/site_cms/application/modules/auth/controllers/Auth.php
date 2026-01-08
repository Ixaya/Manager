<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class Auth extends Site_Controller {
	private $use_levels = TRUE;

	function __construct() {
		parent::__construct();
		$this->load->database();
		$this->load->library(['ion_auth', 'form_validation','session']);
		$this->load->helper(['url', 'language']);

		$this->form_validation->set_error_delimiters($this->config->item('error_start_delimiter', 'ion_auth'), $this->config->item('error_end_delimiter', 'ion_auth'));

		log_message('debug', 'Admin : Auth class loaded');
	}

	public function index() {

		if ($this->ion_auth->logged_in()) {
			$this->_redirect_to_area();
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

				$this->_redirect_to_area();

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

	private function _redirect_to_area()
	{
		$redirect_url = $this->session->userdata('auth_redirect');
		if (!empty($redirect_url)) {
			$this->session->unset_userdata('auth_redirect');
			redirect($redirect_url, 'refresh');
		} else {
			if (empty($this->use_levels))
			{
				if ($this->ion_auth->is_admin()) {
					log_message('debug', 'Is Admin');
					redirect("/admin/dashboard", 'refresh');
				} else {
					redirect("/", 'refresh');
				}
			}
			else
			{
				$this->load->model('rest_user');

				$user_id = $this->ion_auth->user()->row()->id;
				$user_level = $this->rest_user->get_highest_level($user_id);
				if ($user_level == LEVEL_ADMIN) {
					redirect("/admin/dashboard", 'refresh');
				} else if ($user_level == LEVEL_MEMBER) {
					redirect("/private/profile", 'refresh');
				}

				redirect("/", 'refresh');
			}
		}
	}

	public function signup()
	{
		$email = $this->input->post('email');
		$first_name = $this->input->post('first_name');
		$last_name = $this->input->post('last_name');
		$password = $this->input->post('password');
		$password_confirmation = $this->input->post('password_confirmation');


		$message = null;
		try
		{
			if($password != $password_confirmation)
			{
				throw new Exception('The passwords do not match');
				//si no falla ninguna validación previa, proceder
			}

			$additional_data = array(
				'first_name' => $first_name,
				'last_name' => $last_name,
				'username' 	=> $email,
				'company' 	=> ''
			);

			$groups_id = [GROUP_MEMBER_ID];
			$user = $this->ion_auth->register($email, $password, $email, $additional_data, $groups_id);

			if(!$user)
			{
				$errors = $this->ion_auth->errors();
				throw new Exception($errors);
			}

			$this->ion_auth->activate($user);


			$this->ion_auth->login($email, $password);

			$message = 'Account created successfully, please login to proceed';
			$this->session->set_flashdata('message_kind', 'success');

			//redirect('/', 'refresh');
		}
		catch (Exception $ex)
		{
			$message = $ex->getMessage();
			log_message('debug', $message);


		} finally {
			// en caso de que queramos algún código de finalizar
			if($message)
				$this->session->set_flashdata('message', $message);

			redirect('/auth', 'refresh');
		}

	}

}

/* End of file auth.php */
/* Location: ./modules/auth/controllers/auth.php */
