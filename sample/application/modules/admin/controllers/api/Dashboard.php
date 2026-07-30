<?php

//  Created by Kevin Martinez on 29/08/24.
//  Copyright © 2024 Ixaya. All rights reserved.
//

class Dashboard extends APP_Rest_Controller
{
	public function __construct()
	{
		$this->group_methods['*']['level'] = LEVEL_ADMIN;

		parent::__construct();
	}

	public function index_get()
	{
		$this->load->model('admin/user');

		$response = [];
		// null = the count query failed; must stay distinguishable from a real 0.
		// Left un-caught deliberately: each dashboard metric fails independently.
		$response['users_count']   = $this->user->count_all();

		$this->response(['status' => 1, 'response' => $response], REST_Controller::HTTP_OK);
	}
}
