<?php
//
//  Slack.php
//  Züggig
//
// Created by Gustavo Moya on 02/17/18.
//  Copyright © 2018 Ixaya. All rights reserved.
//
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'modules/api/libraries/API_Model.php';

class Slack extends API_Model {
    
    public function __construct() {
        //overrides
        //$this->connection_name = 'catalog';
        //$this->client_id = 1;
        
        //initialize after overriding
        parent::__construct();
    }
    
    function new_signup($user_id)
    {
	    $email = null;
	    $firstname = null;
	    $lastname = null;
	    $gender = null;
	    $last_activity_os = null;
	    $facebook_id = null;
	    $image_url = null;
	    
	    $query_result = $this->query_as_array_auto("SELECT * FROM ic_user where id = $user_id");
	    if(is_array($query_result) && count($query_result) > 0)
	    {
			$email = $query_result[0]['email'];
		    $firstname = $query_result[0]['first_name'];
		    $lastname = $query_result[0]['last_name'];
		    $gender = $query_result[0]['gender'];
		    $last_activity_os = $query_result[0]['last_activity_os'];
		    $facebook_id = $query_result[0]['facebook_id'];
		    $image_url = $query_result[0]['image_url'];
		    
	    }
	    
	    $os = '';
	    if($last_activity_os == 1)
	    	$os = '';
	    	
		switch ($last_activity_os) {			
		    case 0:
		        $os = "Unknown";
		        break;
		    case 1:
		        $os = "iOS";
		        break;
		    case 2:
		        $os = "Android";
		        break;
		}
	    	
	    $data = array(
	            'text'      => "$message"
	    );
	    $data_string = json_encode($data);

	    $curl = curl_init('https://hooks.slack.com/services/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');
	
	    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
	
	    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
	    'Content-Type: application/json',
	    'Content-Length: ' . strlen($data_string))
	    );
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);  // Make it so the data coming back is put into a string
	    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);  // Insert the data
	
	    // Send the request
	    $result = curl_exec($curl);
	
	    // Free up the resources $curl is using
	    curl_close($curl);
	
	    return $result;    
    }
    
    
    function order_progress($message=NULL, $order=NULL, $order_id=NULL, $status=NULL, $mode=NULL)
    {   
    	if(!$message)
    	{	
	    	$step = '';
	    	if($order)
	    	{	
		    	$order_id = $order->id;
				$status = $order->status;
				$mode	= $order->mode;
				$step = ' step: ('. $order->step .')';
	    	} else if(!$order_id && !$status)
	    	{
				log_message('debug',"Order failed to report to slack, not status, message, or order_id");
			}
	    	log_message('debug',"Order Status: $status");
	    	switch (intval($status)) {
			    case -1:
			        $message = "Order $order_id failed with status: ($status)$step";
			        break;
			    case 0:
			        $message = "Order $order_id placed with status: ($status)$step";
			        break;
			    case 1:
			        $message = "Order $order_id in progress with status: ($status)$step";
			        break;
			    case 2:
			        $message = "Order $order_id successful with status: ($status)$step";
			        break;
			}
    	}
    	
		$data = array(
	            'text'      => "$message"
	    );
	    $data_string = json_encode($data);
	    
	    
		if($mode == NULL)
		{
			$result = $this->query_as_array_auto("SELECT id, mode FROM product_order where id = $order_id");
			if(count($result) > 0)
			{
				$mode = $result[0]['mode'];
				log_message('debug', "Slacker: Order Mode Fetched from DB: $mode");
			} else {
				log_message('debug', "Slacker: Error Fetching Order Mode");
			}
		}

	
		//production
		$curl_url = 'https://hooks.slack.com/services/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';
		
		if($mode == 't' || $mode == 'd')
	    {
		    //test
			$curl_url = 'https://hooks.slack.com/services/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';
	    }

	    $curl = curl_init($curl_url);
	
	    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
	
	    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
	    'Content-Type: application/json',
	    'Content-Length: ' . strlen($data_string))
	    );
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);  // Make it so the data coming back is put into a string
	    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);  // Insert the data
	
	    // Send the request
	    $result = curl_exec($curl);
	
	    // Free up the resources $curl is using
	    curl_close($curl);
	
	    return $result;    
	}
    
}
