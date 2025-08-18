<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ix_mailing
{
	protected $config_name = null;
	protected $bbc_address = null;
	protected $bbc_enabled = null;
	protected $from_name = null;

	protected $email_config = null;

	protected $view_module = 'mailing';
	protected $view_theme = null;
	protected $view_folder = null;

	function __construct()
	{
		// Is the config file in the environment folder?
		if (
			! file_exists($file_path = APPPATH . 'config/' . ENVIRONMENT . '/lib_mailing.php')
			&& ! file_exists($file_path = APPPATH . 'config/lib_mailing.php')
		) {
			show_error('The configuration file lib_mailing.php does not exist.');
		}

		include($file_path);

		if (!$this->view_theme) {
			if (!empty($CI->_theme))
				$this->view_theme = $CI->_theme;
			else
				$this->view_theme = 'default';
		}

		if (!$this->config_name && isset($email_active_config)) {
			$this->config_name = $email_active_config;
		}
		if (!$this->bbc_enabled && isset($email_bbc_enabled)) {
			$this->bbc_enabled = $email_bbc_enabled;
		}
		if (!$this->from_name && isset($email_from_name)) {
			$this->from_name = $email_from_name;
		}

		if (!empty($email_config[$this->config_name])) {
			if (!empty($email_base_config))
				$this->email_config = array_merge($email_base_config, $email_config[$this->config_name]);
			else
				$this->email_config = $email_config[$this->config_name];
		}
	}

	public function set_theme($theme)
	{
		$this->view_theme = $theme;
	}
	public function set_bbc_address($bbc_address)
	{
		if ($this->bbc_enabled) {
			$this->bbc_address = $bbc_address;
		}
	}

	public function send_email($email = null, $data = [], $subject = '', $view = '', $view_only = FALSE)
	{
		if (empty($this->email_config)) {
			log_message('error', 'Mailing not configured');
			return;
		}

		$CI = &get_instance();

		//Build dynamic route, allowing for null components
		$path_parts = array_filter([
			$this->view_module,
			$this->view_theme,
			$this->view_folder
		]);
		$view_route = implode('/', $path_parts) . "/$view";

		if (intval($view_only)) {
			return $CI->load->view($view_route, $data, TRUE);
		} else {
			try {
				$CI->load->library('email');
				$CI->email->initialize($this->email_config);

				$from_email = $this->email_config['email_from'] ?? $this->email_config['smtp_user'] ?? '';
				$CI->email->from($from_email, $this->from_name);

				$CI->email->to($email);

				if ($this->bbc_enabled && !empty($this->bbc_address)) {
					$CI->email->bcc($this->bbc_address);
				}

				$CI->email->subject($subject);

				$message = $CI->load->view($view_route, $data, TRUE);
				$CI->email->message($message);

				$result = $CI->email->send(FALSE);

				if (!$result){
					log_message('error', $CI->email->print_debugger(['headers']));
				}

				return $result;
			} catch (Exception $e) {
				$message = $e->getFile() . " " . $e->getLine() . ": " . $e->getMessage();
				log_message('error', $message);

				return false;
			}
		}
	}

	public function send_email_example($email, $view_only)
	{
		$subject = 'Email example';
		$view = "email_example";

		return $this->send_email($email, $data = [], $subject, $view, $view_only);
	}
}
