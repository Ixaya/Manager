<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ix_mailing {
	protected $ci;

	protected $config_name;
	protected $bbc_address;
	protected $bbc_enabled;
	protected $from_name;

	protected $view_module = 'mailing';
	protected $view_theme;
	protected $view_folder = 'frontend';

	function __construct() {
		$this->ci =& get_instance();

		// Is the config file in the environment folder?
		if ( ! file_exists($file_path = APPPATH.'config/'.ENVIRONMENT.'/mailing.php')
			&& ! file_exists($file_path = APPPATH.'config/mailing.php'))
		{
			show_error('The configuration file mailing.php does not exist.');
		}

		include($file_path);

		if (!$this->view_theme){
			if (!empty($this->ci->_theme))
				$this->view_theme = $this->ci->_theme;
			else
				$this->view_theme = 'default';
		}

		if (!$this->config_name)
			$this->config_name = $email_active_config;
		if (!$this->bbc_enabled)
			$this->bbc_enabled = $email_bbc_enabled;
		if (!$this->from_name)
			$this->from_name = $email_from_name;

		if (!empty($email_config[$this->config_name])){
			if (!empty($email_base_config))
				$this->email_config = array_merge($email_base_config, $email_config[$this->config_name]);
			else
				$this->email_config = $email_config[$this->config_name];
		}
	}

	public function set_theme($theme){
		$this->view_theme = $theme;
	}
	public function set_bbc_address($bbc_address){
		if ($this->bbc_enabled){
			$this->bbc_address = $bbc_address;
		}
	}

	public function send_email($email = null, $data = [], $subject = '', $view = '', $view_only = FALSE)
	{
		if (empty($this->email_config)){
			log_message('error', 'Mailing not configured');
			return;
		}

		$view_route = "{$this->view_module}/{$this->view_theme}/{$this->view_folder}/$view";
		if(intval($view_only))
		{
			return $this->ci->load->view($view_route,$data,TRUE);
		}
		else
		{
			try {
				$this->ci->load->library('email');
				$this->ci->email->initialize($this->email_config);
				$this->ci->email->from($this->email_config['smtp_user'], $this->from_name);

				$this->ci->email->to($email);

				if ($this->bbc_enabled && !empty($this->bbc_address)){
						$this->ci->email->bcc($this->bbc_address);
				}

				$this->ci->email->subject($subject);

				$message = $this->ci->load->view($view_route,$data,TRUE);
				$this->ci->email->message($message);

				$result = @$this->ci->email->send(FALSE);
				//Show debug warnings:
				// $result = $this->ci->email->send(FALSE);

				if (!$result)
					log_message('error', $this->ci->email->print_debugger(['headers']));

				return $result;
			} catch (Exception $e) {
				$message = $e->getFile()." " . $e->getLine() . ": " . $e->getMessage();
				log_message('error', $message);

				return false;
			}
		}
	}

	public function send_email_example($email, $view_only)
	{
		$subject = 'Email example';
		$template = "email_example";

		return $this->send_email($email, [], $subject, $template, NULL, $view_only);
	}
}
