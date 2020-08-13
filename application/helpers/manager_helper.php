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
	return get_instance()->config->base_url($uri, 'https:');
}
