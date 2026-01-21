<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Site_Controller extends MY_Controller {


	var $_social_networks = [];
	var $_footer_links = [];
	var $_is_logged_in;
	
	function __construct() {
				
		//you can change the theme from here, or from manager.php inside /application/config/
		//$this->_theme = 'default';
		//$this->_theme = 'soon';
		$this->session_enabled = true;
		
		parent::__construct();

		$footer_data = false;
		if ($this->config->item('cache_enable')) {
			//cache enabled
			$this->load->driver('cache', ['adapter' => 'apc', 'backup' => 'file']);
			$footer_data = $this->cache->get("footer_data");
		}
		
		if($footer_data === false)
		{
			log_message('DEBUG', "Saving footer_data to the cache");
			$this->load->model('admin/page_item');

			$footer_data = [];
			$footer_data['social_networks'] = $this->page_item->get_all('',['kind' => 4]);
			$footer_data['footer_links'] = $this->page_item->get_all('',['kind' => 6]);
			if ($this->config->item('cache_enable')) {
				$this->cache->save("footer_data", $footer_data, 300);
			}
		} else {
			log_message('DEBUG', "Using footer_data cache");
		}
		
		$this->_social_networks = $footer_data['social_networks'];
		$this->_footer_links = $footer_data['footer_links'];
		
		$this->load->library('ion_auth');
		$this->_is_logged_in = $this->ion_auth->logged_in();
	}
}
