<?php

defined('BASEPATH') or exit('No direct script access allowed');

class MGR_Mailing_lib
{
	protected $config_name = null;
	protected $bbc_address = null;
	protected $bbc_enabled = null;
	protected $from_name = null;

	/** @var array<int, string> logical SendGrid template names this subclass sends */
	protected array $templates = [];

	protected $email_config = null;
	protected array $email_configs = [];
	protected array $email_base_config = [];

	protected $view_module = 'mailing';
	protected $view_theme = null;
	protected $view_folder = null;

	public function __construct()
	{
		$file_path = get_instance()->config->path('lib_mailing');
		// Is the config file in the environment folder?
		if (!$file_path) {
			show_error('The configuration file lib_mailing.php does not exist.');
		}

		include($file_path);

		if (!$this->view_theme) {
			if (!empty($CI->_theme)) {
				$this->view_theme = $CI->_theme;
			} else {
				$this->view_theme = 'default';
			}
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

		$this->email_configs = $email_config ?? [];
		$this->email_base_config = $email_base_config ?? [];

		if (!empty($this->email_configs[$this->config_name])) {
			$this->load_config($this->email_configs[$this->config_name]);
		}
	}

	public function set_config_key(string $key): void
	{
		if (!empty($this->email_configs[$key])) {
			$this->config_name = $key;
			$this->load_config($this->email_configs[$key]);
		}
	}

	protected function load_config(array $config): void
	{
		$this->email_config = empty($this->email_base_config)
			? $config
			: array_merge($this->email_base_config, $config);
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

	public function send_email($email = null, $data = [], $subject = '', $view = '', $view_only = false)
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
			return $CI->load->view($view_route, $data, true);
		}

		$message = $CI->load->view($view_route, $data, true);

		if (($this->email_config['protocol'] ?? null) === 'sendgrid') {
			return $this->send_via_sendgrid($email, $subject, $message);
		}

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
			$CI->email->message($message);

			$result = $CI->email->send(false);

			if (!$result) {
				log_message('error', $CI->email->print_debugger(['headers']));
			}

			return $result;
		} catch (Exception $e) {
			$message = $e->getFile() . " " . $e->getLine() . ": " . $e->getMessage();
			log_message('error', $message);

			return false;
		}
	}

	public function send_email_example($email, $view_only)
	{
		$subject = 'Email example';
		$view = "email_example";

		return $this->send_email($email, $data = [], $subject, $view, $view_only);
	}

	/**
	 * Sends a SendGrid template email — bypasses view rendering; the active profile's protocol must be `sendgrid`.
	 *
	 * @param array<string, mixed> $data dynamic template substitution data
	 */
	public function send_template(string $email, string $template_key, array $data = []): bool
	{
		if (($this->email_config['protocol'] ?? null) !== 'sendgrid') {
			log_message('error', "MGR_Mailing_lib: send_template requires the sendgrid protocol, profile '{$this->config_name}' is not configured for it");
			return false;
		}

		if (!in_array($template_key, $this->templates, true)) {
			log_message('error', "MGR_Mailing_lib: template '{$template_key}' is not declared in \$templates");
			return false;
		}

		$CI = &get_instance();
		$CI->load->library('sendgrid_lib');

		$template_id = $CI->sendgrid_lib->get_template_id($template_key);

		if ($template_id === null) {
			log_message('error', "MGR_Mailing_lib: no SendGrid template id configured for '{$template_key}'");
			return false;
		}

		$bcc = ($this->bbc_enabled && !empty($this->bbc_address)) ? ['email' => $this->bbc_address] : null;

		$result = $CI->sendgrid_lib->send_template(
			to: $CI->sendgrid_lib->build_recipients($email),
			template_id: $template_id,
			template_data: $data,
			from: $this->current_from_override(),
			bcc: $bcc,
		);

		if (!$result['success']) {
			log_message('error', 'MGR_Mailing_lib: sendgrid template send failed, status ' . ($result['status_code'] ?? 'n/a'));
		}

		return $result['success'];
	}

	protected function send_via_sendgrid(string $email, string $subject, string $html_body): bool
	{
		$CI = &get_instance();
		$CI->load->library('sendgrid_lib');

		$bcc = ($this->bbc_enabled && !empty($this->bbc_address)) ? ['email' => $this->bbc_address] : null;

		$result = $CI->sendgrid_lib->send(
			to: $CI->sendgrid_lib->build_recipients($email),
			subject: $subject,
			html_body: $html_body,
			from: $this->current_from_override(),
			bcc: $bcc,
		);

		if (!$result['success']) {
			log_message('error', 'MGR_Mailing_lib: sendgrid send failed, status ' . ($result['status_code'] ?? 'n/a'));
		}

		return $result['success'];
	}

	/**
	 * This profile's sender override, if it has one — shaping/fallback to the
	 * SendGrid account default is `MGR_Sendgrid_lib::build_from()`'s job, not ours.
	 *
	 * @return array{email: string, name?: string}|null
	 */
	protected function current_from_override(): ?array
	{
		if (empty($this->email_config['email_from'])) {
			return null;
		}

		return ['email' => $this->email_config['email_from'], 'name' => $this->from_name ?: null];
	}
}
