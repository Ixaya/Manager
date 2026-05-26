<?php


/**
 * Generate the full file path based on the URI, handling private and public files.
 *
 * @param string $uri The file path to be processed.
 * @return string The full path to the file, either private or public.
 */
function mgr_file_path($uri = '')
{
	mgr_clean_file_path($uri);

	if (strpos($uri, 'private/') === 0) {
		return mgr_app_file_path() . $uri;
	} else {
		return FCPATH . $uri;
	}
}

/**
 * Generate the full path for a private file, cleaning the URI and ensuring it starts with 'private/'.
 *
 * @param string $uri The file path to be processed.
 * @return string The full path to the private file.
 */
function mgr_private_file_path($uri = '')
{
	mgr_clean_file_path($uri);

	if (strpos($uri, 'private/') !== 0) {
		$uri = "private/{$uri}";
	}

	return mgr_app_file_path() . $uri;
}

/**
 * Get the application root directory.
 *
 * @return string The application root path or an empty string if not found.
 */
function mgr_app_file_path()
{
	$app_path_pos = strpos(APPPATH, 'app/');
	// Extract the base path including the 'app' folder
	if ($app_path_pos !== false) {
		return substr(APPPATH, 0, $app_path_pos + 4);
	}

	$public_path_pos = strpos(FCPATH, 'public/');
	// Extract the base path including the 'public' folder
	if ($public_path_pos !== false) {
		return substr(FCPATH, 0, $public_path_pos);
	}

	$application_path_pos = strpos(APPPATH, 'application/');
	// Extract the base path up to (but not including) the 'application' folder
	if ($application_path_pos !== false) {
		return substr(APPPATH, 0, $application_path_pos);
	}

	return '';
}

/**
 * Clean a file path by removing query strings and leading slashes.
 *
 * @param string &$uri The file path to clean (passed by reference).
 * @return void
 */
function mgr_clean_file_path(&$uri = '')
{
	if (($pos = strpos($uri, '?')) !== false) {
		$uri = substr($uri, 0, $pos);
	}


	if (strpos($uri, '/') === 0) {
		$uri = ltrim($uri, '/');
	}
}

/**
 * Clean a s3 file path by removing query strings and s3 base path.
 *
 * @param string &$uri The file path to clean (passed by reference).
 * @return void
 */
function mgr_clean_file_s3_path(&$uri = '')
{
	if (($pos = strpos($uri, '?')) !== false) {
		$uri = substr($uri, 0, $pos);
	}

	if (($pos = strpos($uri, 's3/')) !== false) {
		$uri = substr($uri, $pos + 3); // Get everything after 's3/'
	}
}

/**
 * Retrieves the temporary upload path of an uploaded file and optionally returns its extension, type, and name.
 *
 * @param string $field_name The name of the input field from the $_FILES array.
 * @param string|null &$file_extension Optional. Will be set to the file's extension.
 * @param string|null &$file_type Optional. Will be set to the file's MIME type.
 * @param string|null &$file_name Optional. Will be set to the original name of the file.
 *
 * @return string The temporary file path of the uploaded file.
 */
function mgr_get_temp_upload_path($field_name, &$file_extension = null, &$file_type = null, &$file_name = null)
{
	if (
		!isset($_FILES[$field_name]) ||
		$_FILES[$field_name]['error'] !== UPLOAD_ERR_OK
	) {
		return '';
	}

	$tmp_file_path = $_FILES[$field_name]['tmp_name'];

	$file_name = $_FILES[$field_name]['name'];
	$file_extension = pathinfo($file_name, PATHINFO_EXTENSION);

	$file_type = $_FILES[$field_name]['type'];

	return $tmp_file_path;
}


/**
 * Retrieves the temporary upload path of a specific file from a multi-file upload
 * and optionally returns its extension and type.
 *
 * @param string $field_name The name of the input field from the $_FILES array.
 * @param int|string $field_key The index or key of the specific file in the multi-file upload.
 * @param string|null &$file_extension Optional. Will be set to the file's extension.
 * @param string|null &$file_type Optional. Will be set to the file's MIME type.
 *
 * @return string The temporary file path of the uploaded file.
 */
function mgr_get_temp_upload_path_key($field_name, $field_key, &$file_extension = null, &$file_type = null)
{
	if (
		!isset($_FILES[$field_name]['tmp_name'][$field_key]) ||
		$_FILES[$field_name]['error'][$field_key] !== UPLOAD_ERR_OK
	) {
		return '';
	}

	$tmp_file_path = $_FILES[$field_name]['tmp_name'][$field_key];

	$tmp_file_name = $_FILES[$field_name]['name'][$field_key];
	$file_extension = pathinfo($tmp_file_name, PATHINFO_EXTENSION);

	$file_type = $_FILES[$field_name]['type'][$field_key];

	return $tmp_file_path;
}

/**
 * Retrieves temporary upload paths for single or multiple uploaded files and
 * optionally returns their extensions and types.
 *
 * @param string $field_name The name of the input field from the $_FILES array.
 * @param array|string|null &$file_extension Optional. Will be set to the file extension(s).
 * @param array|string|null &$file_type Optional. Will be set to the file MIME type(s).
 * @return array The temporary file path(s) of the uploaded file(s).
 */
function mgr_get_temp_upload_paths($field_name, &$file_extension = null, &$file_type = null)
{
	if (!isset($_FILES[$field_name]['tmp_name']) || empty($_FILES[$field_name]['tmp_name'])) {
		return [];
	}

	$temp_paths = [];
	$extensions = [];
	$types = [];

	if (is_array($_FILES[$field_name]['tmp_name'])) {
		foreach ($_FILES[$field_name]['tmp_name'] as $key => $tmp_file_path) {
			if (empty($tmp_file_path)) {
				continue;
			}

			$tmp_file_name = $_FILES[$field_name]['name'][$key];
			$extensions[] = pathinfo($tmp_file_name, PATHINFO_EXTENSION);
			$types[] = $_FILES[$field_name]['type'][$key];
			$temp_paths[] = $tmp_file_path;
		}
	} else {
		$tmp_file_name = $_FILES[$field_name]['name'];
		$extensions[] = pathinfo($tmp_file_name, PATHINFO_EXTENSION);
		$types[] = $_FILES[$field_name]['type'];
		$temp_paths[] = $_FILES[$field_name]['tmp_name'];
	}

	$file_extension = $extensions;
	$file_type = $types;

	return $temp_paths;
}
