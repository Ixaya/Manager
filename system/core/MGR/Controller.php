<?php

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

class MGR_Controller extends CI_Controller
{
	protected ?string $_theme = null;
	protected ?string $_theme_kind = null;
	protected ?string $_container = null;
	protected ?string $_layout = null;
	protected string $_layout_path = '';

	protected int $_domain_id = 0;
	public ?int $domain_client_id = null;

	protected null|string|array $language_file = null;
	protected bool $language_enabled = false;
	protected bool $session_enabled = false;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');

		if ($this->session_enabled) {
			$this->load_session();
		}

		$this->load_language();
		$this->_layout_path = $this->resolve_layout($this->_layout);
	}

	// MGR_Site_Controller
	protected function resolve_theme(): void
	{
		$this->load->model('domain');
		$domain_name = $_SERVER['HTTP_HOST'];
		$domain = $this->domain->get_where("domain_name = '$domain_name'");

		if ($domain) {
			if (!empty($domain->redirect_url)) {
				redirect($domain->redirect_url);
			}
			$this->_domain_id       = (int)$domain->id;
			$this->domain_client_id = (int)$domain->client_id;

			if (!empty($domain->theme_id)) {
				$this->load->model('theme');
				$theme_row    = $this->theme->get($domain->theme_id);
				$this->_theme = $theme_row['shortname'];
			}
		}

		$this->resolve_layout($this->_layout);
	}
	protected function resolve_layout(?string $layout): string
	{
		$layout_path = '';
		if (!empty($this->_container)) {
			$layout_path .= "{$this->_container}/";
		}
		if (!empty($this->_theme)) {
			$layout_path .= "{$this->_theme}/";
		}
		if (empty($layout)) {
			$layout_path .= "layout";
		} else {
			$layout_path .= $layout;
		}

		return $layout_path;
	}

	public function load_language(): void
	{
		if ($this->language_enabled) {
			if ($this->language_file === null) {
				$this->load->helper('inflector');
				$this->language_file = strtolower(get_class($this));
			}

			if (isset($_GET['language'])) {
				$this->config->set_item('language', $_GET['language']);
			} elseif (isset($_SESSION['language'])) {
				$this->config->set_item('language', $_SESSION['language']);
			}

			if (is_array($this->language_file)) {
				foreach ($this->language_file as $file) {
					$this->lang->load($file);
				}
			} else {
				$this->lang->load($this->language_file);
			}

			$this->load->helper('language');
		}
	}

	public function load_session(): void
	{
		$this->load->library('session');

		if (!empty($this->session->flashdata('message')) && empty($this->session->flashdata('message-kind'))) {
			$this->session->set_flashdata('message-kind', 'info');
		}
	}

	public function load_clean_view(string $page, array $data = []): void
	{
		$layout_path = $this->resolve_layout('layout_clean');
		$this->load_view($page, $data, $layout_path);
	}
	public function load_view(string $page, array $data = [], ?string $layout_path = null): void
	{
		//modify default layout after constructing the controller
		if (empty($layout_path)) {
			$layout_path = $this->_layout_path;
		} elseif (strpos($layout_path, '/') === false) {
			$layout_path  = $this->resolve_layout($layout_path);
		}

		$data['page'] = $page;
		$data['module'] = $this->_theme;
		$this->load->view($layout_path, $data);
	}
	public function json_response(mixed $data): void
	{
		header('Content-Type: application/json');

		echo(json_encode($data));
		die();
	}

	public function upload_file(string $relative_path, ?string $desired_file_name = null, string $field_name = 'userfile', ?array $upload_config = null, bool $encrypt_name = true, ?string &$error = null): ?array
	{
		$this->load->library('upload_lib');
		return $this->upload_lib->upload_file($relative_path, $desired_file_name, $field_name, $upload_config, $encrypt_name, $error);
	}

	public function upload_image(string $relative_path, ?string $desired_file_name = null, bool $delete_original = true, string $field_name = 'userfile', ?array $resolution = null, bool $preserve_type = false, ?array $upload_config = null, ?string &$error = null): ?array
	{
		$this->load->library('upload_lib');
		return $this->upload_lib->upload_image($relative_path, $desired_file_name, $delete_original, $field_name, $resolution, $preserve_type, $upload_config, $error);
	}

	public function put_file(string $relative_path, string $file_name, array $data, ?string &$error = null): ?array
	{
		$this->load->library('upload_lib');
		return $this->upload_lib->put_file($relative_path, $file_name, $data, $error);
	}

	public function get_file_base64(?string $file_path, string &$file_name = '', string &$file_ext = '', string &$file_mime = ''): ?string
	{
		$this->load->library('upload_lib');
		return $this->upload_lib->get_file_base64($file_path, $file_name, $file_ext, $file_mime);
	}

	public function display_image(?string $file_path): void
	{
		$this->load->library('upload_lib');
		$this->upload_lib->display_image($file_path);
	}
}
