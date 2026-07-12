<?php

defined('BASEPATH') or exit('No direct script access allowed');

function mgr_file_kind_extension($file_path, &$mime_type = null, &$kind = null)
{
	$mime_type = mgr_detect_mime_from_file($file_path);

	if (strpos($mime_type, 'image/') === 0) {
		$kind = 'image';
	} else {
		$kind = 'document';
	}

	return mgr_file_extension($file_path, $mime_type);
}

/** @deprecated Use mgr_file_kind_extension() instead. */
function mgr_file_kind_extention($file_path, &$mime_type = null, &$kind = null)
{
	return mgr_file_kind_extension($file_path, $mime_type, $kind);
}

/**
 * Get file extension from MIME type with filename fallback
 *
 * @param string $mime_type MIME type to convert to extension
 * @param string $filepath Optional filename or file path for fallback extension extraction
 * @return string|false File extension (without dot) or false if not found
 */
function mgr_file_extension($filepath = '', $mime_type = '')
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
	$mimes = mgr_mimes_config();
	if (empty($mimes)) {
		return false;
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

/** @deprecated Use mgr_file_extension() instead. */
function mgr_file_extention($filepath = '', $mime_type = '')
{
	return mgr_file_extension($filepath, $mime_type);
}

if (!function_exists('mgr_mimes_config')) {
	/**
	 * config/mimes.php is a return-style file (`return [...]`), not the
	 * $config-variable style Config::load() requires — load it via config
	 * path resolution (respects env overrides / package config path) plus a
	 * direct include(), whose return value IS that array, instead.
	 */
	function &mgr_mimes_config()
	{
		static $mimes;

		if (!is_array($mimes)) {
			$path = get_instance()->config->path('mimes');
			$mimes = ($path !== null) ? include($path) : [];
		}

		return $mimes;
	}
}

if (!function_exists('mgr_mime_extension')) {
	function mgr_mime_extension($extension)
	{
		$mimes = mgr_mimes_config();

		// Remove dot if present
		$extension = ltrim($extension, '.');

		if (isset($mimes[$extension])) {
			// Return first MIME type if multiple exist
			return is_array($mimes[$extension]) ? $mimes[$extension][0] : $mimes[$extension];
		}

		return 'application/octet-stream'; // Default fallback
	}
}

if (!function_exists('mgr_mime_extention')) {
	/** @deprecated Use mgr_mime_extension() instead. */
	function mgr_mime_extention($extension)
	{
		return mgr_mime_extension($extension);
	}
}

/**
 * Detect MIME type from file path
 *
 * @param string $file_path Path to the file to analyze
 * @return string|false MIME type string or false on error
 */

if (!function_exists('mgr_detect_mime_from_file')) {
	function mgr_detect_mime_from_file($file_path)
	{
		if (!file_exists($file_path)) {
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
if (!function_exists('mgr_detect_mime_from_data')) {
	function mgr_detect_mime_from_data($data, $chunk_size = 2048)
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
