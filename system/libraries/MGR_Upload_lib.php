<?php

defined('BASEPATH') or exit('No direct script access allowed');

class MGR_Upload_lib
{
	/** Default `allowed_types` of upload_file() when no `$upload_config` is given. */
	protected const string DEFAULT_FILE_TYPES = 'gif|jpg|jpeg|png|webp|pdf|txt|csv|doc|docx|xls|xlsx|ppt|pptx|odt|ods';

	/** Default `allowed_types` of upload_image() when no `$upload_config` is given. */
	protected const string DEFAULT_IMAGE_TYPES = 'gif|jpg|png|jpeg';

	/** Extensions never stored under the web root, whatever the config. Keep in sync with the page-type location in docker/nginx/conf.d/app.conf. */
	protected const array PAGE_TYPES = ['html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'xml', 'xsl', 'js'];

	/** Detected types display_image() serves inline and the only ones upload_image() converts; any other type is sent as a download. */
	protected const array RASTER_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

	/**
	 * Whether to show flashdata in the response
	 *
	 * @var bool
	 */
	public bool $show_flashdata = false;

	/**
	 * __get
	 *
	 * Enables the use of CI super-global without having to define an extra variable.
	 *
	 * @access	public
	 * @param	$var
	 * @return	mixed
	 */
	public function __get(mixed $var): mixed
	{
		return get_instance()->$var;
	}

	public function split_files(string $temp_name, string $field_name, string|int $index): void
	{
		if (empty($_FILES[$field_name]['name'][$index])) {
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
	 * Uploads a file and returns an array with file details.
	 *
	 * Without `$upload_config` it accepts gif, jpg, jpeg, png, webp, pdf, txt, csv and the Office/OpenDocument
	 * document types. Page types (html, svg, xml, js…) are refused under the web root whatever the config.
	 *
	 * @param string      $relative_path    Destination storage URI (`media/…`, `private/…`, `s3/…`), built by the server, never request input; not validated.
	 * @param string|null $desired_file_name Name to store as, built by the server and never request input; null generates one. A name with `/`, `\`, `?`, NUL, a leading dot or characters outside mgr_is_allowed_file_name() is refused with `$error`.
	 * @param string      $field_name       The form field name of the file input.
	 * @param array|null  $upload_config    CI Upload settings; replaces the defaults entirely, so include `allowed_types` and `max_size`.
	 * @param bool        $encrypt_name     Ignored, kept for compatibility: a name is always generated unless `$desired_file_name` is given.
	 * @param string|null &$error           Reference variable to store an error message (null if no error).
	 *
	 *
	 * @return array|null Array with file details on success, null on failure.
	 *
	 */
	public function upload_file(string $relative_path, ?string $desired_file_name = null, string $field_name = 'userfile', ?array $upload_config = null, bool $encrypt_name = true, ?string &$error = null): ?array
	{
		$validate_result = $this->validate_files_field($field_name);
		if ($validate_result !== null) {
			$error = $validate_result;
			return null;
		}

		if (strpos($relative_path, 's3/') !== false) {
			return $this->upload_file_s3($relative_path, $desired_file_name, $field_name, $upload_config, $encrypt_name, $error);
		}

		return $this->upload_file_local($relative_path, $desired_file_name, $field_name, $upload_config, $encrypt_name, $error);
	}
	public function upload_file_local(string $relative_path, ?string $desired_file_name = null, string $field_name = 'userfile', ?array $upload_config = null, bool $encrypt_name = true, ?string &$error = null): ?array
	{
		if (!$this->is_desired_name_allowed($desired_file_name)) {
			return $this->refuse_desired_name($relative_path, $error);
		}

		$written_paths = [];

		try {
			$file_path = mgr_file_path($relative_path);
			if (!file_exists($file_path)) {
				mkdir($file_path, 0755, true);
			}

			if ($upload_config == null) {
				$config['allowed_types']		= static::DEFAULT_FILE_TYPES;
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
			} else {
				$config['encrypt_name'] = true;
			}

			//initialize in second line in case you want to do multiple uploads on same instance
			$this->load->library('upload');
			$this->upload->initialize($config);

			if ($this->upload->do_upload($field_name) && !$this->refuse_public_page_type($file_path)) {
				$written_paths[] = $this->upload->data('full_path');

				if ($this->show_flashdata == true) {
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

				if ($this->show_flashdata == true) {
					$this->session->set_flashdata('message', $this->upload->display_errors());
					$this->session->set_flashdata('message_kind', 'error');
				} else {
					$error = $this->upload->display_errors(); // end($this->upload->error_msg);
				}
			}
		} catch (Throwable $e) {
			$this->delete_written_files($written_paths);

			return $this->fail_upload($e, $relative_path, 'Error al subir archivo', $error);
		}

		return null;
	}
	protected function upload_file_s3(string $relative_path, ?string $desired_file_name = null, string $field_name = 'userfile', ?array $upload_config = null, bool $encrypt_name = true, ?string &$error = null): ?array
	{
		if (!$this->is_desired_name_allowed($desired_file_name)) {
			return $this->refuse_desired_name($relative_path, $error);
		}

		try {
			$v = '';
			if ($desired_file_name) {
				$v = dechex(time());
				$v = "?v=$v";
			}

			if (empty($desired_file_name)) {
				$desired_file_name = mgr_generate_hash($lenght = 32);
			}

			$file_ext = '';
			$file_name = '';

			$original_file_path = mgr_get_temp_upload_path(field_name: $field_name, file_extension: $file_ext, file_name: $file_name);
			$file_type = (string) mgr_detect_mime_from_file($original_file_path);

			$s3_file_name = "{$desired_file_name}.{$file_ext}";

			if (!$this->is_s3_file_allowed($s3_file_name, $file_type, $upload_config, static::DEFAULT_FILE_TYPES)) {
				return $this->refuse_s3_file($relative_path, $error);
			}

			$s3_file_path = "{$relative_path}{$s3_file_name}";

			mgr_clean_file_s3_path($s3_file_path);

			$this->load->library('amazon_aws_lib');

			$file_url = $this->amazon_aws_lib->upload_file($original_file_path, $s3_file_path);

			if ($file_url) {
				if ($this->show_flashdata == true) {
					$this->session->set_flashdata('message', 'Archivo agregado correctamente');
					$this->session->set_flashdata('message_kind', 'success');
				}

				if (strpos($relative_path, '/') !== 0) {
					$relative_path = "/$relative_path";
				}

				$return_data['file_type'] = $file_type;
				$return_data['file_name'] = $s3_file_name . $v;
				$return_data['file_url']  = $relative_path . $s3_file_name . $v;
				$return_data['file_path'] = $file_url;
				$return_data['client_name'] = $file_name;

				//Retro compativility keys
				// $return_data['fullsize_image_name'] = $return_data['fullsize_name'];
				// $return_data['url_file'] = $return_data['file_url'];

				return $return_data;
			}

			return $this->refuse_upload($relative_path, 'Error al subir archivo', $error);
		} catch (Throwable $e) {
			return $this->fail_upload($e, $relative_path, 'Error al subir archivo', $error);
		}
	}

	/**
	 * Stores generated content under a server-chosen name.
	 *
	 * `$relative_path` is a storage URI (`media/…`, `private/…`, `s3/…`) built by the server, never request input,
	 * and is not validated.
	 * `$file_name` is built by the server, never request input. A name with `/`, `\`, `?`, NUL, a leading dot or
	 * characters outside mgr_is_allowed_file_name() is refused with `$error`. An empty name generates
	 * `<hash>.<ext>`, the extension looked up from the detected content type.
	 *
	 * @return array|null File details on success, null on failure with the reason in `$error`.
	 */
	public function put_file(string $relative_path, string $file_name, string $data, ?string &$error = null): ?array
	{
		if (strpos($relative_path, 's3/') !== false) {
			return $this->put_file_s3($relative_path, $file_name, $data, $error);
		}

		return $this->put_file_local($relative_path, $file_name, $data, $error);
	}
	public function put_file_local(string $relative_path, string $file_name, string $data, ?string &$error = null): ?array
	{
		if (!$this->is_desired_name_allowed($file_name)) {
			return $this->refuse_desired_name($relative_path, $error);
		}

		$written_paths = [];

		try {
			$file_path = mgr_file_path($relative_path);

			if (!file_exists($file_path)) {
				mkdir($file_path, 0755, true);
			}

			$v = '';
			if ($file_name) {
				$v = dechex(time());
				$v = "?v=$v";
			}

			if (empty($file_name)) {
				$file_name = $this->generate_file_name(mgr_detect_mime_from_data($data));
			}

			file_put_contents($file_path . $file_name, $data);
			$written_paths[] = $file_path . $file_name;

			if (strpos($relative_path, '/') !== 0) {
				$relative_path = "/$relative_path";
			}

			$mime_type = mgr_detect_mime_from_file($file_path . $file_name);

			$return_data['file_type'] = $mime_type;
			$return_data['file_name'] = $file_name . $v;
			$return_data['file_url']  = $relative_path . $file_name . $v;
			$return_data['file_path'] = $file_path . $file_name;
			$return_data['client_name'] = $file_name;

			//Retro compativility keys
			// $return_data['fullsize_image_name'] = $return_data['fullsize_name'];
			// $return_data['url_file'] = $return_data['file_url'];

			return $return_data;
		} catch (Throwable $e) {
			$this->delete_written_files($written_paths);

			return $this->fail_upload($e, $relative_path, 'Error al subir archivo', $error);
		}
	}
	protected function put_file_s3(string $relative_path, string $file_name, string $data, ?string &$error = null): ?array
	{
		if (!$this->is_desired_name_allowed($file_name)) {
			return $this->refuse_desired_name($relative_path, $error);
		}

		try {
			$v = '';
			if ($file_name) {
				$v = dechex(time());
				$v = "?v=$v";
			}

			$content_type = mgr_detect_mime_from_data($data);

			if (empty($file_name)) {
				$file_name = $this->generate_file_name($content_type);
			}

			$s3_file_path = "{$relative_path}{$file_name}";

			mgr_clean_file_s3_path($s3_file_path);

			$this->load->library('amazon_aws_lib');

			$file_url = $this->amazon_aws_lib->upload_data($data, $s3_file_path, $content_type);

			if ($file_url) {
				if ($this->show_flashdata == true) {
					$this->session->set_flashdata('message', 'Archivo agregado correctamente');
					$this->session->set_flashdata('message_kind', 'success');
				}

				if (strpos($relative_path, '/') !== 0) {
					$relative_path = "/$relative_path";
				}

				$return_data['file_type'] = $content_type;
				$return_data['file_name'] = $file_name . $v;
				$return_data['file_url']  = $relative_path . $file_name . $v;
				$return_data['file_path'] = $file_url;
				$return_data['client_name'] = $file_name;

				//Retro compativility keys
				// $return_data['fullsize_image_name'] = $return_data['fullsize_name'];
				// $return_data['url_file'] = $return_data['file_url'];

				return $return_data;
			}

			return $this->refuse_upload($relative_path, 'Error al subir archivo', $error);
		} catch (Throwable $e) {
			return $this->fail_upload($e, $relative_path, 'Error al subir archivo', $error);
		}
	}

	/**
	 * Uploads an image, converting it to JPEG unless `$preserve_type`, with an optional thumbnail for `$resolution`.
	 *
	 * Without `$upload_config` it accepts gif, jpg, jpeg and png. A given `$upload_config` replaces the defaults
	 * entirely. Page types (html, svg, xml, js…) are refused under the web root whatever the config. Without
	 * `$preserve_type` only jpeg, png, gif, webp and avif content is converted; any other detected type is refused.
	 *
	 * `$relative_path` is a storage URI (`media/…`, `private/…`, `s3/…`) built by the server, never request input,
	 * and is not validated.
	 *
	 * `$desired_file_name` is built by the server, never request input; null generates one. A name with `/`, `\`,
	 * `?`, NUL, a leading dot or characters outside mgr_is_allowed_file_name() is refused with `$error`.
	 *
	 * @return array|null Image details on success, null on failure with the reason in `$error`.
	 */
	public function upload_image(string $relative_path, ?string $desired_file_name = null, bool $delete_original = true, string $field_name = 'userfile', ?array $resolution = null, bool $preserve_type = false, ?array $upload_config = null, ?string &$error = null): ?array
	{
		$validate_result = $this->validate_files_field($field_name);
		if ($validate_result !== null) {
			$error = $validate_result;
			return null;
		}

		if (strpos($relative_path, 's3/') === 0) {
			return $this->upload_image_s3($relative_path, $desired_file_name, $delete_original, $field_name, $resolution, $preserve_type, $upload_config, $error);
		}

		return $this->upload_image_local($relative_path, $desired_file_name, $delete_original, $field_name, $resolution, $preserve_type, $upload_config, $error);
	}
	protected function upload_image_local(string $relative_path, ?string $desired_file_name = null, bool $delete_original = true, string $field_name = 'userfile', ?array $resolution = null, bool $preserve_type = false, ?array $upload_config = null, ?string &$error = null): ?array
	{
		if (!$this->is_desired_name_allowed($desired_file_name)) {
			return $this->refuse_desired_name($relative_path, $error);
		}

		$written_paths = [];

		try {
			$file_path = mgr_file_path($relative_path);

			if (!file_exists($file_path)) {
				mkdir($file_path, 0755, true);
			}

			if ($upload_config == null) {
				$config['allowed_types']		= static::DEFAULT_IMAGE_TYPES;
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
			if ($this->upload->do_upload($field_name) && !$this->refuse_public_page_type($file_path) && !$this->refuse_unconvertible_image($preserve_type)) {
				$written_paths[] = $this->upload->data('full_path');

				if ($this->show_flashdata == true) {
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
					$white = imagecolorallocate($output_image, 255, 255, 255);
					imagefilledrectangle($output_image, 0, 0, $width, $height, $white);
					imagecopy($output_image, $input_image, 0, 0, 0, 0, $width, $height);
					imagejpeg($output_image, $new_file_path);
					$written_paths[] = $new_file_path;
				}

				$new_file_thumb = '';
				if ($resolution !== null && ($file_type == 'image/jpeg' || $file_type == 'image/png')) {
					$image_info = getimagesize($new_file_path);
					$original_width = $image_info[0];
					$original_height = $image_info[1];

					// Only resize if image is larger than desired resolution
					if ($original_width > $resolution[0] || $original_height > $resolution[1]) {
						// Create thumbnail
						$new_file_thumb = $upload_data['raw_name'] . '_thumb' . $file_ext;
						$img_config['image_library'] = 'gd2';
						$img_config['source_image'] = $new_file_path;
						$img_config['create_thumb'] = true;
						$img_config['maintain_ratio'] = true;
						$img_config['width'] = $resolution[0];
						$img_config['height'] = $resolution[1];
						$this->load->library('image_lib', $img_config);
						if ($this->image_lib->resize()) {
							$written_paths[] = $file_path . $new_file_thumb;
						}
					} else {
						// Image is already smaller, just use the original
						$new_file_thumb = $upload_data['raw_name'] . $file_ext;
					}
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
				// $return_data['thumb_image_name'] = $return_data['thumb_name'];
				// $return_data['fullsize_image_name'] = $return_data['fullsize_name'];
				// $return_data['url_image'] = $return_data['fullsize_url'];

				//delete original image in case its not a jpg
				if ($delete_original && !$preserve_type && $upload_data['file_ext'] != '.jpg') {
					//delete
					unlink($upload_data['full_path']);
				}
				return $return_data;
			} else {
				log_message('error', "Error uploading($relative_path): " . json_encode($this->upload->display_errors()));

				if ($this->show_flashdata == true) {
					$this->session->set_flashdata('message', $this->upload->display_errors()); //'Error al subir imagen');
					$this->session->set_flashdata('message_kind', 'error');
				} else {
					$error = $this->upload->display_errors(); // end($this->upload->error_msg);
				}
			}
		} catch (Throwable $e) {
			$this->delete_written_files($written_paths);

			return $this->fail_upload($e, $relative_path, 'Error al subir imagen', $error);
		}

		return null;
	}
	protected function upload_image_s3(string $relative_path, ?string $desired_file_name = null, bool $delete_original = true, string $field_name = 'userfile', ?array $resolution = null, bool $preserve_type = false, ?array $upload_config = null, ?string &$error = null): ?array
	{
		if (!$this->is_desired_name_allowed($desired_file_name)) {
			return $this->refuse_desired_name($relative_path, $error);
		}

		try {
			$this->load->library('amazon_aws_lib');

			$s3_relative_path = $relative_path;
			mgr_clean_file_s3_path($s3_relative_path);

			if (empty($desired_file_name)) {
				$desired_file_name = mgr_generate_hash($lenght = 32);
			}

			$file_ext = '';

			$s3_file_name = '';
			$s3_file_path = '';

			$s3_orig_file_name = '';
			$s3_orig_file_path = '';

			$s3_thumb_file_name = '';
			$s3_thumb_file_path = '';

			$original_file_path = mgr_get_temp_upload_path(field_name: $field_name, file_extension: $file_ext);
			$file_type = (string) mgr_detect_mime_from_file($original_file_path);

			if (!$this->is_s3_file_allowed("{$desired_file_name}.{$file_ext}", $file_type, $upload_config, static::DEFAULT_IMAGE_TYPES)) {
				return $this->refuse_s3_file($relative_path, $error);
			}

			if (!$preserve_type && $file_type != 'image/jpeg') {
				if (!in_array($file_type, self::RASTER_TYPES, true)) {
					return $this->refuse_s3_file($relative_path, $error);
				}

				$base_file_path = "{$original_file_path}_optimized";
				$input_image = imagecreatefromstring(file_get_contents($original_file_path));
				list($width, $height) = getimagesize($original_file_path);
				$output_image = imagecreatetruecolor($width, $height);
				$white = imagecolorallocate($output_image, 255, 255, 255);
				imagefilledrectangle($output_image, 0, 0, $width, $height, $white);
				imagecopy($output_image, $input_image, 0, 0, 0, 0, $width, $height);
				imagejpeg($output_image, $base_file_path);

				// After the conversion, so a failed one leaves no `_original` object behind.
				if ($delete_original == false) {
					$s3_orig_file_name = "{$desired_file_name}_original.{$file_ext}";
					$s3_orig_file_path = "{$s3_relative_path}{$s3_orig_file_name}";

					$orig_image_url = $this->amazon_aws_lib->upload_file($original_file_path, $s3_orig_file_path);
					if (empty($orig_image_url)) {
						$s3_orig_file_path = '';
					}
				}

				$file_ext = 'jpg';
				$file_type = 'image/jpeg';
			} else {
				$base_file_path = $original_file_path;
			}

			$s3_file_name = "{$desired_file_name}.{$file_ext}";
			$s3_file_path = "{$s3_relative_path}{$s3_file_name}";

			$image_url = $this->amazon_aws_lib->upload_file($base_file_path, $s3_file_path);
			if (empty($image_url)) {
				$s3_file_path = '';
			} elseif ($this->show_flashdata == true) {
				$this->session->set_flashdata('message', 'Imagen agregada correctamente');
				$this->session->set_flashdata('message_kind', 'success');
			}

			if ($resolution !== null && ($file_type == 'image/jpeg' || $file_type == 'image/png')) {
				$image_info = getimagesize($base_file_path);
				$original_width = $image_info[0];
				$original_height = $image_info[1];

				// Only resize if image is larger than desired resolution
				if ($original_width > $resolution[0] || $original_height > $resolution[1]) {
					// Create thumbnail
					$thumb_file_path = "{$base_file_path}_thumb";
					$img_config['image_library']  = 'gd2';
					$img_config['source_image']   = $base_file_path;
					$img_config['create_thumb']   = true;
					$img_config['maintain_ratio'] = true;
					$img_config['width']		  = $resolution[0];
					$img_config['height']		 = $resolution[1];
					$this->load->library('image_lib', $img_config);
					$this->image_lib->resize();

					$s3_thumb_file_name = "{$desired_file_name}_thumb.{$file_ext}";
					$s3_thumb_file_path = "{$s3_relative_path}{$s3_thumb_file_name}";

					$thumb_image_url = $this->amazon_aws_lib->upload_file($thumb_file_path, $s3_thumb_file_path);
					if (empty($thumb_image_url)) {
						$s3_thumb_file_path = '';
					}

					// Clean up local thumbnail file after S3 upload
					if (file_exists($thumb_file_path)) {
						unlink($thumb_file_path);
					}
				} else {
					$s3_thumb_file_name = $s3_file_name;
					$s3_thumb_file_path = "{$s3_relative_path}{$s3_thumb_file_name}";
				}
			}

			//Clean up optimized image
			if ($base_file_path != $original_file_path && file_exists($base_file_path)) {
				unlink($base_file_path);
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
		} catch (Throwable $e) {
			return $this->fail_upload($e, $relative_path, 'Error al subir imagen', $error);
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
	}

	/**
	 * Serves a stored file: raster images inline, any other type as a download.
	 *
	 * `$file_path` is a storage URI (`media/…`, `private/…`, `s3/…`) built by the server, never request input.
	 * A local URI with a `..` segment is refused and answered with the empty image.
	 */
	public function display_image(?string $file_path): void
	{
		if (empty($file_path)) {
			$this->display_empty_image();
			return;
		}

		if (strpos($file_path, 's3/') !== false) {
			$this->display_image_s3($file_path);
			return;
		}

		$this->display_image_local($file_path);
	}
	/** Serves a local stored file; same contract as display_image(). */
	public function display_image_local(string $file_path): void
	{
		if (!mgr_is_allowed_file_path($file_path)) {
			log_message('error', __METHOD__ . ': refused path ' . json_encode($file_path, JSON_INVALID_UTF8_SUBSTITUTE));
			$this->display_empty_image();
			return;
		}

		$file_path = mgr_file_path($file_path);

		if (file_exists($file_path)) {
			$this->send_file_headers(file_name: basename($file_path), detected_type: mgr_detect_mime_from_file($file_path));

			readfile($file_path);
		} else {
			$this->display_empty_image();
		}
	}
	public function display_image_s3(string $file_path): void
	{
		$this->load->library('amazon_aws_lib');

		mgr_clean_file_s3_path($file_path);
		$image_data = $this->amazon_aws_lib->get_file($file_path);

		if ($image_data) {
			$this->send_file_headers(file_name: basename($file_path), detected_type: mgr_detect_mime_from_data($image_data));

			echo($image_data);
		} else {
			$this->display_empty_image();
		}
	}

	/** Raster types go out with their detected type inline; anything else as an octet-stream attachment. */
	protected function send_file_headers(string $file_name, string|false $detected_type): void
	{
		$is_raster = in_array($detected_type, self::RASTER_TYPES, strict: true);
		$ascii_name = preg_replace('/[^\x20-\x7e]|["\\\\%]/', '_', $file_name);
		$disposition = $is_raster ? 'inline' : 'attachment';

		header('Content-Type: ' . ($is_raster ? $detected_type : 'application/octet-stream'));
		header('Cache-Control: private');
		header("Content-Disposition: {$disposition}; filename=\"{$ascii_name}\"; filename*=UTF-8''" . rawurlencode($file_name));
	}

	protected function display_empty_image(): void
	{
		header('Content-Type: image/png');
		echo("\x89\x50\x4e\x47\x0d\x0a\x1a\x0a\x00\x00\x00\x0d\x49\x48\x44\x52\x00\x00\x00\x01\x00\x00\x00\x01\x01\x03\x00\x00\x00\x25\xdb\x56\xca\x00\x00\x00\x03\x50\x4c\x54\x45\x00\x00\x00\xa7\x7a\x3d\xda\x00\x00\x00\x01\x74\x52\x4e\x53\x00\x40\xe6\xd8\x66\x00\x00\x00\x0a\x49\x44\x41\x54\x08\xd7\x63\x60\x00\x00\x00\x02\x00\x01\xe2\x21\xbc\x33\x00\x00\x00\x00\x49\x45\x4e\x44\xae\x42\x60\x82");
	}

	/**
	 * Returns a stored file as a `data:` URI, or null when it is missing or unreadable.
	 *
	 * `$file_path` is a storage URI (`media/…`, `private/…`, `s3/…`) built by the server, never request input.
	 * A local URI with a `..` segment (mgr_is_allowed_file_path()) is refused, logged, and returns null.
	 */
	public function get_file_base64(?string $file_path, string &$file_name = '', string|array &$file_ext = '', string &$file_mime = ''): ?string
	{
		if (empty($file_path)) {
			return null;
		}

		if (strpos($file_path, 's3/') !== false) {
			mgr_clean_file_s3_path($file_path);
			$this->load->library('amazon_aws_lib');

			$file_mime = '';
			$file_data = $this->amazon_aws_lib->get_file($file_path, $file_mime);

			if (empty($file_data)) {
				return null;
			}
		} else {
			if (!mgr_is_allowed_file_path($file_path)) {
				log_message('error', __METHOD__ . ': refused path ' . json_encode($file_path, JSON_INVALID_UTF8_SUBSTITUTE));
				return null;
			}

			$file_path = mgr_file_path($file_path);

			if (!file_exists($file_path)) {
				return null;
			}

			$file_data = file_get_contents($file_path);
			if ($file_data === false) {
				return null;
			}

			$file_mime = mime_content_type($file_path) ?: '';
		}

		$file_name = basename($file_path);
		$file_ext = pathinfo($file_path, PATHINFO_EXTENSION);

		$base64 = base64_encode($file_data);
		return 'data:' . $file_mime . ';base64,' . $base64;
	}

	protected function is_page_type(string $file_name): bool
	{
		return in_array(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)), self::PAGE_TYPES, true);
	}

	/**
	 * Deletes the file CI just stored and queues CI's filetype error when it is a page type under the web root.
	 *
	 * @param string $file_path Upload directory as requested; CI resolves symlinks in its own copy.
	 */
	protected function refuse_public_page_type(string $file_path): bool
	{
		if (!str_starts_with($file_path, FCPATH) || !$this->is_page_type($this->upload->data('file_name'))) {
			return false;
		}

		unlink($this->upload->data('full_path'));
		$this->upload->set_error('upload_invalid_filetype', 'debug');

		return true;
	}

	/** Deletes the file CI just stored and queues CI's filetype error when the conversion would hand GD a type outside RASTER_TYPES. */
	protected function refuse_unconvertible_image(bool $preserve_type): bool
	{
		if ($preserve_type || in_array($this->upload->data('file_type'), self::RASTER_TYPES, true)) {
			return false;
		}

		unlink($this->upload->data('full_path'));
		$this->upload->set_error('upload_invalid_filetype', 'debug');

		return true;
	}

	/**
	 * CI Upload's rule for the S3 paths, which never reach CI: extension on the allowed list and the detected type
	 * listed for it. Only `allowed_types` of `$upload_config` is honored. A page type, by extension or by detected
	 * type, is refused whatever the list.
	 */
	protected function is_s3_file_allowed(string $s3_file_name, string $detected_type, ?array $upload_config, string $default_types): bool
	{
		if ($this->is_page_type($s3_file_name) || $this->is_page_mime($detected_type)) {
			return false;
		}

		$allowed_types = $upload_config == null ? $default_types : ($upload_config['allowed_types'] ?? '');
		if ($allowed_types === '*') {
			return true;
		}

		$allowed_types = is_array($allowed_types) ? $allowed_types : explode('|', $allowed_types);
		$extension = strtolower(pathinfo($s3_file_name, PATHINFO_EXTENSION));
		$allowed_mimes = (array) (mgr_mimes_config()[$extension] ?? []);

		return in_array($extension, $allowed_types, true) && in_array($detected_type, $allowed_mimes, true);
	}

	/** True when a page-type extension is listed under this type in mimes.php; text/plain is listed under several and excluded. */
	protected function is_page_mime(string $detected_type): bool
	{
		if ($detected_type === 'text/plain') {
			return false;
		}

		$mimes = mgr_mimes_config();
		foreach (self::PAGE_TYPES as $extension) {
			if (in_array($detected_type, (array) ($mimes[$extension] ?? []), true)) {
				return true;
			}
		}

		return false;
	}

	protected function refuse_s3_file(string $relative_path, ?string &$error): null
	{
		return $this->refuse_upload($relative_path, 'The filetype you are attempting to upload is not allowed.', $error);
	}

	/** Null or empty means "generate one" and passes; otherwise the fixed rules, then mgr_is_allowed_file_name(). */
	protected function is_desired_name_allowed(?string $file_name): bool
	{
		if ($file_name === null || $file_name === '') {
			return true;
		}

		if (preg_match('//u', $file_name) !== 1 || preg_match('~[/\\\\?\x00]~', $file_name) === 1 || $file_name[0] === '.') {
			return false;
		}

		return mgr_is_allowed_file_name($file_name);
	}

	/** `<hash>.<ext>` with the extension looked up from the detected type; `<hash>` alone when none is listed. */
	protected function generate_file_name(string|false $content_type): string
	{
		$file_name = mgr_generate_hash(32);
		$file_ext = mgr_file_extension(mime_type: (string) $content_type);

		return $file_ext ? "$file_name.$file_ext" : $file_name;
	}

	protected function refuse_desired_name(string $relative_path, ?string &$error): null
	{
		return $this->refuse_upload($relative_path, 'The file name is not allowed.', $error);
	}

	protected function refuse_upload(string $relative_path, string $message, ?string &$error): null
	{
		log_message('error', "Error uploading($relative_path): $message");
		$this->report_upload_error($message, $error);

		return null;
	}

	/** Logs a throwable that escaped an upload and reports `$message`; the caller's `$error` never carries internals. */
	protected function fail_upload(Throwable $exception, string $relative_path, string $message, ?string &$error): null
	{
		mgr_process_exception($exception, "Error uploading($relative_path)");
		$this->report_upload_error($message, $error);

		return null;
	}

	protected function report_upload_error(string $message, ?string &$error): void
	{
		if ($this->show_flashdata == true) {
			$this->session->set_flashdata('message', $message);
			$this->session->set_flashdata('message_kind', 'error');
		} else {
			$error = $message;
		}
	}

	/**
	 * Callers register a path only after its write returns, so a failed re-upload never deletes the previous file.
	 *
	 * @param list<string> $paths
	 */
	protected function delete_written_files(array $paths): void
	{
		foreach (array_unique($paths) as $path) {
			if (is_file($path)) {
				unlink($path);
			}
		}
	}

	protected function validate_files_field(string $field_name): ?string
	{
		if (!isset($_FILES[$field_name])) {
			return 'No file uploaded.';
		}

		// Handle multiple files (not supported here)
		if (is_array($_FILES[$field_name]['name'])) {
			return 'Multiple file uploads are not supported.';
		}

		switch ($_FILES[$field_name]['error']) {
			case UPLOAD_ERR_OK:
				return null;
			case UPLOAD_ERR_NO_FILE:
				return 'No file sent.';
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return 'Exceeded filesize limit.';
			case UPLOAD_ERR_PARTIAL:
				return 'The uploaded file was only partially uploaded.';
			case UPLOAD_ERR_CANT_WRITE:
				return 'Failed to write file to disk.';
			case UPLOAD_ERR_EXTENSION:
				return 'A PHP extension stopped the file upload.';
			default:
				return 'Unknown error.';
		}
	}
}
