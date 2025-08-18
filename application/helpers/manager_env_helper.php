<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('mngr_env')) {
	// Get environment variable
	function mngr_env($key, $default = null, $strict = false)
	{
		return Ix_env_lib::get($key, $default, $strict);
	}
}

if (!function_exists('mngr_env_strict')) {
	// Get environment variable forcing check if not empty
	function mngr_env_strict($key, $default = null)
	{
		return Ix_env_lib::get($key, $default, true);
	}
}

if (!function_exists('mngr_env_bool')) {
	// Get environment variable as boolean
	function mngr_env_bool($key, $default = false)
	{
		return Ix_env_lib::get_bool($key, $default);
	}
}

if (!function_exists('mngr_env_int')) {
	// Get environment variable as integer
	function mngr_env_int($key, $default = 0)
	{
		return Ix_env_lib::get_int($key, $default);
	}
}

if (!function_exists('mngr_env_float')) {
	// Get environment variable as float
	function mngr_env_float($key, $default = 0.0)
	{
		return Ix_env_lib::get_float($key, $default);
	}
}

if (!function_exists('mngr_env_array')) {
	//Get environment variable as array (comma-separated)
	function mngr_env_array($key, $default = array(), $separator = ',')
	{
		return Ix_env_lib::get_array($key, $default, $separator);
	}
}

if (!function_exists('mngr_env_json')) {
	// Get environment variable as JSON decoded array/object
	function mngr_env_json($key, $default = null, $associative = true)
	{
		return Ix_env_lib::get_json($key, $default, $associative);
	}
}