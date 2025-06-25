<?php
//  Created by Kevin Martinez on 29/08/24.
//  Copyright © 2024 Ixaya. All rights reserved.
//

class Profile extends IX_Rest_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->group_methods['*']['level'] = LEVEL_ADMIN;
        $this->load->library('ion_auth');
    }

    public function index_get()
    {
        $this->load->model('user');

        $response = [];
        $profile = [];

        try {
            $result = $this->user->get($this->user_id);

            if (!empty($result)) {
                $profile = [
                    'id' => $result->id,
                    'email' => $result->email,
                    'first_name' => $result->first_name,
                    'last_name' => $result->last_name,
                    'username' => $result->username,
                    'company' => $result->company,
                    'phone' => $result->phone,
                    'status' => $result->active,
                    'user_group' => $this->ion_auth->get_users_groups($this->user_id)->row(),
                    'ip_address' => $result->ip_address,
                    'image_url' => $result->image_url ? 'https://' . ltrim(base_url($result->image_url), '/') : null,
                    'last_update' => $result->last_update,
                ];
            }

            $response['profile'] = $profile;
            $this->response([
                'status' => 1,
                'message' => 'Perfil recuperado con éxito',
                'response' => $response
            ], REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->response([
                'status' => 0,
                'message' => 'Error al recuperar el perfil: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function edit_post()
    {
        $this->load->model('user');

        $response = [];
        $profile = [];

        $data = [
            'first_name' => $this->post('first_name'),
            'last_name' => $this->post('last_name'),
            'username' => $this->post('username'),
            'company' => $this->post('company'),
            'phone' => $this->post('phone'),
            'active' => $this->post('status'),
            'rpassword' => $this->post('rpass'),
        ];

        $newPassword = $this->post('password');

        if (!empty($newPassword)) {
            // Si se usa ion_auth->update no es necesario encriptar la contraseña aquí, ya que se encriptará automáticamente.
            $data['password'] = $newPassword;
        }

        try {
            unset($data['rpassword']);
            if ($this->post('status') == 1) {
                $this->ion_auth->activate($this->user_id);
            } else {
                $this->ion_auth->deactivate($this->user_id);
            }

            $this->ion_auth->update($this->user_id, $data);

            $relative_path = "media/user_profile/$this->user_id/";
            $image_fields = ['image'];

            if (!empty($_FILES['image']['name'])) {
                foreach ($image_fields as &$image_field) {
                    $desired_filename = str_replace("_filename", "", $image_field);
                    $resolution = [250, 250];
                    $image_data = $this->upload_image($relative_path, $desired_filename, FALSE, $image_field, $resolution);
                    unset($data);
                    if ($image_data) {
                        $data['image_name'] = $image_data['fullsize_image_name'];
                        $data['image_url'] = $image_data['url_image'];
                        $this->user->update($data, $this->user_id);
                    }
                    unset($image_data);
                }
            }

            $profile = $this->user->get($this->user_id);
            $user_groups = $this->ion_auth->get_users_groups($this->user_id)->row();
            $response['profile'] = [
                'id' => $profile->id,
                'email' => $profile->email,
                'username' => $profile->username,
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'full_name' => $profile->first_name . ' ' . $profile->last_name,
                'image' => [
                    'url' => $profile->image_url ? 'https://' . ltrim(base_url($profile->image_url), '/') : null,
                    'name' => $profile->image_name
                ],
                'user_groups' => [
                    $user_groups->name
                ]
            ];

            $this->response([
                'status' => 1,
                'message' => 'Perfil actualizado con éxito',
                'response' => $response
            ], REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->response([
                'status' => 0,
                'message' => 'Error al actualizar el perfil: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
