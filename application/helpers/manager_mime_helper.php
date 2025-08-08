<?php
defined('BASEPATH') or exit('No direct script access allowed');

function mngr_file_kind_extention($file_path, &$mime_type = null, &$kind = null)
{
	$mime_type = mngr_detect_mime_from_file($file_path);

	if (strpos($mime_type, 'image/') === 0) {
		$kind = 'image';
	} else {
		$kind = 'document';
	}

	return mngr_file_extention($file_path, $mime_type);
}

/**
 * Get file extension from MIME type with filename fallback
 *
 * @param string $mime_type MIME type to convert to extension
 * @param string $filepath Optional filename or file path for fallback extension extraction
 * @return string|false File extension (without dot) or false if not found
 */
function mngr_file_extention($filepath = '', $mime_type = '')
{
	if (!empty($filepath)) {
		$extension = pathinfo($filepath, PATHINFO_EXTENSION);
		if ($extension) {
			return strtolower($extension);
		}
	}
	
	if ($mime_type == '') {
		return false;
	}

	// Fallback: extract extension from filename/path
	static $mimes;
	if (!is_array($mimes))
	{
		$mimes = get_mimes();
		if (empty($mimes)) {
			return false;
		}
	}

	// Try to find extension by MIME type first
	foreach ($mimes as $extension => $mime_values) {
		$mime_values = (array) $mime_values;
		if (in_array($mime_type, $mime_values)) {
			return $extension;
		}
	}

	return false;
}

if (!function_exists('mngr_mime_extention')) {
	function mngr_mime_extention($extension)
	{
		$CI = &get_instance();
		$CI->config->load('mimes');
		$mimes = $CI->config->item('mimes');

		// Remove dot if present
		$extension = ltrim($extension, '.');

		if (isset($mimes[$extension])) {
			// Return first MIME type if multiple exist
			return is_array($mimes[$extension]) ? $mimes[$extension][0] : $mimes[$extension];
		}

		return 'application/octet-stream'; // Default fallback
	}
}

/**
 * Detect MIME type from file path
 *
 * @param string $file_path Path to the file to analyze  
 * @return string|false MIME type string or false on error
 */

if (!function_exists('mngr_detect_mime_from_file')) {
	function mngr_detect_mime_from_file($file_path)
	{
		if (!file_exists($file_path)){
			return false;
		}

		if (!function_exists('finfo_open')) {
			return mime_content_type($file_path);
		}

		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $file_path);
		finfo_close($finfo);

		return $mime ?: 'application/octet-stream';
	}
}


/**
 * Detect MIME type from raw data using chunk
 *
 * @param string $data Raw file data to analyze
 * @param int $chunk_size Number of bytes to analyze from data start (default: 2048)
 * @return string|false MIME type string or false on error
 */
if (!function_exists('mngr_detect_mime_from_data')) {
	function mngr_detect_mime_from_data($data, $chunk_size = 2048)
	{
		if (!function_exists('finfo_open') || empty($data)) {
			return empty($data) ? 'application/octet-stream' : false;
		}

		$chunk = substr($data, 0, $chunk_size);

		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_buffer($finfo, $chunk);
		finfo_close($finfo);

		return $mime ?: 'application/octet-stream';
	}
}