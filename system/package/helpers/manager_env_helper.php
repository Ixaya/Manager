<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('mgr_env')) {
	// Get environment variable
	function mgr_env($key, $default = null, $strict = false)
	{
		return Env_lib::get($key, $default, $strict);
	}
}

if (!function_exists('mgr_env_strict')) {
	// Get environment variable forcing check if not empty
	function mgr_env_strict($key, $default = null)
	{
		return Env_lib::get($key, $default, true);
	}
}

if (!function_exists('mgr_env_bool')) {
	// Get environment variable as boolean
	function mgr_env_bool($key, $default = false)
	{
		return Env_lib::get_bool($key, $default);
	}
}

if (!function_exists('mgr_env_int')) {
	// Get environment variable as integer
	function mgr_env_int($key, $default = 0)
	{
		return Env_lib::get_int($key, $default);
	}
}

if (!function_exists('mgr_env_float')) {
	// Get environment variable as float
	function mgr_env_float($key, $default = 0.0)
	{
		return Env_lib::get_float($key, $default);
	}
}

if (!function_exists('mgr_env_array')) {
	//Get environment variable as array (comma-separated)
	function mgr_env_array($key, $default = [], $separator = ',')
	{
		return Env_lib::get_array($key, $default, $separator);
	}
}

if (!function_exists('mgr_env_json')) {
	// Get environment variable as JSON decoded array/object
	function mgr_env_json($key, $default = null, $associative = true)
	{
		return Env_lib::get_json($key, $default, $associative);
	}
}
