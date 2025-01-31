<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Get the URL for an image, optionally specifying the protocol.
 *
 * @param string $uri The URI of the image.
 * @param string|null $protocol The protocol to use (e.g., 'http' or 'https'), or null to use the default.
 * @return string The full URL to the image.
 */
function image_url($uri = '', $protocol = NULL)
{
	return get_instance()->config->image_url($uri, $protocol);
}

/**
 * Generate a secure (HTTPS) URL from a given URI.
 *
 * @param string $uri The URI to be processed.
 * @return string The secure HTTPS URL.
 */
function secure_url($uri = '')
{
	if (!empty($uri)) {
		if (strpos($uri, 'https://') === 0)
			return $uri;

		if (strpos($uri, '//') === 0)
			return 'https:' . $uri;
	}

	return get_instance()->config->base_url($uri, 'https:');
}

/**
 * Generate the full file path based on the URI, handling private and public files.
 *
 * @param string $uri The file path to be processed.
 * @return string The full path to the file, either private or public.
 */

function file_path($uri = '')
{
	clean_file_path($uri);

	if (strpos($uri, 'private/') === 0) {
		return GET_APP_ROOT() . $uri;
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

function private_file_path($uri = '')
{
	clean_file_path($uri);

	if (strpos($uri, 'private/') !== 0) {
		$uri = "private/{$uri}";
	}

	return GET_APP_ROOT() . $uri;
}
/**
 * Clean a file path by removing query strings and leading slashes.
 *
 * @param string &$uri The file path to clean (passed by reference).
 * @return void
 */
function clean_file_path(&$uri = '')
{
	if (($pos = strpos($uri, '?')) !== false) {
		$uri = substr($uri, 0, $pos);
	}


	if (strpos($uri, '/') === 0) {
		$uri = ltrim($uri, '/');
	}
}
/**
 * Get the application private directory.
 *
 * @return string The application root path or an empty string if not found.
 */
function GET_PRIVATE_PATH()
{
	return GET_APP_ROOT() . "private/";
}
/**
 * Get the application root directory.
 *
 * @return string The application root path or an empty string if not found.
 */
function GET_APP_ROOT()
{
	$app_path_pos = strpos(APPPATH, 'app/');

	// Extract the base path including the 'app' folder
	if ($app_path_pos !== false) {
		return substr(APPPATH, 0, $app_path_pos + 4);
	}

	return '';
}