<?php

defined('BASEPATH') or exit('No direct script access allowed');

class MGR_Upload_lib
{
	/** Default `allowed_types` of upload_file() when no `$upload_config` is given. */
	protected const string DEFAULT_FILE_TYPES = 'gif|jpg|jpeg|png|webp|pdf|txt|csv|doc|docx|xls|xlsx|ppt|pptx|odt|ods';

	/** Default `allowed_types` of upload_image() when no `$upload_config` is given. */
	protected const string DEFAULT_IMAGE_TYPES = 'gif|jpg|png|jpeg|webp';

	/** Extensions never stored under the web root, whatever the config. Keep in sync with the page-type location in docker/nginx/conf.d/app.conf. */
	protected const array PAGE_TYPES = ['html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'xml', 'xsl', 'js'];

	/** Extensions never stored under the web root or on S3, whatever the config, and never given to a generated name. */
	protected const array PHP_FAMILY_TYPES = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phtml', 'phps', 'phar'];

	/** Default upload_image() target, `jpg` or `webp`; a same-named project subclass sets it project-wide. */
	protected string $convert_to = 'jpg';

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
	 * Uploads a file and returns its details.
	 *
	 * Refused: page types and PHP-family types under the web root and on S3, whatever the config.
	 *
	 * @param string $relative_path Storage URI (`media/…`, `private/…`, `s3/…`) built by the server, never request input; not validated.
	 * @param ?string $desired_file_name Built by the server, never request input; null generates one. One dot at most: the text after it is the extension, which must fit the content; stored lowercase.
	 * @param ?array $upload_config Replaces the defaults (images, pdf, txt, csv, Office and OpenDocument) entirely.
	 * @param bool $encrypt_name Ignored; a name is generated unless `$desired_file_name` is given.
	 * @return array|null Null on failure, reason in `$error`.
	 */
	public function upload_file(string $relative_path, ?string $desired_file_name = null, string $field_name = 'userfile', ?array $upload_config = null, bool $encrypt_name = true, ?string &$error = null): ?array
	{
		$validate_result = $this->validate_files_field($field_name);
		if ($validate_result !== null) {
			$error = $validate_result;
			return null;
		}

		if (mgr_is_s3_path($relative_path)) {
			return $this->upload_file_s3($relative_path, $desired_file_name, $field_name, $upload_config, $encrypt_name, $error);
		}

		return $this->upload_file_local($relative_path, $desired_file_name, $field_name, $upload_config, $encrypt_name, $error);
	}
	public function upload_file_local(string $relative_path, ?string $desired_file_name = null, string $field_name = 'userfile', ?array $upload_config = null, bool $encrypt_name = true, ?string &$error = null): ?array
	{
		if (!$this->is_desired_name_allowed($desired_file_name)) {
			return $this->refuse_desired_name($relative_path, $error);
		}

		$name_parts = $this->split_desired_name($desired_file_name);
		if ($name_parts === null || !$this->is_desired_extension_allowed($name_parts['extension'], (string) mgr_detect_mime_from_file(mgr_get_temp_upload_path(field_name: $field_name)), null, $upload_config, static::DEFAULT_FILE_TYPES)) {
			return $this->refuse_filetype($relative_path, $error);
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
			$config['file_ext_tolower'] = true;

			if ($desired_file_name !== null && $desired_file_name !== '') {
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
				if ($desired_file_name !== null && $desired_file_name !== '') {
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
			if ($desired_file_name !== null && $desired_file_name !== '') {
				$v = dechex(time());
				$v = "?v=$v";
			}

			$name_parts = $this->split_desired_name($desired_file_name);
			if ($name_parts === null) {
				return $this->refuse_s3_file($relative_path, $error);
			}

			$stem = $name_parts['stem'] === '' ? mgr_generate_hash(32) : $name_parts['stem'];

			$file_ext = '';
			$file_name = '';

			$original_file_path = mgr_get_temp_upload_path(field_name: $field_name, file_extension: $file_ext, file_name: $file_name);
			$file_type = (string) mgr_detect_mime_from_file($original_file_path);

			$file_ext = strtolower($file_ext);
			$s3_file_name = "{$stem}." . ($name_parts['extension'] ?? $file_ext);

			// The client's own extension is checked against the content too, so a dotted name cannot skip it.
			if (!$this->is_s3_file_allowed("{$stem}.{$file_ext}", $file_type, $upload_config, static::DEFAULT_FILE_TYPES)
				|| ($name_parts['extension'] !== null && !$this->is_s3_file_allowed($s3_file_name, $file_type, $upload_config, static::DEFAULT_FILE_TYPES))
			) {
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
	 * @param string $relative_path Storage URI (`media/…`, `private/…`, `s3/…`) built by the server, never request input; not validated.
	 * @param string $file_name Built by the server, never request input; empty generates `<hash>.<ext>` from the detected type.
	 * @return array|null Null on failure, reason in `$error`.
	 */
	public function put_file(string $relative_path, string $file_name, string $data, ?string &$error = null): ?array
	{
		if (mgr_is_s3_path($relative_path)) {
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
			if ($file_name !== '') {
				$v = dechex(time());
				$v = "?v=$v";
			}

			if ($file_name === '') {
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
			if ($file_name !== '') {
				$v = dechex(time());
				$v = "?v=$v";
			}

			$content_type = mgr_detect_mime_from_data($data);

			if ($file_name === '') {
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
	 * Uploads an image, re-encoded to the conversion target, with an optional thumbnail.
	 *
	 * Refused: page types and PHP-family types, content GD cannot decode, images over the pixel ceiling.
	 *
	 * @param string $relative_path Storage URI (`media/…`, `private/…`, `s3/…`) built by the server, never request input; not validated.
	 * @param ?string $desired_file_name Built by the server, never request input; null generates one. One dot at most: the text after it is the extension, which must be the conversion target (listed in `allowed_types`), or fit the content under `$preserve_type`; stored lowercase.
	 * @param ?array $resolution `[width, height]` box; a thumbnail is built only when the image is larger.
	 * @param bool $preserve_type Store as uploaded: no conversion, an animated GIF keeps its frames.
	 * @param ?array $upload_config Replaces the defaults (gif, jpg, jpeg, png, webp) entirely.
	 * @param ?string $convert_to `jpg` or `webp`; null uses the `$convert_to` property. JPEG flattens alpha onto white.
	 * @return array|null Null on failure, reason in `$error`. `thumb_*` name the main when no thumbnail was stored;
	 *                    `original_*` are empty when no original was kept.
	 * @throws InvalidArgumentException On an unknown `$convert_to`.
	 */
	public function upload_image(string $relative_path, ?string $desired_file_name = null, bool $delete_original = true, string $field_name = 'userfile', ?array $resolution = null, bool $preserve_type = false, ?array $upload_config = null, ?string &$error = null, ?string $convert_to = null): ?array
	{
		$CI = get_instance();
		$CI->load->library('image_process_lib');

		$convert_to ??= $this->convert_to;
		if (!$CI->image_process_lib->is_convert_target($convert_to)) {
			throw new InvalidArgumentException("MGR_Upload_lib: unknown conversion target '{$convert_to}'.");
		}

		$validate_result = $this->validate_files_field($field_name);
		if ($validate_result !== null) {
			$error = $validate_result;
			return null;
		}

		// Runs on PHP's temp file, so a refusal leaves any previous image under the same name untouched.
		$temp_path = $_FILES[$field_name]['tmp_name'];
		$detected_type = (string) mgr_detect_mime_from_file($temp_path);

		if (!$preserve_type && !$CI->image_process_lib->has_image_decoder($detected_type)) {
			return $this->refuse_upload($relative_path, 'The filetype you are attempting to upload is not allowed.', $error);
		}

		// A server-side gap, so the caller gets the generic message and the log names the missing encoder.
		if (!$preserve_type && !$CI->image_process_lib->has_image_encoder($convert_to)) {
			log_message('error', "Error uploading($relative_path): no GD encoder for the '{$convert_to}' target");
			$this->report_upload_error('Error al subir imagen', $error);

			return null;
		}

		$will_decode = !$preserve_type || ($resolution !== null && $CI->image_process_lib->is_thumbnail_type($detected_type));
		if ($will_decode && !$CI->image_process_lib->fits_pixel_ceiling($temp_path)) {
			return $this->refuse_upload($relative_path, 'The image you are attempting to upload does not fit into the allowed dimensions.', $error);
		}

		if (mgr_is_s3_path($relative_path)) {
			return $this->upload_image_s3($relative_path, $desired_file_name, $delete_original, $field_name, $resolution, $preserve_type, $upload_config, $error, $convert_to);
		}

		return $this->upload_image_local($relative_path, $desired_file_name, $delete_original, $field_name, $resolution, $preserve_type, $upload_config, $error, $convert_to);
	}
	protected function upload_image_local(string $relative_path, ?string $desired_file_name = null, bool $delete_original = true, string $field_name = 'userfile', ?array $resolution = null, bool $preserve_type = false, ?array $upload_config = null, ?string &$error = null, ?string $convert_to = null): ?array
	{
		$CI = get_instance();
		$CI->load->library('image_process_lib');

		$convert_to ??= $this->convert_to;
		if (!$this->is_desired_name_allowed($desired_file_name)) {
			return $this->refuse_desired_name($relative_path, $error);
		}

		$temp_path = mgr_get_temp_upload_path(field_name: $field_name);

		$name_parts = $this->split_desired_name($desired_file_name);
		if ($name_parts === null || !$this->is_desired_extension_allowed($name_parts['extension'], (string) mgr_detect_mime_from_file($temp_path), $preserve_type ? null : $convert_to, $upload_config, static::DEFAULT_IMAGE_TYPES)) {
			return $this->refuse_filetype($relative_path, $error);
		}

		$stem = $name_parts['stem'] === '' ? mgr_generate_hash(32) : $name_parts['stem'];
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
			$config['file_ext_tolower'] = true;

			// Under conversion the upload is stored aside as `<stem>_original.<client ext>`, so the main never
			// overwrites its own source and CI never sees the desired extension.
			if (!$preserve_type) {
				$config['file_name'] = "{$stem}_original";
				$config['overwrite'] = true;
			} elseif ($desired_file_name !== null && $desired_file_name !== '') {
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
					$this->session->set_flashdata('message', 'Imagen agregada correctamente');
					$this->session->set_flashdata('message_kind', 'success');
				}

				$upload_data = $this->upload->data();

				$original_file_path = $upload_data['full_path'];
				$file_ext = $upload_data['file_ext'];
				$file_type = $upload_data['file_type'];

				if (!$preserve_type) {
					$file_ext = '.' . $convert_to;
					$file_type = $CI->image_process_lib->convert_target_type($convert_to);
				}

				$main_stem = $preserve_type ? $upload_data['raw_name'] : $stem;
				$new_file_name = $main_stem . $file_ext;
				// CI's own resolved directory, so path comparisons with its full_path hold through a symlinked root.
				$new_file_path = $upload_data['file_path'] . $new_file_name;

				if (!$preserve_type) {
					$CI->image_process_lib->convert_image(source_path: $original_file_path, detected_type: $upload_data['file_type'], target_path: $new_file_path, convert_to: $convert_to);
					$written_paths[] = $new_file_path;
				}

				// The main image stands in for any thumbnail that was not built or stored.
				$new_file_thumb = $new_file_name;
				if ($resolution !== null && $CI->image_process_lib->is_thumbnail_type($file_type)) {
					$image_info = getimagesize($new_file_path);
					$original_width = $image_info[0];
					$original_height = $image_info[1];

					// Only resize if image is larger than desired resolution
					if ($original_width > $resolution[0] || $original_height > $resolution[1]) {
						// Create thumbnail
						$thumb_file_name = $main_stem . '_thumb' . $file_ext;
						$thumb_file_path = $upload_data['file_path'] . $thumb_file_name;
						if ($CI->image_process_lib->create_thumbnail(source_path: $new_file_path, detected_type: $file_type, target_path: $thumb_file_path, resolution: $resolution)) {
							$written_paths[] = $thumb_file_path;
							$new_file_thumb = $thumb_file_name;
						} else {
							log_message('error', "Error uploading($relative_path): thumbnail not created");
						}
					}
				}

				if ($delete_original && !$preserve_type) {
					unlink($original_file_path);
				}

				$original_kept = $original_file_path !== $new_file_path && file_exists($original_file_path);

				//return thumbnail name and image_name
				$v = '';
				if ($desired_file_name !== null && $desired_file_name !== '') {
					$v = dechex(time());
					$v = "?v=$v";
				}

				if (strpos($relative_path, '/') !== 0) {
					$relative_path = "/$relative_path";
				}

				$return_data['fullsize_name'] = $new_file_name . $v;
				$return_data['fullsize_type'] = $file_type;
				$return_data['fullsize_url']  = $relative_path . $new_file_name . $v;

				$return_data['original_link'] = $original_kept ? $original_file_path : '';
				$return_data['original_name'] = $original_kept ? basename($original_file_path) . $v : '';

				$return_data['thumb_name']	  = $new_file_thumb . $v;
				$return_data['thumb_url']     = $relative_path . $new_file_thumb . $v;

				//Retro compativility keys
				// $return_data['thumb_image_name'] = $return_data['thumb_name'];
				// $return_data['fullsize_image_name'] = $return_data['fullsize_name'];
				// $return_data['url_image'] = $return_data['fullsize_url'];

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
	protected function upload_image_s3(string $relative_path, ?string $desired_file_name = null, bool $delete_original = true, string $field_name = 'userfile', ?array $resolution = null, bool $preserve_type = false, ?array $upload_config = null, ?string &$error = null, ?string $convert_to = null): ?array
	{
		$CI = get_instance();
		$CI->load->library('image_process_lib');

		$convert_to ??= $this->convert_to;

		if (!$this->is_desired_name_allowed($desired_file_name)) {
			return $this->refuse_desired_name($relative_path, $error);
		}

		try {
			$this->load->library('amazon_aws_lib');

			$s3_relative_path = $relative_path;
			mgr_clean_file_s3_path($s3_relative_path);

			$name_parts = $this->split_desired_name($desired_file_name);
			if ($name_parts === null) {
				return $this->refuse_s3_file($relative_path, $error);
			}

			$stem = $name_parts['stem'] === '' ? mgr_generate_hash(32) : $name_parts['stem'];

			$file_ext = '';

			$s3_file_name = '';
			$s3_file_path = '';

			$s3_orig_file_name = '';

			$s3_thumb_file_name = '';

			$original_file_path = mgr_get_temp_upload_path(field_name: $field_name, file_extension: $file_ext);
			$file_type = (string) mgr_detect_mime_from_file($original_file_path);

			$file_ext = strtolower($file_ext);
			$desired_ext = $name_parts['extension'];

			// The client's own extension is checked against the content too, so a dotted name cannot skip it.
			$desired_ext_allowed = $desired_ext === null || ($preserve_type
				? $this->is_s3_file_allowed("{$stem}.{$desired_ext}", $file_type, $upload_config, static::DEFAULT_IMAGE_TYPES)
				: $this->is_desired_extension_allowed($desired_ext, $file_type, $convert_to, $upload_config, static::DEFAULT_IMAGE_TYPES));

			if (!$this->is_s3_file_allowed("{$stem}.{$file_ext}", $file_type, $upload_config, static::DEFAULT_IMAGE_TYPES) || !$desired_ext_allowed) {
				return $this->refuse_s3_file($relative_path, $error);
			}

			$keep_original = false;
			$original_ext = $file_ext;

			if (!$preserve_type) {
				$base_file_path = "{$original_file_path}_optimized";
				$CI->image_process_lib->convert_image(source_path: $original_file_path, detected_type: $file_type, target_path: $base_file_path, convert_to: $convert_to);

				$keep_original = $delete_original == false;
				$file_ext = $convert_to;
				$file_type = $CI->image_process_lib->convert_target_type($convert_to);
			} else {
				$base_file_path = $original_file_path;
				$file_ext = $desired_ext ?? $file_ext;
			}

			// Put order: main, original, thumbnail. A failed main stores nothing; a later failure falls back.
			$s3_file_name = "{$stem}.{$file_ext}";
			$s3_file_path = "{$s3_relative_path}{$s3_file_name}";

			$image_url = $this->amazon_aws_lib->upload_file($base_file_path, $s3_file_path);
			if (empty($image_url)) {
				return $this->refuse_upload($relative_path, 'Error al subir imagen', $error);
			}

			if ($this->show_flashdata == true) {
				$this->session->set_flashdata('message', 'Imagen agregada correctamente');
				$this->session->set_flashdata('message_kind', 'success');
			}

			if ($keep_original) {
				$s3_orig_file_name = "{$stem}_original.{$original_ext}";

				$orig_image_url = $this->amazon_aws_lib->upload_file($original_file_path, "{$s3_relative_path}{$s3_orig_file_name}");
				if (empty($orig_image_url)) {
					log_message('error', "Error uploading($relative_path): original not stored");
					$s3_orig_file_name = '';
				}
			}

			if ($resolution !== null && $CI->image_process_lib->is_thumbnail_type($file_type)) {
				$image_info = getimagesize($base_file_path);
				$original_width = $image_info[0];
				$original_height = $image_info[1];

				// Only resize if image is larger than desired resolution
				if ($original_width > $resolution[0] || $original_height > $resolution[1]) {
					// Create thumbnail
					$thumb_file_path = "{$base_file_path}_thumb";

					if ($CI->image_process_lib->create_thumbnail(source_path: $base_file_path, detected_type: $file_type, target_path: $thumb_file_path, resolution: $resolution)) {
						$thumb_file_name = "{$stem}_thumb.{$file_ext}";

						$thumb_image_url = $this->amazon_aws_lib->upload_file($thumb_file_path, "{$s3_relative_path}{$thumb_file_name}");
						if (empty($thumb_image_url)) {
							log_message('error', "Error uploading($relative_path): thumbnail not stored");
						} else {
							$s3_thumb_file_name = $thumb_file_name;
						}
					} else {
						log_message('error', "Error uploading($relative_path): thumbnail not created");
					}

					// Clean up local thumbnail file after S3 upload
					if (file_exists($thumb_file_path)) {
						unlink($thumb_file_path);
					}
				} else {
					$s3_thumb_file_name = $s3_file_name;
				}
			}

			if ($s3_thumb_file_name === '') {
				$s3_thumb_file_name = $s3_file_name;
			}

			//Clean up optimized image
			if ($base_file_path != $original_file_path && file_exists($base_file_path)) {
				unlink($base_file_path);
			}

			$v = '';
			if ($desired_file_name !== null && $desired_file_name !== '') {
				$v = dechex(time());
				$v = "?v=$v";
			}

			if (strpos($relative_path, '/') !== 0) {
				$relative_path = "/$relative_path";
			}

			$return_data['fullsize_type'] = $file_type;
			$return_data['fullsize_name'] = $s3_file_name . $v;
			$return_data['fullsize_url']  = $relative_path . $s3_file_name . $v;


			$return_data['original_name'] = $s3_orig_file_name === '' ? '' : $s3_orig_file_name . $v;
			$return_data['original_url'] = $s3_orig_file_name === '' ? '' : $relative_path . $s3_orig_file_name . $v;

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

		if (mgr_is_s3_path($file_path)) {
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
		$CI = get_instance();
		$CI->load->library('image_process_lib');

		$is_raster = $CI->image_process_lib->is_raster_type((string) $detected_type);
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

		if (mgr_is_s3_path($file_path)) {
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

	protected function is_php_family_type(string $file_name): bool
	{
		return in_array(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)), static::PHP_FAMILY_TYPES, true);
	}

	/**
	 * Deletes the file CI just stored and queues CI's filetype error when it is a page or PHP-family type under the web root.
	 *
	 * @param string $file_path Upload directory as requested; CI resolves symlinks in its own copy.
	 */
	protected function refuse_public_page_type(string $file_path): bool
	{
		$file_name = $this->upload->data('file_name');
		if (!str_starts_with($file_path, FCPATH) || (!$this->is_page_type($file_name) && !$this->is_php_family_type($file_name))) {
			return false;
		}

		unlink($this->upload->data('full_path'));
		$this->upload->set_error('upload_invalid_filetype', 'debug');

		return true;
	}

	/**
	 * CI Upload's rule for the S3 paths, which never reach CI: extension on the allowed list and the detected type
	 * listed for it. Only `allowed_types` of `$upload_config` is honored. A page type, by extension or by detected
	 * type, and a PHP-family extension are refused whatever the list.
	 */
	protected function is_s3_file_allowed(string $s3_file_name, string $detected_type, ?array $upload_config, string $default_types): bool
	{
		if ($this->is_page_type($s3_file_name) || $this->is_php_family_type($s3_file_name) || $this->is_page_mime($detected_type)) {
			return false;
		}

		return $this->is_extension_listed_for_type(strtolower(pathinfo($s3_file_name, PATHINFO_EXTENSION)), $detected_type, $upload_config, $default_types);
	}

	/** An extension on the allowed list whose `mimes.php` entry lists the detected type; `*` allows any. */
	protected function is_extension_listed_for_type(string $extension, string $detected_type, ?array $upload_config, string $default_types): bool
	{
		$allowed_types = $this->allowed_types_list($upload_config, $default_types);
		if ($allowed_types === null) {
			return true;
		}

		$allowed_mimes = (array) (mgr_mimes_config()[$extension] ?? []);

		return in_array($extension, $allowed_types, true) && in_array($detected_type, $allowed_mimes, true);
	}

	/** The `allowed_types` in force as a list; null when `*` allows any. */
	protected function allowed_types_list(?array $upload_config, string $default_types): ?array
	{
		$allowed_types = $upload_config == null ? $default_types : ($upload_config['allowed_types'] ?? '');
		if ($allowed_types === '*') {
			return null;
		}

		return is_array($allowed_types) ? $allowed_types : explode('|', $allowed_types);
	}

	/**
	 * Stem and lowercased extension of a desired name, which has at most one dot: the text after it is the extension.
	 *
	 * @return array{stem: string, extension: ?string}|null Null for more than one dot or a trailing dot.
	 */
	protected function split_desired_name(?string $desired_file_name): ?array
	{
		$desired_file_name ??= '';
		if (substr_count($desired_file_name, '.') > 1 || str_ends_with($desired_file_name, '.')) {
			return null;
		}

		$dot_position = strpos($desired_file_name, '.');
		if ($dot_position === false) {
			return ['stem' => $desired_file_name, 'extension' => null];
		}

		return ['stem' => substr($desired_file_name, 0, $dot_position), 'extension' => strtolower(substr($desired_file_name, $dot_position + 1))];
	}

	/** A desired extension must agree with the file it names: the conversion target (also on the allowed list) when converting, else a type listed for the detected content. */
	protected function is_desired_extension_allowed(?string $extension, string $detected_type, ?string $convert_target, ?array $upload_config, string $default_types): bool
	{
		if ($extension === null) {
			return true;
		}

		if ($convert_target !== null) {
			$allowed_types = $this->allowed_types_list($upload_config, $default_types);

			return $extension === $convert_target && ($allowed_types === null || in_array($extension, $allowed_types, true));
		}

		return $this->is_extension_listed_for_type($extension, $detected_type, $upload_config, $default_types);
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
		return $this->refuse_filetype($relative_path, $error);
	}

	protected function refuse_filetype(string $relative_path, ?string &$error): null
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

	/** `<hash>.<ext>` with the extension looked up from the detected type; `<hash>` alone when none is listed or it is a PHP extension. */
	protected function generate_file_name(string|false $content_type): string
	{
		$file_name = mgr_generate_hash(32);
		$file_ext = mgr_file_extension(mime_type: (string) $content_type);

		if (!$file_ext || in_array($file_ext, static::PHP_FAMILY_TYPES, true)) {
			return $file_name;
		}

		return "$file_name.$file_ext";
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
