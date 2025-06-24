<?php if (!defined('BASEPATH'))  exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
	var $_theme;
	var $_container;
	var $_layout;

	var $_use_domain = false;
	var $_domain_id = 0;
	var $domain_client_id;

	var $_theme_kind = 'frontend';

	var $language_file = null;
	var $language_enabled = false;

	var $_css_files = [];
	var $_js_files = [];

	function __construct()
	{
		parent::__construct();
		$this->load->helper('url');

		if ($this->_use_domain && !is_cli() && empty($this->_theme)) {
			$this->load->model('ix_domain');

			$domain_name = $_SERVER['HTTP_HOST'];
			$domain = $this->ix_domain->get_where("domain_name = '$domain_name'");
			if ($domain) {
				if (!empty($domain->redirect_url))
					redirect($domain->redirect_url);

				$this->_domain_id = $domain->id;
				$this->domain_client_id = $domain->client_id;

				if (!empty($domain->theme_id)) {
					$this->load->model('ix_theme');

					$theme_row = $this->ix_theme->get($domain->theme_id);
					$this->_theme = $theme_row->shortname;
				}
			}
		}

		//construct defaults in case no overrides are setup
		if (empty($this->_theme)) {

			// load from config file
			$this->_theme = $this->config->item("{$this->_theme_kind}_theme");


			//$this->_theme = 'default';
		}

		if (empty($this->_container)) {
			$this->_container = 'frontend';
		}
		if (empty($this->_layout)) {
			$this->_layout = "{$this->_container}/{$this->_theme}/layout";
		} else {
			$this->_layout = "{$this->_container}/{$this->_theme}/{$this->_layout}";
		}

		$this->load->library('session');
		if (!empty($this->session->flashdata('message')) && empty($this->session->flashdata('message-kind'))) {
			$this->session->set_flashdata('message-kind', 'info');
		}

		if ($this->language_enabled) {
			if (!$this->language_file) {
				$this->load->helper('inflector');
				$this->language_file = strtolower(get_class($this));
			}

			if (isset($_SESSION['language'])) {
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

		//Build WebPage Title and Breadcrume
		$_SESSION['page_title'] = null;
	}

	public function load_clean_view($page, $data = [])
	{
		$layout = $this->_layout = "{$this->_container}/{$this->_theme}/layout_clean";
		$this->load_view($page, $data, $layout);
	}
	public function load_view($page, $data = [], $layout = null)
	{
		//modify default layout after constructing the controller
		if (empty($layout)) {
			$layout = $this->_layout;
		}

		$data['page'] = $page;
		$data['module'] = $this->_theme;
		$this->load->view($layout, $data);
	}
	public function json_response($data)
	{
		header('Content-Type: application/json');

		echo (json_encode($data));
		die();
	}

	public function upload_file($relative_path, $desired_file_name = NULL, $field_name = 'userfile', $upload_config = NULL, $encrypt_name = TRUE, &$error = NULL)
	{
		$this->load->library('ix_upload_lib');
		return $this->ix_upload_lib->upload_file($relative_path, $desired_file_name, $field_name, $upload_config, $encrypt_name, $error);
	}

	public function upload_image($relative_path, $desired_file_name = NULL, $delete_original = TRUE, $field_name = 'userfile', $resolution = [200, 200], $preserve_type = FALSE, $upload_config = NULL, &$error = NULL)
	{
		$this->load->library('ix_upload_lib');
		return $this->ix_upload_lib->upload_image($relative_path, $desired_file_name, $delete_original, $field_name, $resolution, $preserve_type, $upload_config, $error);
	}

	public function get_file_base64($file_path, &$file_name = '', &$file_ext = '', &$file_mime = '')
	{
		$this->load->library('ix_upload_lib');
		return $this->ix_upload_lib->get_file_base64($file_path, $file_name, $file_ext, $file_mime);
	}

	public function display_image($file_path)
	{
		$this->load->library('ix_upload_lib');
		$this->ix_upload_lib->display_image($file_path);
	}
}
