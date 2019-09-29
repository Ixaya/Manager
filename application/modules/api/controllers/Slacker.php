<?php
//
//  Slacker.php
//  Ixaya
//
// Created by Gustavo Moya on 02/17/18.
//  Copyright © 2018 Ixaya. All rights reserved.
//

defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'modules/api/libraries/IX_Rest_Controller.php';

class Slacker extends IX_Rest_Controller {
	
	function __construct() {
		parent::__construct();
		
		$this->methods['*']['level'] = 2;
	}
	
	function order_progress_post()
	{   
		$order = NULL;
		$message 		= $this->post('message');
		$order_id 		= $this->post('order_id');
		
		
		
		$this->load->model('api/slack');
		$this->load->model('admin/product_order');
		
		if($order_id != null && $order_id != '')
		{
			$order = $this->product_order->get($order_id);
		}
		$result = $this->slack->order_progress($message, $order);
		
		
		$this->set_response(array("result"=>$result), REST_Controller::HTTP_CREATED);
	}
}

