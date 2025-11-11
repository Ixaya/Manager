<?php
//  Created by Kevin Martinez on 29/08/24.
//  Copyright © 2024 Ixaya. All rights reserved.
//

class Sysusers extends IX_Rest_Controller
{

    function __construct()
    {
        $this->group_methods['*']['level'] = LEVEL_ADMIN;
        // $this->group_methods['*']['group'] = GROUP_ADMIN;

        parent::__construct();
    }

    public function index_get()
    {
        $params = [
            'page' => $this->get('page') && is_numeric($this->get('page')) && $this->get('page') > 0 ? intval($this->get('page')) : 1,
            'limit' => $this->get('limit') && is_numeric($this->get('limit')) && $this->get('limit') > 0 ? intval($this->get('limit')) : 10,
            'search' => $this->get('searchQuery') ? trim($this->get('searchQuery')) : '',
            'order' => $this->get('order') && in_array(strtoupper($this->get('order')), ['ASC', 'DESC']) ? strtoupper($this->get('order')) : 'ASC',
            'order_by' => $this->get('order_by') ? trim($this->get('order_by')) : 'id'
        ];

        $params = [];

        $page = $this->get('page');
        $params['page'] = ($page && is_numeric($page) && $page > 0) ? intval($page) : 1;

        $limit = $this->get('limit');
        $params['limit'] = ($limit && is_numeric($limit) && $limit > 0) ? intval($limit) : 10;

        $search = $this->get('searchQuery');
        $params['search'] = $search ? trim($search) : '';

        $order_input = strtoupper($this->get('order') ?? '');
        $params['order'] = in_array($order_input, ['ASC', 'DESC']) ? $order_input : 'ASC';

        $order_by = $this->get('order_by');
        $params['order_by'] = $order_by ? trim($order_by) : 'id';

        try {
            $this->load->model('user');
            $users = $this->user->get_list($params);

            if (!is_array($users)){
                $users['data'] = [];
                $users['total'] = 0;
            }

            $response = [
                'status' => 1,
                'result' => true,
                'response' => [
                    'users' => $users['data'] ?? [],
                    'recordsTotal' => $users['total'] ?? 0,
                    'recordsFiltered' => 0

                ]
            ];

            $this->response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $response = [
                'status' => 0,
                'result' => false,
                'error' => $e->getMessage()
            ];
            $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    public function create_post()
    {
        $this->load->model('admin/user');

        $data = $this->post();

        if (empty($data)) {
            $this->response([
                'status' => 0,
                'message' => 'No data provided, please try again.'
            ], REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        try {

            $password = $this->post('password');
            $email    = $this->post('email');
            $group_id = array($this->post('role'));

            $additional_data = array(
                'first_name' => $this->post('first_name'),
                'last_name'  => $this->post('last_name'),
                'username'   => $this->post('username'),
                'company'    => $this->post('company'),
                'phone'      => $this->post('phone'),
            );

            $user = $this->ion_auth->register($email, $password, $email, $additional_data, $group_id);
            if (!$user) {
                $this->response([
                    'status' => 0,
                    'message' => 'Something went wrong while creating the user. Please try again.'
                ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                return;
            } else {
                if ($this->post('status') == 1){
                    $this->ion_auth->activate($user);
                }

                if (!empty($_FILES['image']['name'])) {
                    $resolution = [250, 250];
                    $image_field = 'image';
                    $relative_path = "media/user_profile/$user/";

                    $image_data = $this->upload_image($relative_path, $image_field, FALSE, $image_field, $resolution);
                    unset($data);
                    if ($image_data) {
                        $data['image_name'] = $image_data['thumb_name'];
                        $data['image_url'] = $image_data['thumb_url'];

                        $this->user->update($data, $user);
                    }
                }

                $this->response([
                    'status' => 1,
                    'message' => 'User created successfully.',
                    'response' => $user
                ], REST_Controller::HTTP_OK);
                return;
            }
        } catch (Exception $e) {
            $this->response([
                'status' => 0,
                'message' => 'Error: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }
    }

    public function update_post()
    {
        $this->load->model('admin/user');

        $data = $this->post();

        if (empty($data)) {
            $this->response([
                'status' => 0,
                'message' => 'No data was provided, please try again.'
            ], REST_Controller::HTTP_BAD_REQUEST);
            return;
        }

        try {
            $id = $this->post('id');
            $data = [
                'first_name' => $this->post('first_name'),
                'last_name'  => $this->post('last_name'),
                'email'      => $this->post('email'),
                'username'   => $this->post('username'),
                'company'    => $this->post('company'),
                'phone'      => $this->post('phone'),
            ];

            $newPassword = $this->post('password');
            if (!empty($newPassword)) {
                //if you use: ion_auth->update there is no need to encrypt it, else it will double crypt it.
                $data['password'] = $newPassword;
            }

            if ($this->post('status') == 1)
                $this->ion_auth->activate($id);
            else
                $this->ion_auth->deactivate($id);


            $this->ion_auth->remove_from_group('', $id);
            $this->ion_auth->add_to_group($this->post('role'), $id);

            $this->ion_auth->update($id, $data);

            if (!empty($_FILES['image']['name'])) {
                $resolution = [250, 250];
                $image_field = 'image';
                $relative_path = "media/user_profile/$id/";

                $image_data = $this->upload_image($relative_path, $image_field, FALSE, $image_field, $resolution);
                unset($data);
                if ($image_data) {
                    $data['image_name'] = $image_data['thumb_name'];
                    $data['image_url'] = $image_data['thumb_url'];

                    $this->user->update($data, $id);
                }
            }

            $this->response([
                'status' => 1,
                'message' => 'User updated succesfully',
                'response' => $id
            ], REST_Controller::HTTP_OK);
            return;
        } catch (Exception $e) {
            $this->response([
                'status' => 0,
                'message' => 'Error: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }
    }

    public function details_get()
    {
        $this->load->model('user', 'user_key', 'login_attempt');

        $id = $this->get('id');
        $response = [];

        if (empty($id)) {
            $this->response([
                'status' => 0,
                'message' => 'The user ID is required.'
            ], REST_Controller::HTTP_OK);
            return;
        }

        try {
            $api_key_obj = $this->user_key->get_where(['user_id' => $id]);
            $api_key = "User doesn't have an API Key";
            if (!empty($api_key_obj)) {
                $api_key = $api_key_obj->key;
            }
            $data['user'] = $this->ion_auth->user($id)->row();
            $data['api_key'] = $api_key;
            $data['user_group'] = $this->ion_auth->get_users_groups($id)->row();
            $data['login_attempts'] = $this->login_attempt->get_by_user($id);

            $response['user'] = [
                'id' => $data['user']->id,
                'email' => $data['user']->email,
                'username' => $data['user']->username,
                'first_name' => $data['user']->first_name,
                'last_name' => $data['user']->last_name,
                'company' => $data['user']->company,
                'phone' => $data['user']->phone,
                'active' => $data['user']->active,
                'image'  =>  $this->get_file_base64($data['user']->image_url),
                'user_groups' => [
                    'id' => $data['user_group']->id,
                ],
                'api_key' => $data['api_key'],
                'ip_address' => $data['user']->ip_address,
                'last_update' => $data['user']->last_update
            ];
        } catch (Exception $e) {
            $this->response([
                'status' => 0,
                'message' => 'Error: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }

        $this->response([
            'status' => 1,
            'message' => 'Usuario',
            'response' => $response
        ], REST_Controller::HTTP_OK);
    }

    public function clear_login_attempts_post()
    {
        $username =  $this->post('username');
        if (!empty($username)) {
            $response = $this->ion_auth->clear_login_attempts($username);
            if (!empty($response)) {
                $this->response(['status' => 1, 'message' => 'Cleared successfully'], REST_Controller::HTTP_OK);
            } else {
                $this->response(['status' => -1, 'error' => 'Not Found'], REST_Controller::HTTP_NOT_FOUND);
            }
        }
    }

    public function delete_post()
    {
        $this->load->model('admin/user');

        try {
            $id = $this->post('id');

            $result = $this->ion_auth->delete_user($id);
            if ($result === true){
                $this->response([
                    'status' => (int)1,
                    'message' => 'User deleted successfully',
                    'response' => $result
                ], REST_Controller::HTTP_OK);
            }
        } catch (Exception $e) {
            mngr_process_exception($e);
        }

        $this->response([
            'status' => 0,
            'message' => 'Error deleting user'
        ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function roles_get()
    {
        try {
            $roles = $this->ion_auth->groups()->result();
        } catch (Exception $e) {
            $this->response([
                'status' => 0,
                'message' => 'Error getting roles'
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }

        $this->response([
            'status' => 1,
            'message' => 'Success',
            'response' => $roles
        ], REST_Controller::HTTP_OK);
    }
}
