<?php if (!defined('BASEPATH'))  exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
	var $_container;
	var $_layout;
	var $_theme;

	function __construct()
	{
		parent::__construct();
		$this->load->helper('url');

		//construct defaults in case no overrides are setup
		if(empty($this->_theme)){
			$this->_theme = '/default/';
		} else {
			$this->_theme = '/'.$this->_theme.'/';
		}
		if(empty($this->_container)){
			$this->_container = 'public';
		}
		if(empty($this->_layout)){
			$this->_layout = $this->_container.$this->_theme.'layout';
		}

		$this->load->library('session');
		if (!empty($this->session->flashdata('message')) && empty($this->session->flashdata('message-kind'))){
			$this->session->set_flashdata('message-kind', 'alert-info');
		}
	}

	public function load_clean_view($page, $data = [])
	{
		$layout = $this->_layout = $this->_container.$this->_theme.'/layout_clean';
		$this->load_view($page, $data, $layout);
	}
	public function load_view($page, $data = [], $layout = null)
	{
		//modify default layout after constructing the controller
		if(empty($container)){
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
