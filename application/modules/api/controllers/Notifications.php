<?php
//
//  Notifications.php
//  Ixaya
//
// Created by Humberto Olavarrieta on 6/19/17.
//  Copyright © 2017 Ixaya. All rights reserved.
//

defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'modules/api/libraries/IX_Rest_Controller.php';

class Notifications extends IX_Rest_Controller {
	
	function __construct() {
		parent::__construct();
		
		$this->methods['send']['level'] = 2;
		
		$this->setupModel('api/notification_push','push');
		
	}
	
	public function add_device_post()
	{
		$result = $this->push->addDevice($this->user_id, $this->post('token'), $this->post('os_kind'));
		
		$this->response(array('result' => $result), REST_Controller::HTTP_OK);
	}
	
	public function read_post()
	{
		$this->setupModel('api/notification','notification');
		
		$id = $this->post('id');
		if (!empty($id))
		{
			$data = array();
			$data['read_status'] = 1;
			
			$result = $this->notification->update($data, $id);
			
			$this->response(array('result' => $result), REST_Controller::HTTP_OK);
		} else {
			$this->response(array('result' => false), REST_Controller::HTTP_OK);
		}
	}
	
	public function pending_post()
	{		
		$result = $this->push->getPendingNotifications($this->user_id);
		
		$this->response(array('result' => $result), REST_Controller::HTTP_OK);
	}
	
	public function remove_device_post()
	{
		$result = $this->push->removeDevice($this->user_id, $this->post('token'));
		
		$this->response(array('result' => $result), REST_Controller::HTTP_OK);
	}
	
	public function send_scheduled_get()
	{
		//crontab: */5  *  *  *  * /usr/bin/php -f /home/ixayanet/app/public/index.php api notifications send_scheduled >> /home/ixayanet/logs/notifications_log
		
		$ip = $this->input->ip_address();
		if ($ip != '0.0.0.0'){
			echo('<pre>{"status":false,"error":"Invalid API key "}</pre>');
			exit();
		}
		
		$this->setupModel('api/notification_schedule','schedule');
		
		$sheduledNotifications = $this->schedule->getPending();
		if (count($sheduledNotifications) == 0){
// 			$this->print_log(array('result'=>'No notifications'));
			return false;
		}
		
		$notification = $sheduledNotifications[0];
		
		if (empty($notification['users']))
			$userRows = $this->push->getAllUsers($notification['mode'], $notification['delivery_offset']);
		else
			$userRows = $this->push->getSelectedUsers($notification['users'], $notification['delivery_offset']);
		
		if (count($userRows) == 0){
			$data = array('delivery_status' => 2);
			$this->schedule->update($data, $notification['id']);
			
			$this->print_log(array('notification' => $notification['id'], 'result' => 'No users remaining'));
			
			return false;
		} else {
			$data = array('delivery_status' => 4);
			$this->schedule->update($data, $notification['id']);
		}
		
		$this->setupModel('api/notification','notifications');
   
		$result = array();
		foreach ($userRows as $userRow)
		{
			$message = str_replace("#user#", $userRow['first_name'], $notification['message']);
	
			if ($notification['mode'] == 'p')
				$notification_id = $this->notifications->notifyUser($userRow['id'], 1, $notification['layout_kind'], $notification['action_id'], $message);
			else
				$notification_id = 0;
				
			$resultRow = $this->push->sendNotification($userRow['id'], $message, false, $notification['action_kind'], $notification_id, $notification['action_id'], false);
			array_push($result, array('user'=> $userRow['id'], 'result'=> $resultRow));
		}
		
		$data = array('delivery_status' => 3, 'delivery_offset' => $notification['delivery_offset'] + 1);
		$this->schedule->update($data, $notification['id']);
		
		$this->print_log(array('notification' => $notification['id'], 'result' => $result));
		return true;
	}
	
	public function send_post()
	{
		$userID = $this->post('id');
		$text = $this->post('text');
		$subtext = $this->post('subtext');
		$kind = $this->post('kind');
		$action = $this->post('action');
		$instant = $this->post('instant');
		
		if (is_null($userID) || is_null($text) || is_null($kind))
			return ;
			
		if (empty($instant))
			$instant = 0;

		$result = $this->push->sendNotification($userID, $text, $subtext, $kind, 0, $action, false, $instant);
		$this->response(array('result' => $result), REST_Controller::HTTP_OK);
	}
		
	public function test_apple_post()
	{
		$token = $this->post('token');
		
		if (is_null($token))
			return ;
		
// 		$result = $this->push->sendAppleNotification($token, 'Test push', false, 2, 6363, 2);
// 		$result = $this->push->sendAppleNotification($token, 'Test push', false, 3, 525156, 1 );
// 		$result = $this->push->sendAppleNotification($token, 'Test push', false, 4, 269, 2);
		$result = $this->push->sendAppleNotification($token, 'Test push', false, 6, 41, 3);
		
		$this->response(array('result' => $result, 'token' => $token), REST_Controller::HTTP_OK);
	}
	
	public function test_android_post()
	{
		$token = $this->post('token');
		
		if (is_null($token))
			return ;
		
// 		$result = $this->push->sendAndroidNotification($token, 'Test push','Que onda!', 1, 5);
// 		$result = $this->push->sendAndroidNotification($token, 'Test push','Que onda!', 3, 25020131);//Product
// 		$result = $this->push->sendAndroidNotification($token, 'Test push','Que onda!', 4, 277);//Order
		$result = $this->push->sendAndroidNotification($token, 'Test push','Que onda!', 6, 41);//Card
		
		$this->response(array('result' => $result), REST_Controller::HTTP_OK);
	}
}

