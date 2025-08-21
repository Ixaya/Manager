<?php

// Path: application/libraries/Attachment_lib.php
// Library to manage attachments


defined('BASEPATH') or exit('No direct script access allowed');

class Attachment_lib
{
    /**
     * __get
     *
     * Enables the use of CI super-global without having to define an extra variable.
     *
     * @access	public
     * @param	$var
     * @return	mixed
     */
    public function __get($var)
    {
        return get_instance()->$var;  
    }

    protected $model = 'attachment';
    protected $model_path = 'attachment';

    protected $public_path = 'media/files';
    protected $private_path = 's3/media/files';//aws
    // protected $private_path = 'private/media/files';//local

    public function set_model($model_path)
    {
        $this->model_path = $model_path;

        if (strpos($model_path, '/') !== false) {
            $parts = explode('/', $model_path);
            $this->model =  end($parts);
        } else {
            $this->model = $model_path;
        }
    }

    public function get_by($model_name, $hash)
    {
        $this->load->model($this->model_path);
        return $this->{$this->model}->get_where(['model_hash' => $hash, 'model_name' => $model_name]);
    }

    public function update_by($data, $model_name, $hash)
    {
        return $this->{$this->model}->update_where($data, ['model_hash' => $hash, 'model_name' => $model_name]);
    }

    public function get_where($where)
    {
        $this->load->model($this->model_path);
        return $this->{$this->model}->get_where($where);
    }

    public function update_by_id($data, $attachment_id)
    {
        return $this->{$this->model}->update($data, $attachment_id);
    }

    public function upload_file($field_name, $module, $hash, $desired_filename = '', $return_data = false, $public_file = true, $extra_data = [])
    {
        $this->load->model($this->model_path);

        // Basic parameter validation
        if (!isset($field_name) || !isset($module) || !isset($hash)) {
            throw new InvalidArgumentException('Invalid parameters: field_name, module and hash are required.');
        }

        // Determine the upload path based on public/private
        $relative_path = $public_file ? "{$this->public_path}/$module/$hash/" : "{$this->private_path}/$module/$hash/";

        $this->load->library('ix_upload_lib');

        $error = null;
        $upload_result = $this->ix_upload_lib->upload_file(
            $relative_path,
            $desired_filename,
            $field_name,
            null, // upload_config
            true, // encrypt_name
            $error
        );

        /** @var string|null $error */
        if ($upload_result === false) {
            throw new RuntimeException('File upload failed: ' . ($error ?? 'Unknown error'));
        }

        // Prepare data for database insertion using upload_file results
        $data = array(
            'title' => $upload_result['client_name'], // Original filename from client
            'file_name' => $upload_result['file_name'],
            'full_path' => $upload_result['file_url'], // This should be the relative path + filename
            'type' => $upload_result['file_type'],
            'model_hash' => $hash,
            'model_name' => $module,
        );

        // Insert into database
        $attachment_id = $this->{$this->model}->insert(array_merge($data, $extra_data));

        // Return based on $return_data flag
        if ($return_data == false) {
            return $data['full_path'];
        } else {
            $data['attachment_id'] = isset($attachment_id) ? $attachment_id : null;
            return $data;
        }
    }

    public function put_file($data, $module, $hash, $desired_filename = '', $return_data = false, $public_file = true, $extra_data = [])
    {
        $this->load->model($this->model_path);

        // Basic parameter validation
        if (!isset($data) || !isset($module) || !isset($hash)) {
            throw new InvalidArgumentException('Invalid parameters: field_name, module and hash are required.');
        }

        // Determine the upload path based on public/private
        $relative_path = $public_file ? "{$this->public_path}/$module/$hash/" : "{$this->private_path}/$module/$hash/";

        $this->load->library('ix_upload_lib');

        $error = null;
        $upload_result = $this->ix_upload_lib->put_file(
            $relative_path,
            $desired_filename,
            $data,
            $error
        );

        /** @var string|null $error */
        if ($upload_result === false || $error !== null) {
            throw new RuntimeException('File upload failed: ' . ($error ?? 'Unknown error'));
        }

        // Prepare data for database insertion using upload_file results
        $data = array(
            'title' => $upload_result['client_name'], // Original filename from client
            'file_name' => $upload_result['file_name'],
            'full_path' => $upload_result['file_url'], // This should be the relative path + filename
            'type' => $upload_result['file_type'],
            'model_hash' => $hash,
            'model_name' => $module,
        );

        // Insert into database
        $attachment_id = $this->{$this->model}->insert(array_merge($data, $extra_data));

        // Return based on $return_data flag
        if ($return_data == false) {
            return $data['full_path'];
        } else {
            $data['attachment_id'] = isset($attachment_id) ? $attachment_id : null;
            return $data;
        }
    }

    // get hash 
    public function get_hash()
    {
        $this->load->model($this->model_path);    
    
        return $this->{$this->model}->get_hash(13);
    }

    public function get_unique_hash()
    {
        $this->load->model($this->model_path);

        return $this->{$this->model}->get_unique_hash(13);
    }
}