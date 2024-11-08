<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Base URL
 *
 * Returns base_url [. uri_string]
 *
 * @uses	CI_Config::_uri_string()
 *
 * @param	string|string[]	$uri	URI string or an array of segments
 * @param	string	$protocol
 * @return	string
 */

function image_url($uri = '', $protocol = NULL)
{
	return get_instance()->config->image_url($uri, $protocol);
}

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

function file_path($uri = '')
{
	clean_file_path($uri);

	if (strpos($uri, 'private/') === 0) {
		return GET_APP_ROOT() . $uri;
	} else {
		return FCPATH . $uri;
	}
}

function private_file_path($uri = '')
{
	clean_file_path($uri);

	if (strpos($uri, 'private/') !== 0) {
		$uri = "private/{$uri}";
	}

	return GET_APP_ROOT() . $uri;
}

function clean_file_path(&$uri = '')
{
	if (($pos = strpos($uri, '?')) !== false) {
		$uri = substr($uri, 0, $pos);
	}


	if (strpos($uri, '/') === 0) {
		$uri = ltrim($uri, '/');
	}
}
function GET_PRIVATE_PATH()
{
	return GET_APP_ROOT() . "private/";
}
function GET_APP_ROOT()
{
	$app_path_pos = strpos(APPPATH, 'app/');

	// Extract the base path including the 'app' folder
	if ($app_path_pos !== false) {
		return substr(APPPATH, 0, $app_path_pos + 4);
	}

	return '';
}

function add_css_fontawesome5(&$items)
{
	$items[] = '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css" integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay" crossorigin="anonymous">';
}