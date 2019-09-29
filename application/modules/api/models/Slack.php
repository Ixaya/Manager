<?php
//
//  Slack.php
//  Ixaya
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
	
	function send_message($message=NULL)
	{   
		if(!$message)
		{	
			//example logic
		}
		
		$data = array(
				'text'	  => "$message"
		);
		$data_string = json_encode($data);
		
	
		//production url
		$curl_url = 'https://hooks.slack.com/services/XXXXXXXX';
		
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
