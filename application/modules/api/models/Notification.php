<?php
//
//  Notification.php
//  Ixaya
//
// Created by Humberto Olavarrieta on 5/2/17.
//  Copyright © 2017 Ixaya. All rights reserved.
//

defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'modules/api/libraries/API_Model.php';

class Notification extends API_Model {
	
	public function __construct() {
		//overrides
		$this->table_name = 'notification';
		//$this->client_id = 1;
		
		//initialize after overriding
		parent::__construct();
	}
	
	public function get_all_custom($userID, $limit, $offset)
	{
		if (empty($limit))
			$limit = 10;
			
		if (empty($offset))
			$offset = 0;
		
		$rows = $this->query_as_array_auto("SELECT n.id, n.message, n.kind, n.last_update, n.status, n.target_user_id as user_id, n.product_id, uf.authorized as user_following, "
										   ."u.image_url as user_image_url, u.image_name as user_image_name, u.first_name as user_name, p.image_url as product_image_url "
										   ."FROM notification as n "
										   ."LEFT JOIN user_friend as uf ON n.target_user_id = uf.follow_user_id AND n.user_id = uf.user_id "
										   ."LEFT JOIN ic_user as u ON n.target_user_id = u.id "
										   ."LEFT JOIN product as p ON n.product_id = p.id "
										   ."WHERE n.user_id = ? AND n.status <> -2 "
										   ."ORDER By n.id DESC LIMIT ? OFFSET ?", array($userID, $limit, $offset));
		
		
		
		$this->updateReadNotifications($userID);
		
		return $rows;
	}
	
	public function notifyUser($toUserID, $fromUserID, $kind=0, $productID = false, $messageUser = false)
	{
		//log_message('error', 'User:' . $toUserID);
		$this->load->model('api/notification_push','push');
		$this->load->model('api/profile','profile');
		
		$userRow = $this->profile->get_user_profile($fromUserID, true);
		$userName = $userRow['first_name'];
		
		$notification_data = array();

		if ($productID != false)
		{
			$notification_data ['product_id'] = $productID;
			
			$this->load->model('api/product','product');
			$productRow = $this->product->get($productID);
		}
		//Create Journal
		$messagePushSubtitle = false;
		switch($kind)
		{
			case 0:
				$message = 'aceptado tu solicitud.';
				$messagePush = $userName.' ha aceptado tu solicitud.';
				break;
			case 1:
				$message = 'ahora te sigue.';
				$messagePush = 'Ahora te sigue '.$userName.'.';
				break;
			case 2:
				$message = 'enviado una solicitud.';
				$messagePush = $userName.' ha enviado una solicitud.';
				break;
			case 3:
				$message = 'te ha compartido.';
				$messagePush = $userName.' te ha compartido '.$productRow->product_name.'.';
				if (!empty($messageUser)){
					$messagePushSubtitle = $messageUser;
					$message = $messageUser;
				}
				break;
			case 4:
				$message = $messageUser;
				$messagePush = null;
				break;
			case 5:
				$message = $messageUser;
				$messagePush = null;
				$kind = 3; 
				break;
			default:
				$message = '';
				$messagePush = null;
				$kind = 0; //gm-fix		 
				break;
		}
		
		$notification_data ['message'] 		  = $message;
		$notification_data ['user_id']		  = $toUserID;
		$notification_data ['target_user_id'] = $fromUserID;
		$notification_data ['kind']		 	  = $kind;
		
		
		log_message('debug','************ Notification Crash ************');
		log_message('debug',json_encode($notification_data));
		
		if($kind == '0' && $fromUserID == '1')
		{
			log_message('debug','IMPOSIBLE NOTIFICATION FROM MANAGER');
			return 0;
		}
		
		$notification_id = $this->insert($notification_data);
		
		if ($messagePush != null)
			$update_data['delivery_status'] = $this->push->sendNotification($toUserID, $messagePush, $messagePushSubtitle, 1, $notification_id);
		
		return $notification_id;
	}
	
	
	public function updateNotification($toUserID, $fromUserID, $kind, $status)
	{
		$data = array('status' => $status);

		$this->db->where('user_id', $toUserID);
		$this->db->where('target_user_id', $fromUserID);
		$this->db->where('kind', $kind);
		$this->db->where('status !=', -2);
		
		return $this->db->update($this->table_name, $data);
	}
	
	public function updateReadNotifications($userID)
	{
		$data = array('read_status' => 1);

		$this->db->where('user_id', $userID);
		
		return $this->db->update($this->table_name, $data);
	}
}
