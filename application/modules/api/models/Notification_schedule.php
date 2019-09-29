<?php
//
//  Notification_schedule.php
//  Ixaya
//
// Created by Humberto Olavarrieta on 11/29/17.
//  Copyright © 2017 Ixaya. All rights reserved.
//

defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'modules/api/libraries/API_Model.php';

class Notification_schedule extends API_Model {
	
	public function __construct() {
		//overrides
//		 $this->table_name = 'notification';
		//$this->client_id = 1;
		
		//initialize after overriding
		parent::__construct();
	}
	
	public function getPending()
	{
		
		$now = new DateTime(null, new DateTimeZone('UTC'));
		$timestamp = $now->format('Y-m-d H:i:s');

		$userRows = $this->query_as_array_auto("SELECT * FROM notification_schedule WHERE delivery_status in (1, 3) AND start_time <= ?", array($timestamp));
					
		
		return $userRows;
	}
}
