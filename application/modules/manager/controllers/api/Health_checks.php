<?php
//
//  Health_checks.php
//  Ixaya
//
// Created by Humberto Olavarrieta on 2/3/17.
// Copyright © 2017 Ixaya. All rights reserved.
//

if (! defined('BASEPATH')) exit('No direct script access allowed');

class Health_checks extends REST_Controller
{
	public function __construct()
	{
		$this->methods['*']['auth_override'] = 'none';

		parent::__construct();
	}
	public function index_get()
	{
		//Uncomment to send into frontend
		//redirect('https://www.example.com');

		$this->response(['status' => 1, 'message' => "API running"], REST_Controller::HTTP_OK);
	}
	public function not_found_get()
	{
		$this->response(['status' => -1, 'message' => "404 Not Found"], REST_Controller::HTTP_NOT_FOUND);
	}

	public function validate_get()
	{
		$this->load->model('auth/user');

		// $data = $this->user->get_all('username, id, wololo');
		// var_dump($data);
		// $data = $this->user->get_all('username, id');
		// var_dump($data);
		// $data = $this->user->get(1);
		// var_dump($data);
		// $data = $this->user->get(50);
		// var_dump($data);
		// $data = $this->user->get_where(['email' => 'ho@ixaya.cpom']);
		// var_dump($data);
		// $data = $this->user->get_where(['email' => 'ho5@ixaya.com']);
		// var_dump($data);
		// $data = $this->user->get_all('username, id, wololo');
		// var_dump($data);
		// $data = $this->user->get_all('username, id, wololo');
		// var_dump($data);
		// $data =  $this->user->query("SELECT id FROM user"); //CI_DB_mysqli_result
		// var_dump($data);
		// $data =  $this->user->query("UPDATE user_key SET device_uuid = 'AAAaa' where id = 1");//true
		// $data =  $this->user->query_alter("UPDATE user_key SET device_uuids = 'AAA' where id = 4");//false 
		// var_dump($data);

		$this->load->driver('cache');
		// $data = [
		// 	'name' => "\xB1\x31"
		// ];
		$data = ['hello'=>'yes'];
		$this->cache->save('test', $data, 58);
		$data = $this->cache->delete('test');
		var_dump($data);

		$data = (object)['hello' => 'yes'];
		var_dump($data);
		$this->cache->save('test_php', $data, 66, false, 'none');
		$data = $this->cache->get('test_php');
		var_dump($data);
	}
}
