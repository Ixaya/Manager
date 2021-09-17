<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Frontend extends Public_Controller
{

	public function index()
	{
		$this->webpage('frontend');
	}


	public function webpage($slug)
	{
		if ($this->config->item('cache_enable')) {
			//Cache from config
			$this->output->cache($this->config->item('cache_time'));
		}

		$sections = [];


		$data = null;
		if ($this->config->item('cache_enable')) {
			//cache enabled
			$this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));
			$data = $this->cache->get("frontend/webpage/$slug");
		}


		if (!$data) {
			log_message('DEBUG', "Saving frontend/webpage/$slug to the cache");

			$this->load->model('admin/webpage');
			$webpage = $this->webpage->get_all('', "slug = '$slug' and kind = 1");

			if (!empty($webpage)) {
				//cargar la sección referida en el webpage
				$webpage_id = $webpage[0]['id'];
				$this->load->model('admin/page_section');
				$sections =  $this->page_section->get_all('', "webpage_id = '$webpage_id'", '', '', 'order asc');
			}

			//cargar los page_items de esa seccion
			$this->load->model('admin/page_item');
			foreach ($sections as &$section) {
				$page_section_id = $section['id'];
				$section['page_items'] = $this->page_item->get_all('', "page_section_id = $page_section_id");
			}

			$data['sections'] = $sections;

			// Save into the cache for 5 minutes
			$this->cache->save("frontend/webpage/$slug", $data, 300);
		} else {
			log_message('DEBUG', "Using frontend/webpage/$slug cache");
		}

		$this->load_view('webpage', $data);
	}
}
