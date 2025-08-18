<?php

class Ix_env_lib
{

	private static $loaded = false;
	private static $env_vars = array();

	public static function load($enviorment = null)
	{
		if (self::$loaded) {
			return;
		}

		$file_path = Ix_env_lib::get_file_path($enviorment);
		if ($file_path === null){
			return;
		}
		
		
		$lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		foreach ($lines as $line) {
			// Skip comments
			if (strpos(trim($line), '#') === 0) {
				continue;
			}

			// Parse key=value pairs
			if (strpos($line, '=') !== false) {
				list($key, $value) = explode('=', $line, 2);
				$key = trim($key);
				$value = trim($value);

				// Remove quotes if present
				$value = trim($value, '"\'');

				// Store internally
				if (!empty($value)){
					self::$env_vars[$key] = $value;
				}
			}
		}

		self::$loaded = true;
	}

	public static function get($key, $default = null, $strict = false)
	{
		if (isset(self::$env_vars[$key])) {
			$value = self::$env_vars[$key];

			$use_strict = $strict || ($default === null);
			if ($use_strict == true && is_string($value) && trim($value) === '') {
				return $default;
			}

			return $value;
		}

		return $default;
	}

	/**
	 * Get environment variable as boolean
	 */
	public static function get_bool($key, $default = false)
	{
		$value = self::get($key, $default, true);

		if (is_bool($value)) {
			return $value;
		}

		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * Get environment variable as integer
	 */
	public static function get_int($key, $default = 0)
	{
		$value = self::get($key, $default, true);

		return (int) $value;
	}

	/**
	 * Get environment variable as float
	 */
	public static function get_float($key, $default = 0.0)
	{
		$value = self::get($key, $default, true);

		return (float) $value;
	}

	/**
	 * Get environment variable as array (comma-separated)
	 */
	public static function get_array($key, $default = array(), $separator = ',')
	{
		$value = self::get($key, $default, true);

		if (is_string($value)) {
			return array_map('trim', explode($separator, $value));
		}

		return $default;
	}

	/**
	 * Get environment variable as JSON decoded array/object
	 */
	public static function get_json($key, $default = null, $associative = true)
	{
		$value = self::get($key, $default, true);

		if (is_string($value)) {
			$decoded = json_decode($value, $associative);
			if (json_last_error() === JSON_ERROR_NONE) {
				return $decoded;
			}
		}

		return $default;
	}

	private static function get_file_path($enviorment)
	{
		$file_path = FCPATH . '../.env';

		if ($enviorment != null) {
			$env_file_path = "{$file_path}.{$enviorment}";
			if (file_exists($env_file_path)) {
				return $env_file_path;
			}
		}

		if (file_exists($file_path)) {
			return $file_path;
		}

		return null;
	}
}
