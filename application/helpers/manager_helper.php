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

function add_css_fontawesome5(&$items)
{
	$items[] = '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css" integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay" crossorigin="anonymous">';
}