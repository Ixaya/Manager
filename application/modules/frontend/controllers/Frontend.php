<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Frontend extends Public_Controller {

	public function index()
	{
		$this->webpage('frontend');
	}

	
	public function webpage($slug)
	{
		
		//ten minute cache
		$this->output->cache(5);
		
		$sections = [];
		
		//cache enabled
		$this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));
		$data = $this->cache->get("frontend/webpage/$slug");
// 		$data = null;
		
		if (!$data)
		{
		        log_message('DEBUG', "Saving frontend/webpage/$slug to the cache");

		        $this->load->model('admin/webpage');
		        $webpage = $this->webpage->get_all('',"slug = '$slug' and kind = 1");
		
				if(!empty($webpage))
				{
					//cargar la sección referida en el webpage
					$webpage_id = $webpage[0]['id'];
					$this->load->model('admin/page_section');
					$sections =  $this->page_section->get_all('',"webpage_id = '$webpage_id'");			
				}
				
				//cargar los page_items de esa seccion
				$this->load->model('admin/page_item');
				foreach($sections as &$section)
				{
					$page_section_id = $section['id'];
					$section['page_items'] = $this->page_item->get_all('',"page_section_id = $page_section_id");
				}

				$data['sections'] = $sections;
						
		        // Save into the cache for 5 minutes
		        $this->cache->save("frontend/webpage/$slug", $data, 300);
		} else {
			log_message('DEBUG', "Using frontend/webpage/$slug cache");
		}
		
		$this->load_view('webpage',$data);
	}
	
	public function test()
	{
		$this->load->library('admin/amazons3');
		$aws_bucket = $this->config->item('aws_bucket');
		$aws_accesskey = $this->config->item('aws_accesskey');
		$aws_secretkey = $this->config->item('aws_secretkey');
		
		$this->amazons3->aws_bucket = $aws_bucket;
		$this->amazons3->aws_accesskey = $aws_accesskey;
		$this->amazons3->aws_secretkey = $aws_secretkey;
		
		
		//$this->amazons3->upload_file('/home/manager/app/private/user/ef2d3c16686589d1dd8eff7b65a59cc8.jpg');
		//$result = $this->amazons3->list_files();
		$result = $this->amazons3->save_file('ef2d3c16686589d1dd8eff7b65a59cc8.jpg', '/home/manager/app/private/temp/');
		print("<pre>");
		print_r($result);
		print("</pre>");
	}

}
