<?php if (!defined('BASEPATH'))  exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
	var $_theme;
	var $_container;
	var $_layout;

	var $_use_domain = false;
	var $_domain_id = 0;
	var $domain_client_id;

	function __construct()
	{
		parent::__construct();
		$this->load->helper('url');

		if($this->_use_domain && !is_cli() && empty($this->_theme))
		{
			$this->load->model('ix_domain');

			$domain_name = $_SERVER['HTTP_HOST'];
			$domain = $this->ix_domain->get_where("domain_name = '$domain_name'");
			if($domain) {
				if (!empty($domain->redirect_url))
					redirect($domain->redirect_url);

				$this->_domain_id = $domain->id;
				$this->domain_client_id = $domain->client_id;

				if (!empty($domain->theme_id)){
					$this->load->model('ix_theme');

					$theme_row = $this->ix_theme->get($domain->theme_id);
					$this->_theme = $theme_row->shortname;
				}
			}
		}

		//construct defaults in case no overrides are setup
		if(empty($this->_theme)){
			
			$this->_theme = $this->config->item('frontend_theme');
			//$this->_theme = 'default';
		}

		if(empty($this->_container)){
			$this->_container = 'frontend';
		}
		if(empty($this->_layout)){
			$this->_layout = "{$this->_container}/{$this->_theme}/layout";
		} else {
			$this->_layout = "{$this->_container}/{$this->_theme}/{$this->_layout}";
		}

		$this->load->library('session');
		if (!empty($this->session->flashdata('message')) && empty($this->session->flashdata('message-kind'))){
			$this->session->set_flashdata('message-kind', 'alert-info');
		}
	}

	public function load_clean_view($page, $data = [])
	{
		$layout = $this->_layout = "{$this->_container}/{$this->_theme}/layout_clean";
		$this->load_view($page, $data, $layout);
	}
	public function load_view($page, $data = [], $layout = null)
	{
		//modify default layout after constructing the controller
		if(empty($layout)){
			$layout = $this->_layout;
		}

		$data['page'] = $page;
		$data['module'] = $this->_theme;
		$this->load->view($layout, $data);
	}
	public function json_response($data)
	{
		header('Content-Type: application/json');

		echo(json_encode($data));
		die();
	}
}
