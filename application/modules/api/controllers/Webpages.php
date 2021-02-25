<?php
//
//  Webpages.php
//  Ixaya
//
// Created by Gustavo Moya on 02/23/21.
//  Copyright © 2021 Ixaya. All rights reserved.
//

defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'modules/api/libraries/IX_Rest_Controller.php';

class Webpages extends IX_Rest_Controller {
	
	function __construct() {
		parent::__construct();
		
		//$this->methods['*']['level'] = 2;
		
	}
	
	
	public function create_post()
	{
		$data['title'] = $this->post('title');
		$data['slug'] = $this->post('slug');
		$data['kind'] = $this->post('kind');
		
		$this->load->model('admin/webpage');
		$webpage_id = $this->webpage->insert($data);
		
		$response['webpage_id'] = $webpage_id;
		
		
		$result['status'] = 1;
		$result['response'] = $response;
		$result['message'] = 'Webpage created successfully';
		$this->set_response($result, REST_Controller::HTTP_OK);
	}
	
	
	
	public function delete_post()
	{
		$webpage_id = $this->post('webpage_id');
		
		if(!empty($webpage_id))
		{
			
			$this->load->model('admin/webpage');
			$webpage_id = $this->webpage->delete($webpage_id);
			
			$response['webpage_id'] = $webpage_id;
			
			$result['status'] = 1;
			$result['response'] = $response;
			$result['message'] = 'Webpage deleted successfully';
			$this->set_response($result, REST_Controller::HTTP_OK);
		}
		
		$result['status'] = -1;
		$result['response'] = null;
		$result['message'] = "webpage_id is empty";
		$this->set_response($result, REST_Controller::HTTP_OK);		
	}
	
	public function list_get()
	{
		$this->load->model('admin/webpage');
		$webpages = $this->webpage->get_all();
		$result['status'] = 1;
		$result['response'] = $webpages;
		$this->set_response($result, REST_Controller::HTTP_OK);
	}
	
	public function list_kinds()
	{
		$this->load->model('admin/webpage');
		$kinds = $this->webpage->get_kinds();
		$result['status'] = 1;
		$result['response'] = $kinds;
		$this->set_response($result, REST_Controller::HTTP_OK);
	}

}