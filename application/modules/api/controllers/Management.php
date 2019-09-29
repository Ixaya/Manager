<?php
//
//  Management.php
//  Ixaya
//
// Created by Humberto Olavarrieta on 11/29/17.
//  Copyright © 2017 Ixaya. All rights reserved.
//

defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'modules/api/libraries/IX_Rest_Controller.php';

class Management extends IX_Rest_Controller {
	
	function __construct() {
		parent::__construct();
		
		$this->methods['*']['level'] = 2;
	}
	
	
	/**
	 * send_checkout_mail function.
	 * 
	 * @access public
	 * @param mixed $order_id (default: null)
	 * @return void
	 */


	//in use since 11 february 2018
	//WRAPPER Functions
	public function send_checkout_mail_post()
	{
		$order_id = $this->post('order_id');
		log_message('debug',"send_checkout_mail_post, order_id=$order_id");
		$this->load->library('admin/checkout_email');		
		$result =  $this->checkout_email->send_checkout_mail($order_id);		
		$this->set_response($result, REST_Controller::HTTP_OK);

	}
	public function send_checkout_mail_get()
	{
		$order_id = $this->get('order_id');
		log_message('debug',"send_checkout_mail_get, order_id=$order_id");
		$this->load->library('admin/checkout_email');		
		$result =  $this->checkout_email->send_checkout_mail($order_id);		
		$this->set_response($result, REST_Controller::HTTP_OK);

	}

	/**
	 * unencrypt_credit_card_post function.
	 * 
	 * @access public
	 * @return void
	 */
	function unencrypt_credit_card_post()
	{   
		$payment_id = $this->post('payment_id');
		$kind 		= $this->post('kind');
		
		log_message('debug',"unencrypt_credit_card_post payment_id=$payment_id, kind=$kind");
		$this->load->model('admin/payment_method');
		
		$card = $this->payment_method->unencrypt_credit_card($payment_id, $kind);   
		
		$result["unencrypted_ccn"] = $card["unencrypted_ccn"];
		$result["unencrypted_cvv"] = $card["unencrypted_cvv"];
		
		//print_r(json_decode(json_encode($result, JSON_PRETTY_PRINT)));
		
		$this->set_response(array("result"=>$result), REST_Controller::HTTP_CREATED);
	}
	
	public function upload_checkout_file_post()
	{

/*
		$userID = $this->user_id;
		if (empty($userID))
			 $this->response(array('status' => -1, 'message' => "Not logged in"));

			 
		$this->load->model('api/Auth_model','auth'); 
*/
		
		
		
		
		
		$this->load->helper(array('url', 'image'));
			
		$config['upload_path']		  = 'media/';
		//$config['upload_path']		  = '/localhost/public/media/';
		$config['allowed_types']		= 'gif|jpg|png';
		$config['max_size']			 = 2048; //2MB (PHP Max in this config)
//			 $config['max_width']			= 1024;
//			 $config['max_height']		   = 1024;
		$config['max_width']			= 0; // no size restriction
		$config['max_height']		   = 0; // no size restriction

		$config['encrypt_name']			= false; 
		$config['remove_spaces']		= true;
		

		$this->load->library('upload', $config);

		if ( ! $this->upload->do_upload('screenshot_image'))
		{
			$upload_data = $this->upload->data();
			$this->response(array('status' => -1, 'message' => $this->upload->display_errors(), 'extra' => $upload_data), REST_Controller::HTTP_OK);
		}
		else
		{		
				$upload_data = $this->upload->data();

				//$data['image_url']   = base_url("/media/".$upload_data['file_name']);
				$data['media_url']  = $upload_data['file_name'];
				
				$data['order_id'] 		= $this->post('order_id');
				$data['message'] 		= $this->post('message');
				$data['kind'] 			= $this->post('kind');
				$data['media_kind'] 	= $this->post('media_kind');
// 				$data['media_url']  	= $this->post('media_url');
// 				$data['last_update']	= date();
		
				$this->load->model('admin/Product_order_log');
				$this->Product_order_log->insert($data);		
					
				$this->response(array('status' => 1, 'result' => $data['image_name']), REST_Controller::HTTP_OK);
		}
	}	
}

