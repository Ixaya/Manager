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
        $params = array(
            'page' => $this->get('page') && is_numeric($this->get('page')) && $this->get('page') > 0 ? intval($this->get('page')) : 1,
            'limit' => $this->get('limit') && is_numeric($this->get('limit')) && $this->get('limit') > 0 ? intval($this->get('limit')) : 10,
            'search' => $this->get('searchQuery') ? trim($this->get('searchQuery')) : '',
            'order' => $this->get('order') && in_array(strtoupper($this->get('order')), ['ASC', 'DESC']) ? strtoupper($this->get('order')) : 'ASC',
            'order_by' => $this->get('order_by') ? trim($this->get('order_by')) : 'id'
        );

        try {

            $this->load->model('admin/user');
            $users = $this->user->get_list($params);

            if (!$users) {
                $this->response(['status' => 1, 'result' => true, 'response' => ['users' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]], REST_Controller::HTTP_OK);
            }

            $response = [
                'status' => 1,
                'result' => true,
                'response' => [
                    'users' => $users['data'],
                    'recordsTotal' => $users['total'],
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
                'message' => 'No se proporcionaron datos, por favor intente de nuevo.'
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
                    'message' => 'Algo salió mal al crear el usuario. Por favor intente de nuevo.'
                ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                return;
            } else {
                if ($this->post('status') == 1)
                    $this->ion_auth->activate($user);

                $relative_path = "media/user_profile/$user/";
                $image_fields = ['image'];

                if (!empty($_FILES['image']['name'])) {
                    foreach ($image_fields as &$image_field) {
                        $desired_filename =  str_replace("_filename", "", $image_field);
                        $resolution = [250, 250];
                        $image_data = $this->upload_image($relative_path, $desired_filename, FALSE, $image_field, $resolution);
                        unset($data);
                        if ($image_data) {
                            $data['image_name'] = $image_data['fullsize_image_name'];
                            $data['image_url'] = $image_data['url_image'];
                            $this->user->update($data, $user);
                        }
                        unset($image_data);
                    }
                }
                $this->response([
                    'status' => 1,
                    'message' => 'Usuario creado exitosamente',
                    'response' => $user
                ], REST_Controller::HTTP_OK);
                return;
            }
        } catch (Exception $e) {
            $this->response([
                'status' => 0,
                'message' => 'Error al crear el usuario: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }
    }

    public function show_get()
    {
        $this->load->model('admin/user');
        $this->load->model('admin/user_key');

        $id = $this->get('id');
        $response = [];

        if (empty($id)) {
            $this->response([
                'status' => 0,
                'message' => 'El ID del usuario es requerido'
            ], REST_Controller::HTTP_OK);
            return;
        }

        try {
            $api_key_obj = $this->user_key->get_where("user_id=$id");
            $api_key = "User doesn't have an API Key";
            if (!empty($api_key_obj)) {
                $api_key = $api_key_obj->key;
            }
            $data['user'] = $this->ion_auth->user($id)->row();
            $data['api_key'] = $api_key;
            $data['user_group'] = $this->ion_auth->get_users_groups($id)->row();

            $response['user'] = [
                'id' => $data['user']->id,
                'email' => $data['user']->email,
                'username' => $data['user']->username,
                'first_name' => $data['user']->first_name,
                'last_name' => $data['user']->last_name,
                'company' => $data['user']->company,
                'phone' => $data['user']->phone,
                'active' => $data['user']->active,
                'image'  =>  [
                    'url' => $data['user']->image_url == null ? null : 'https://' . ltrim(base_url($data['user']->image_url), '/'),
                    'name' => $data['user']->image_name
                ],
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
                'message' => 'Error al obtener el usuario: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }

        $this->response([
            'status' => 1,
            'message' => 'Usuario',
            'response' => $response
        ], REST_Controller::HTTP_OK);
    }

    public function update_post()
    {
        $this->load->model('admin/user');

        $data = $this->post();

        if (empty($data)) {
            $this->response([
                'status' => 0,
                'message' => 'No se proporcionaron datos, por favor intente de nuevo.'
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

            $relative_path = "media/user_profile/$id/";
            $image_fields = ['image'];

            if (!empty($_FILES['image']['name'])) {
                foreach ($image_fields as &$image_field) {
                    $desired_filename =  str_replace("_filename", "", $image_field);
                    $resolution = [250, 250];
                    $image_data = $this->upload_image($relative_path, $desired_filename, FALSE, $image_field, $resolution);
                    unset($data);
                    if ($image_data) {
                        $data['image_name'] = $image_data['fullsize_image_name'];
                        $data['image_url'] = $image_data['url_image'];
                        $this->user->update($data, $id);
                    }
                    unset($image_data);
                }
            }

            $this->response([
                'status' => 1,
                'message' => 'Usuario actualizado exitosamente',
                'response' => $id
            ], REST_Controller::HTTP_OK);
            return;
        } catch (Exception $e) {
            $this->response([
                'status' => 0,
                'message' => 'Error al actualizar el usuario: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }
    }

    public function roles_get()
    {
        try {
            $roles = $this->ion_auth->groups()->result();
        } catch (Exception $e) {
            $this->response([
                'status' => 0,
                'message' => 'Error al obtener los roles'
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }

        $this->response([
            'status' => 1,
            'message' => 'Success',
            'response' => $roles
        ], REST_Controller::HTTP_OK);
    }

    public function delete_post()
    {
        $this->load->model('admin/user');

        try {
            $id = $this->post('id');
            $result = $this->user->delete($id);
        } catch (Exception $e) {
            $this->response([
                'status' => 0,
                'message' => 'Error al eliminar el usuario'
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }

        $this->response([
            'status' => 1,
            'message' => 'Usuario eliminado exitosamente',
            'response' => $result
        ], REST_Controller::HTTP_OK);
    }
}
