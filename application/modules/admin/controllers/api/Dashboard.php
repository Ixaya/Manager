<?php
//  Created by Kevin Martinez on 29/08/24.
//  Copyright © 2024 Ixaya. All rights reserved.
//

class Dashboard extends IX_Rest_Controller
{
    public function __construct()
    {
        $this->group_methods['*']['level'] = LEVEL_ADMIN;

        parent::__construct();
    }

    public function index_get()
    {
        try {
            $this->load->model('admin/user');

            $response = [];
            $response['users_count']   = intval($this->user->count_all());

            $this->response(['status' => 1, 'response' => $response], REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->response(['status' => -1, 'message' => 'Failed to load dashboard data'], REST_Controller::HTTP_BAD_REQUEST);
        }
    }
}
