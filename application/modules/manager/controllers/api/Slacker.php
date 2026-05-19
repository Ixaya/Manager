<?php

//
//  Slacker.php
//  Ixaya
//
// Created by Gustavo Moya on 02/17/18.
//  Copyright © 2018 Ixaya. All rights reserved.
//

defined('BASEPATH') or exit('No direct script access allowed');

class Slacker extends IX_Rest_Controller
{
	public function __construct()
	{
		$this->methods['*']['level'] = 2;

		parent::__construct();
	}

	public function order_progress_post()
	{
		$order = null;
		$message 		  = $this->post('message');
		$order_id 		= $this->post('order_id');



		$this->load->model('api/slack');
		$this->load->model('admin/product_order');

		if ($order_id != null && $order_id != '') {
			$order = $this->product_order->get($order_id);
		}
		$result = $this->slack->order_progress($message, $order);


		$this->set_response(["result" => $result], REST_Controller::HTTP_CREATED);
	}
}
