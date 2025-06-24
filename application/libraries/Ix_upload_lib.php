<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ix_upload_lib
{
	/**
	 * Whether to show flashdata in the response
	 *
	 * @var bool
	 */
	public $show_flashdata = FALSE;

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

	public function split_files($temp_name, $field_name, $index)
	{
		if (empty($_FILES[$field_name]['name'][$index])){
			return;
		}

		$files = $_FILES[$field_name];

		$_FILES[$temp_name]['name'] = $files['name'][$index];
		$_FILES[$temp_name]['type'] = $files['type'][$index];
		$_FILES[$temp_name]['tmp_name'] = $files['tmp_name'][$index];
		$_FILES[$temp_name]['error'] = $files['error'][$index];
		$_FILES[$temp_name]['size'] = $files['size'][$index];
	}

	/**
	 * Uploads a file and returns an array containing file details.
	 *
	 * @param string $relative_path The destination directory for the uploaded file.
	 * @param string|null $desired_file_name Optional desired filename. If null, a name is generated.
	 * @param string $field_name The form field name of the file input.
	 * @param array|null $upload_config Optional configuration settings for the upload.
	 * @param bool $encrypt_name Whether to encrypt the file name.
	 * @param null &$error Reference variable to store an error state.
	 * @return array|bool A two-dimensional array with file details.
	 * @throws RuntimeException If the upload fails.
	 */
	public function upload_file($relative_path, $desired_file_name = NULL, $field_name = 'userfile', $upload_config = NULL, $encrypt_name = TRUE, &$error = NULL)
	{
		$validate_result = $this->validate_files_field($field_name);
		if ($validate_result === null) {
			$error = $validate_result;
			return false;
		}

		if (strpos($relative_path, 's3/') !== false) {
			return $this->upload_file_s3($relative_path, $desired_file_name, $field_name, $upload_config, $encrypt_name, $error);
		} else {
			return $this->upload_file_local($relative_path, $desired_file_name, $field_name, $upload_config, $encrypt_name, $error);
		}
	}
	public function upload_file_local($relative_path, $desired_file_name = NULL, $field_name = 'userfile', $upload_config = NULL, $encrypt_name = TRUE, &$error = NULL)
	{
		try {
			$file_path = mngr_file_path($relative_path);
			if (!file_exists($file_path))
				mkdir($file_path, 0755, true);

			if ($upload_config == NULL) {
				$config['allowed_types']		= '*'; //'gif|jpg|png|jpeg|pdf|doc|docx|dwg|dxf';
				$config['max_size']			 		= 50000; //10MB (PHP Max in this config)
				$config['max_width']				= 0; // no size restriction
				$config['max_height']				= 0; // no size restriction
			} else {
				$config = $upload_config;
			}

			$config['upload_path']		  = $file_path;
			$config['remove_spaces']  = true;
			$config['detect_mime']   = true;

			if ($desired_file_name) {
				$config['file_name'] = $desired_file_name;
				$config['overwrite'] = true;
			} else if ($encrypt_name !== TRUE) {
				$config['encrypt_name'] = true;
			}

			//initialize in second line in case you want to do multiple uploads on same instance
			$this->load->library('upload');
			$this->upload->initialize($config);

			if ($this->upload->do_upload($field_name)) {
				if ($this->show_flashdata == TRUE){
					$this->session->set_flashdata('message', 'Archivo agregado correctamente');
					$this->session->set_flashdata('message_kind', 'success');
				}

				$upload_data = $this->upload->data();

				$file_type = $upload_data['file_type'];

				$v = '';
				if ($desired_file_name) {
					$v = dechex(time());
					$v = "?v=$v";
				}

				if (strpos($relative_path, '/') !== 0) {
					$relative_path = "/$relative_path";
				}

				$return_data['file_type'] = $file_type;
				$return_data['file_name'] = $upload_data['file_name'] . $v;
				$return_data['file_url']  = $relative_path . $upload_data['file_name'] . $v;
				$return_data['file_path'] = $upload_data['full_path'];
				$return_data['client_name'] = $upload_data['client_name'];

				//Retro compativility keys
				// $return_data['fullsize_image_name'] = $return_data['fullsize_name'];
				// $return_data['url_file'] = $return_data['file_url'];

				return $return_data;
			} else {
				log_message('error', "Error uploading($relative_path): " . json_encode($this->upload->display_errors()));

				if ($this->show_flashdata == TRUE) {
					$this->session->set_flashdata('message', $this->upload->display_errors());
					$this->session->set_flashdata('message_kind', 'error');
				} else {
					$error = $this->upload->display_errors(); // end($this->upload->error_msg);
				}
			}
		} catch (Exception $e) {
			log_message('error', "Error uploading($relative_path): " . $e->getMessage());

			if ($this->show_flashdata == TRUE) {
				$this->session->set_flashdata('message', 'Error al subir archivo');
				$this->session->set_flashdata('message_kind', 'error');
			} else {
				$error = 'Error al subir archivo';
			}
		}

		return false;
	}
	private function upload_file_s3($relative_path, $desired_file_name = NULL, $field_name = 'userfile', $upload_config = NULL, $encrypt_name = TRUE, &$error = NULL)
	{
		try {
			$v = '';
			if ($desired_file_name) {
				$v = dechex(time());
				$v = "?v=$v";
			}
			
			if (empty($desired_file_name)) {
				$desired_file_name = mngr_generate_hash($lenght = 32);
			}

			$file_ext = '';
			$file_type = '';
			$file_name = '';

			$original_file_path = mngr_get_temp_upload_path($field_name, $file_ext, $file_type, $file_name);

			$s3_file_name = "{$desired_file_name}.{$file_ext}";
			$s3_file_path = "{$relative_path}{$s3_file_name}";

			mngr_clean_file_s3_path($s3_file_path);

			$this->load->library('amazon_aws');

			$image_url = $this->amazon_aws->upload_file($original_file_path, $s3_file_path);

			if ($image_url) {
				if ($this->show_flashdata == TRUE) {
					$this->session->set_flashdata('message', 'Archivo agregado correctamente');
					$this->session->set_flashdata('message_kind', 'success');
				}

				if (strpos($relative_path, '/') !== 0) {
					$relative_path = "/$relative_path";
				}

				$return_data['file_type'] = $file_type;
				$return_data['file_name'] = $s3_file_name . $v;
				$return_data['file_url']  = $relative_path . $s3_file_name . $v;
				$return_data['file_path'] = $image_url;
				$return_data['client_name'] = $file_name;

				//Retro compativility keys
				// $return_data['fullsize_image_name'] = $return_data['fullsize_name'];
				// $return_data['url_file'] = $return_data['file_url'];

				return $return_data;
			} else {
				log_message('error', "Error uploading($relative_path): " . json_encode($this->upload->display_errors()));

				if ($this->show_flashdata == TRUE) {
					$this->session->set_flashdata('message', $this->upload->display_errors());
					$this->session->set_flashdata('message_kind', 'error');
				} else {
					$error = $this->upload->display_errors(); // end($this->upload->error_msg);
				}
			}
		} catch (Exception $e) {
			log_message('error', "Error uploading($relative_path): " . $e->getMessage());

			if ($this->show_flashdata == TRUE) {
				$this->session->set_flashdata('message', 'Error al subir archivo');
				$this->session->set_flashdata('message_kind', 'error');
			} else {
				$error = 'Error al subir archivo';
			}
		}

		return false;
	}

	public function upload_image($relative_path, $desired_file_name = NULL, $delete_original = TRUE, $field_name = 'userfile', $resolution = [200, 200], $preserve_type = FALSE, $upload_config = NULL, &$error = NULL)
	{
		$validate_result = $this->validate_files_field($field_name);
		if ($validate_result === null) {
			$error = $validate_result;
			return false;
		}

		if (strpos($relative_path, 's3/') === 0) {
			return $this->upload_image_s3($relative_path, $desired_file_name, $delete_original, $field_name, $resolution, $preserve_type, $upload_config, $error);
		} else {
			return $this->upload_image_local($relative_path, $desired_file_name, $delete_original, $field_name, $resolution, $preserve_type, $upload_config, $error);
		}
	}
	public function upload_image_local($relative_path, $desired_file_name = NULL, $delete_original = TRUE, $field_name = 'userfile', $resolution = [200, 200], $preserve_type = FALSE, $upload_config = NULL, &$error = NULL)
	{
		try {
			$file_path = mngr_file_path($relative_path);

			if (!file_exists($file_path))
				mkdir($file_path, 0755, true);

			if ($upload_config == NULL) {
				$config['allowed_types']		= 'gif|jpg|png|jpeg|svg|pdf';
				$config['max_size']					= 10048; //2MB (PHP Max in this config)
				$config['max_width']				= 0; // no size restriction
				$config['max_height']				= 0; // no size restriction
			} else {
				$config = $upload_config;
			}

			$config['upload_path']		  = $file_path;
			$config['remove_spaces']  = true;
			$config['detect_mime']   = true;

			if ($desired_file_name) {
				$config['file_name'] = $desired_file_name;
				$config['overwrite'] = true;
			} else {
				$config['encrypt_name'] = true;
			}

			//initialize in second line in case you want to do multiple uploads on same instance
			$this->load->library('upload');
			$this->upload->initialize($config);
			if ($this->upload->do_upload($field_name)) {
				if ($this->show_flashdata == TRUE) {
					$this->session->set_flashdata('message', 'Imagen agregada correctamente');
					$this->session->set_flashdata('message_kind', 'success');
				}

				$upload_data = $this->upload->data();

				$original_file_path = $upload_data['full_path'];
				$file_ext = $upload_data['file_ext'];
				$file_type = $upload_data['file_type'];

				if (!$preserve_type) {
					$file_ext = '.jpg';
					$file_type = 'image/jpeg';
				}

				$new_file_name = $upload_data['raw_name'] . $file_ext;
				$new_file_path = $file_path . $new_file_name;


				if (!$preserve_type) {
					//advanced convert to JPG that sets background to white
					$input_image = imagecreatefromstring(file_get_contents($original_file_path));
					list($width, $height) = getimagesize($original_file_path);
					$output_image = imagecreatetruecolor($width, $height);
					$white = imagecolorallocate($output_image,  255, 255, 255);
					imagefilledrectangle($output_image, 0, 0, $width, $height, $white);
					imagecopy($output_image, $input_image, 0, 0, 0, 0, $width, $height);
					imagejpeg($output_image, $new_file_path);
				}

				$new_file_thumb = '';
				if ($resolution !== FALSE && ($file_type == 'image/jpeg' || $file_type == 'image/png')) {
					//create thumbnail
					$new_file_thumb = $upload_data['raw_name'] . '_thumb' . $file_ext;
					$img_config['image_library']  = 'gd2';
					$img_config['source_image']   = $new_file_path;
					$img_config['create_thumb']   = TRUE;
					$img_config['maintain_ratio'] = TRUE;
					$img_config['width']		  = $resolution[0];
					$img_config['height']		 = $resolution[1];
					$this->load->library('image_lib', $img_config);
					$this->image_lib->resize();
				}


				//return thumbnail name and image_name
				$v = '';
				if ($desired_file_name) {
					$v = dechex(time());
					$v = "?v=$v";
				}

				if (strpos($relative_path, '/') !== 0) {
					$relative_path = "/$relative_path";
				}

				$return_data['fullsize_name'] = $new_file_name . $v;
				$return_data['fullsize_type'] = $file_type;
				$return_data['fullsize_url']  = $relative_path . $new_file_name . $v;

				$return_data['original_link'] = $upload_data['full_path'];
				$return_data['original_name'] = $upload_data['file_name'] . $v;

				$return_data['thumb_name']	  = $new_file_thumb . $v;
				$return_data['thumb_url']     = $relative_path . $new_file_thumb . $v;

				//Retro compativility keys
				$return_data['thumb_image_name'] = $return_data['thumb_name'];
				$return_data['fullsize_image_name'] = $return_data['fullsize_name'];
				$return_data['url_image'] = $return_data['fullsize_url'];

				//delete original image in case its not a jpg
				if ($delete_original && !$preserve_type && $upload_data['file_ext'] != '.jpg') {
					//delete
					unlink($upload_data['full_path']);
				}
				return $return_data;
			} else {
				log_message('error', "Error uploading($relative_path): " . json_encode($this->upload->display_errors()));

				if ($this->show_flashdata == TRUE) {
					$this->session->set_flashdata('message', $this->upload->display_errors()); //'Error al subir imagen');
					$this->session->set_flashdata('message_kind', 'error');
				} else {
					$error = $this->upload->display_errors(); // end($this->upload->error_msg);
				}
			}
		} catch (Exception $e) {
			log_message('error', "Error uploading($relative_path): " . $e->getMessage());

			if ($this->show_flashdata == TRUE) {
				$this->session->set_flashdata('message', 'Error al subir imagen');
				$this->session->set_flashdata('message_kind', 'error');	
			} else {
				$error = 'Error al subir imagen';
			}
		}
		return false;
	}
	private function upload_image_s3($relative_path, $desired_file_name = NULL, $delete_original = TRUE, $field_name = 'userfile', $resolution = [200, 200], $preserve_type = FALSE, $upload_config = NULL, &$error = NULL)
	{
		try {
			$this->load->library('amazon_aws');

			$s3_relative_path = $relative_path;
			mngr_clean_file_s3_path($s3_relative_path);

			if (empty($desired_file_name)) {
				$desired_file_name = mngr_generate_hash($lenght = 32);
			}

			$file_ext = '';
			$file_type = '';

			$s3_file_name = '';
			$s3_file_path = '';

			$s3_orig_file_name = '';
			$s3_orig_file_path = '';

			$s3_thumb_file_name = '';
			$s3_thumb_file_path = '';

			$original_file_path = mngr_get_temp_upload_path($field_name, $file_ext, $file_type);

			if (!$preserve_type && $file_type != 'image/jpeg') {
				if ($delete_original == false) {
					$s3_orig_file_name = "{$desired_file_name}_original.{$file_ext}";
					$s3_orig_file_path = "{$s3_relative_path}{$s3_orig_file_name}";

					$orig_image_url = $this->amazon_aws->upload_file($original_file_path, $s3_orig_file_path);
					if (empty($orig_image_url)) {
						$s3_orig_file_path = '';
					}
				}

				$file_ext = 'jpg';
				$file_type = 'image/jpeg';

				$base_file_path = "{$original_file_path}_optimized";
				$input_image = imagecreatefromstring(file_get_contents($original_file_path));
				list($width, $height) = getimagesize($original_file_path);
				$output_image = imagecreatetruecolor($width, $height);
				$white = imagecolorallocate($output_image,  255, 255, 255);
				imagefilledrectangle($output_image, 0, 0, $width, $height, $white);
				imagecopy($output_image, $input_image, 0, 0, 0, 0, $width, $height);
				imagejpeg($output_image, $base_file_path);
			} else {
				$base_file_path = $original_file_path;
			}

			$s3_file_name = "{$desired_file_name}.{$file_ext}";
			$s3_file_path = "{$s3_relative_path}{$s3_file_name}";

			$image_url = $this->amazon_aws->upload_file($base_file_path, $s3_file_path);
			if (empty($image_url)) {
				$s3_file_path = '';
			} else if ($this->show_flashdata == TRUE) {
				$this->session->set_flashdata('message', 'Imagen agregada correctamente');
				$this->session->set_flashdata('message_kind', 'success');
			}

			if ($resolution !== FALSE && ($file_type == 'image/jpeg' || $file_type == 'image/png')) {
				//create thumbnail

				$thumb_file_path = "{$base_file_path}_thumb";
				$img_config['image_library']  = 'gd2';
				$img_config['source_image']   = $base_file_path;
				$img_config['create_thumb']   = TRUE;
				$img_config['maintain_ratio'] = TRUE;
				$img_config['width']		  = $resolution[0];
				$img_config['height']		 = $resolution[1];
				$this->load->library('image_lib', $img_config);
				$this->image_lib->resize();

				$s3_thumb_file_name = "{$desired_file_name}_thumb.{$file_ext}";
				$s3_thumb_file_path = "{$s3_relative_path}{$s3_thumb_file_name}";

				$thumb_image_url = $this->amazon_aws->upload_file($thumb_file_path, $s3_thumb_file_path);
				if (empty($thumb_image_url)) {
					$s3_thumb_file_path = '';
				}
			}

			$v = '';
			if ($desired_file_name) {
				$v = dechex(time());
				$v = "?v=$v";
			}

			if (strpos($relative_path, '/') !== 0) {
				$relative_path = "/$relative_path";
			}

			$return_data['fullsize_type'] = $file_type;
			$return_data['fullsize_name'] = $s3_file_name . $v;
			$return_data['fullsize_url']  = $relative_path . $s3_file_name . $v;


			$return_data['original_name'] = $s3_orig_file_name . $v;
			$return_data['original_url'] = $relative_path . $s3_orig_file_name . $v;

			$return_data['thumb_name']	  = $s3_thumb_file_name . $v;
			$return_data['thumb_url']     = $relative_path . $s3_thumb_file_name . $v;

			//Retro compativility keys
			// $return_data['thumb_image_name'] = $return_data['thumb_name'];
			// $return_data['fullsize_image_name'] = $return_data['fullsize_name'];
			// $return_data['url_image'] = $return_data['fullsize_url'];

			return $return_data;
		} catch (Exception $e) {
			log_message('error', "Error uploading($relative_path): " . $e->getMessage());
			
			if ($this->show_flashdata == TRUE) {
				$this->session->set_flashdata('message', 'Error al subir imagen');
				$this->session->set_flashdata('message_kind', 'error');
			} else {
				$error = 'Error al subir imagen';
			}
		} finally { //Cleanup tmp files
			if (!empty($thumb_file_path) && file_exists($thumb_file_path)) {
				unlink($thumb_file_path);
			}

			if ((!empty($original_file_path) && !empty($base_file_path)) &&
				($base_file_path != $original_file_path) &&
				file_exists($base_file_path)
			) {
				unlink($base_file_path);
			}
		}
		return false;
	}

	public function display_image($file_path)
	{
		if (strpos($file_path, 's3/') !== false) {
			$this->display_image_s3($file_path);
		} else {
			$this->display_image_local($file_path);
		}
	}
	public function display_image_local($file_path)
	{
		if (file_exists($file_path)) {
			$filename = basename($file_path);
			$file_ext = pathinfo($file_path, PATHINFO_EXTENSION);

			header("Content-Type: image/{$file_ext}");
			header('Cache-Control: private');
			header("Content-Disposition: inline; filename='$filename'");

			readfile($file_path);
		} else {
			header('Content-Type: image/png');
			echo ("\x89\x50\x4e\x47\x0d\x0a\x1a\x0a\x00\x00\x00\x0d\x49\x48\x44\x52\x00\x00\x00\x01\x00\x00\x00\x01\x01\x03\x00\x00\x00\x25\xdb\x56\xca\x00\x00\x00\x03\x50\x4c\x54\x45\x00\x00\x00\xa7\x7a\x3d\xda\x00\x00\x00\x01\x74\x52\x4e\x53\x00\x40\xe6\xd8\x66\x00\x00\x00\x0a\x49\x44\x41\x54\x08\xd7\x63\x60\x00\x00\x00\x02\x00\x01\xe2\x21\xbc\x33\x00\x00\x00\x00\x49\x45\x4e\x44\xae\x42\x60\x82");
		}
	}
	public function display_image_s3($file_path)
	{
		$this->load->library('amazon_aws');

		mngr_clean_file_s3_path($file_path);
		$image_data = $this->amazon_aws->get_file($file_path);

		if ($image_data) {
			$file_name = basename($file_path);
			$file_ext = pathinfo($file_path, PATHINFO_EXTENSION);

			header("Content-Type: image/{$file_ext}");
			header('Cache-Control: private');
			header("Content-Disposition: inline; filename='$file_name'");

			echo ($image_data);
		} else {
			header('Content-Type: image/png');
			echo ("\x89\x50\x4e\x47\x0d\x0a\x1a\x0a\x00\x00\x00\x0d\x49\x48\x44\x52\x00\x00\x00\x01\x00\x00\x00\x01\x01\x03\x00\x00\x00\x25\xdb\x56\xca\x00\x00\x00\x03\x50\x4c\x54\x45\x00\x00\x00\xa7\x7a\x3d\xda\x00\x00\x00\x01\x74\x52\x4e\x53\x00\x40\xe6\xd8\x66\x00\x00\x00\x0a\x49\x44\x41\x54\x08\xd7\x63\x60\x00\x00\x00\x02\x00\x01\xe2\x21\xbc\x33\x00\x00\x00\x00\x49\x45\x4e\x44\xae\x42\x60\x82");
		}
	}

	public function get_file_base64($file_path, &$file_name = '', &$file_ext = '', &$file_mime = '')
	{
		if (empty($file_path)) {
			return null;
		}

		if (strpos($file_path, 's3/') !== false) {
			mngr_clean_file_s3_path($file_path);
			$this->load->library('amazon_aws');

			$file_mime = '';
			$file_data = $this->amazon_aws->get_file($file_path, $file_mime);

			if (empty($file_data)) {
				return null;
			}
		} else {
			$file_path = mngr_file_path($file_path);

			if (!file_exists($file_path)) {
				return null;
			}

			$file_data = file_get_contents($file_path);
			$file_mime = mime_content_type($file_path);
		}

		$file_name = basename($file_path);
		$file_ext = pathinfo($file_path, PATHINFO_EXTENSION);

		$base64 = base64_encode($file_data);
		return 'data:' . $file_mime . ';base64,' . $base64;
	}

	private function validate_files_field($field_name)
	{
		if (empty($_FILES[$field_name]['name']) || is_array($_FILES[$field_name]['name']) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
			return null;
		}

		switch ($_FILES[$field_name]) {
			case UPLOAD_ERR_OK:
				return null;
			case UPLOAD_ERR_NO_FILE:
				return'No file sent.';
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return'Exceeded filesize limit.';
			case UPLOAD_ERR_PARTIAL:
				return'The uploaded file was only partially uploaded.';
			case UPLOAD_ERR_CANT_WRITE:
				return'Failed to write file to disk.';
			case UPLOAD_ERR_EXTENSION:
				return'A PHP extension stopped the file upload.';
			default:
				return'Unknown error.';
		}
	}
}

