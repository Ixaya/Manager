<?php
//
//  Notification_push.php
//  Ixaya
//
// Created by Humberto Olavarrieta on 6/22/17.
//  Copyright © 2017 Ixaya. All rights reserved.
//

defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'modules/api/libraries/API_Model.php';

use sngrl\PhpFirebaseCloudMessaging\Client;
use sngrl\PhpFirebaseCloudMessaging\Message;
use sngrl\PhpFirebaseCloudMessaging\Recipient\Device;
use sngrl\PhpFirebaseCloudMessaging\Notification;


class Notification_push extends API_Model {
	
	public function __construct() {
		//overrides
		$this->table_name = 'ic_user_push';
		//$this->client_id = 1;
		
		//initialize after overriding
		parent::__construct();
	}
	
	
	public function addDevice($userID, $token, $osKind)
	{
// 		if (is_null($userID) || is_null($token) || $token == 'null' || is_null($osKind))
// 			return FALSE;
		
		$tokenRows = $this->query_as_array_auto("SELECT id FROM ".$this->table_name." WHERE user_id = ? AND token = ?", array($userID, $token));

		if (count($tokenRows) > 0){
			$tokenRow = $tokenRows[0];
			$this->update(array(), $tokenRow["id"]);
			
			return $tokenRow["id"];
		}
		
		$data['user_id'] = $userID;
		$data['token']   = $token;
		$data['os_kind'] = $osKind;
		
		$success = $this->db->insert($this->table_name, $data);
		if ($success) {
			return $this->db->insert_id();
		} else {
			return FALSE;
		}
	}
	
	public function removeDevice($userID, $token)
	{
		if (empty($userID) || empty($token))
			return FALSE;
		
		$this->db->where('user_id', $userID);
		$this->db->where('token', $token);

		return $this->db->delete($this->table_name);
	}
	
	public function sendNotification($userID, $text, $subtext, $kind, $id, $action = 0, $osKind = false, $instant = 0)
	{
		$tokenRows = $this->getDevicesFromUser($userID, $osKind);
		$pendingBadge = $this->getPendingNotifications($userID);
		$result = 0;
		
		log_message('debug', 'Tokens:' . count($tokenRows) . ' Badge:' . $pendingBadge . ' User: ' . $userID);
		foreach ($tokenRows as $tokenRow)
		{
			switch ($tokenRow["os_kind"])
			{
				//iOS Prod
				case 1:
				//iOS Dev
				case 3:
				{
					$notifResult = $this->sendAppleNotification($tokenRow["token"], $text, $subtext, $kind, $id, $action, $tokenRow["os_kind"], $instant, $pendingBadge);
					if ($notifResult == TRUE)
						$result ++;
					else
						log_message('error', $notifResult);
					
					break;
				}
				case 2:
				{
					$notifResult = $this->sendAndroidNotification($tokenRow["token"], $text, $subtext, $kind, $id, $action, $tokenRow["os_kind"], $instant, $pendingBadge);
					if ($notifResult == TRUE)
						$result ++;
					else
						log_message('error', $notifResult);
					
					break;
				}
				default:{
					break;
				}
				
			}
		}
		
		if ($result == count($tokenRows))
			return TRUE;
		else
			return FALSE;
	}
	
	public function sendAppleNotification($token, $text, $subtext, $kind, $id, $action, $enviorment, $instant, $pendingBadge)
	{
		try {
		log_message('debug', 't:'.$token.' m:'.$text.' k:'.$kind);
		
		$basePath = APPPATH.'modules/api/resources/';
		
		// Instantiate a new ApnsPHP_Push object
		$apnsEnviorment;
		if ($enviorment == 1)
			$apnsEnviorment = ApnsPHP_Abstract::ENVIRONMENT_PRODUCTION;
		else
			$apnsEnviorment = ApnsPHP_Abstract::ENVIRONMENT_SANDBOX;
		
		$push = new ApnsPHP_Push(
			$apnsEnviorment,
			$basePath.'aps_mrp.pem'
		);
		
		$ixAPNSLogger = new IXAPNSLogger();
		$push->setLogger($ixAPNSLogger);

		// Set the Provider Certificate passphrase
		// $push->setProviderCertificatePassphrase('test');
		// Set the Root Certificate Autority to verify the Apple remote peer
		$push->setRootCertificationAuthority($basePath.'entrust_root_certification_authority.pem');
		// Connect to the Apple Push Notification Service
		$push->connect();
		// Instantiate a new Message with a single recipient
		$message = new ApnsPHP_Message_Custom($token);
		// Set a custom identifier. To get back this identifier use the getCustomIdentifier() method
		// over a ApnsPHP_Message object retrieved with the getErrors() message.
		//$message->setCustomIdentifier("Wololo");
		// Set badge icon to "3"
		$message->setBadge($pendingBadge);
		// Set a title text
		if ($subtext != false)
		{
			// Set subtitle text
			$message->setSubTitle($text);
			$message->setText($subtext);
		}
		else
		{
			$message->setText($text);			
		}
		// Play the default sound
		$message->setSound();
		// Set a custom property
		$message->setCustomProperty('ctl', array('kind' => $kind, 'action' => $action, 'instant' => $instant, 'id' => $id));		
		// Set the expiry value to 30 seconds
		$message->setExpiry(30);
		// Add the message to the message queue
		$push->add($message);
		// Send all messages in the message queue
		$push->send();
		// Disconnect from the Apple Push Notification Service
		$push->disconnect();
		// Examine the error message container
		$aErrorQueue = $push->getErrors();

		if (!empty($aErrorQueue))
		{
			$errors = array();
			foreach ($aErrorQueue as $errorQueue)
			{
				if (array_key_exists("ERRORS", $errorQueue))
				{
					foreach ($errorQueue["ERRORS"] as $errorRow)
						array_push($errors, $errorRow);
				}					
			}
			return $errors;
		}
		
		return TRUE;
		
		} catch (Exception $e) {
			return  $e->getMessage();
		}
	}
	
	public function sendAndroidNotification($token, $text, $subtext, $kind, $id, $action, $enviorment, $instant, $pendingBadge)
	{
		log_message('debug', 't:'.$token.' m:'.$text.' k:'.$kind);
		
		try {
			if ($subtext == false)
				$subtext = 'Notificación';
				
			$server_key = '';
			$client = new Client();
			$client->setApiKey($server_key);
			$client->injectGuzzleHttpClient(new \GuzzleHttp\Client());
			
			$message = new Message();
			$message->setPriority('high');
			$message->addRecipient(new Device($token));
			$message
			->setNotification(new Notification($text, $subtext))
			->setData(array('kind' => $kind, 'action' => $action, 'instant' => $instant, 'id' => $id));
			
			$response = $client->send($message);
			return ($response->getStatusCode());
// 			var_dump($response->getBody()->getContents());
		
		} catch (Exception $e) {
			return $e->getMessage();
		}
		
	}
	
	public function getDevicesFromUser($userID, $osKind)
	{
		$lastUpdate = new DateTimeImmutable();
		$lastUpdate = $lastUpdate->modify('-10 day');
		$lastUpdateString = $lastUpdate->format('Y-m-d H:i:s T');

		if ($osKind == false)
			$tokenRows = $this->query_as_array_auto("SELECT id, token, os_kind FROM ".$this->table_name
												   ." WHERE user_id = ? AND last_update >= ?", array($userID, $lastUpdateString));
		else
			$tokenRows = $this->query_as_array_auto("SELECT id, token, os_kind FROM ".$this->table_name
												   ." WHERE user_id = ? AND last_update >= ? AND os_kind = ?", array($userID, $lastUpdateString, $osKind));
		
		return $tokenRows;
	}
	
	public function getSelectedUsers($usersString, $offset)
	{
		$offset = $offset * 500;
		$users = explode(",",$usersString);

		$userRows = $this->query_as_array_auto("SELECT id, first_name FROM ic_user WHERE facebook_id IS NOT NULL AND id in ? ORDER BY id  LIMIT 500 OFFSET ?", array($users, $offset));
		
		return $userRows;
	}
	public function getAllUsers($mode, $offset)
	{
		$offset = $offset * 500;
		
		if ($mode == 'p')
			$userRows = $this->query_as_array_auto("SELECT id, first_name FROM ic_user WHERE facebook_id IS NOT NULL ORDER BY id  LIMIT 500 OFFSET ?", array($offset));
		else
			$userRows = $this->query_as_array_auto("SELECT id, first_name FROM ic_user "
												  ." WHERE facebook_id IS NOT NULL AND id IN (20,24) ORDER BY id LIMIT 500 OFFSET ?", array($offset));// (20,23,24,26,27,48)", NULL);

		
		return $userRows;
	}
	public function getPendingNotifications($userID)
	{
		$query = $this->db->query("select count(id) as pending from notification where user_id = ? and read_status = 0 and status > -2;", array($userID));
		$row = $query->row();
				
		return (int)$row->pending;
	}
}


class IXAPNSLogger implements ApnsPHP_Log_Interface
{
	public function log($sMessage)
	{
		log_message('debug', $sMessage);
		//Do something
	}
}