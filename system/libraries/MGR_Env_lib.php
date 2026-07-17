<?php

class MGR_Env_lib
{
	protected static $loaded = false;
	protected static $env_vars = [];

	public static function load($enviorment = null)
	{
		if (self::$loaded) {
			return;
		}

		self::load_env($enviorment);
		self::load_env($enviorment, true);

		self::$loaded = true;
	}
	public static function load_env($enviorment = null, $private = false)
	{
		$file_path = static::get_file_path($enviorment, $private);

		self::$env_vars = array_merge(self::$env_vars, self::parse_file($file_path));
	}

	/**
	 * Parse a dotenv-style file into key=>value pairs.
	 * Same rules as the runtime loader: '#' comments skipped, quotes
	 * stripped, empty values dropped.
	 *
	 * @return array<string, string>
	 */
	protected static function parse_file(?string $file_path): array
	{
		if ($file_path === null || !is_readable($file_path)) {
			return [];
		}

		$vars = [];
		$lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		foreach ($lines as $line) {
			if (strpos(trim($line), '#') === 0) {
				continue;
			}

			if (strpos($line, '=') === false) {
				continue;
			}

			list($key, $value) = explode('=', $line, 2);
			$value = trim(trim($value), '"\'');

			// Only a truly empty value means unset — !empty() would also drop
			// a literal KEY=0, since "0" is falsy in PHP.
			if ($value !== '') {
				$vars[trim($key)] = $value;
			}
		}

		return $vars;
	}

	/**
	 * Diagnostic only — reports which layer get() would answer from,
	 * without returning the value. Walks the same chain in the same
	 * precedence order: process env, $_ENV, .env.priv, .env.
	 *
	 * @return array{source: string, set: bool, length: int}
	 */
	public static function resolve_source(string $key, ?string $enviorment = null): array
	{
		$value = getenv($key);
		if ($value !== false) {
			return ['source' => 'process-env', 'set' => true, 'length' => strlen($value)];
		}

		if (isset($_ENV[$key])) {
			return ['source' => '$_ENV', 'set' => true, 'length' => strlen((string) $_ENV[$key])];
		}

		// Priv file is loaded second at runtime, so it wins over .env in the merged cache.
		$priv = self::parse_file(static::get_file_path($enviorment, true));
		if (isset($priv[$key])) {
			return ['source' => '.env.priv', 'set' => true, 'length' => strlen($priv[$key])];
		}

		$base = self::parse_file(static::get_file_path($enviorment, false));
		if (isset($base[$key])) {
			return ['source' => '.env', 'set' => true, 'length' => strlen($base[$key])];
		}

		return ['source' => 'MISSING', 'set' => false, 'length' => 0];
	}

	public static function get($key, $default = null, $strict = false)
	{
		$value = getenv($key);
		if ($value !== false) {
			return self::process_value($value, $default, $strict);
		}

		if (isset($_ENV[$key])) {
			return self::process_value($_ENV[$key], $default, $strict);
		}

		if (isset(self::$env_vars[$key])) {
			$value = self::$env_vars[$key];
			return self::process_value($value, $default, $strict);
		}

		return $default;
	}

	/**
	 * Get a required environment variable — fails loud instead of silently
	 * defaulting.
	 *
	 * @throws RuntimeException when the key is unset or resolves empty.
	 */
	public static function get_required(string $key): string
	{
		$value = self::get($key, null, true);

		if ($value === null || trim((string) $value) === '') {
			throw new RuntimeException(
				"MGR_Env_lib: required env var '{$key}' is not set or empty — check the instance env files (manager/tools/env_check)."
			);
		}

		return (string) $value;
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
	public static function get_array($key, $default = [], $separator = ',')
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

	protected static function get_file_path($enviorment, $private = false)
	{
		$file_path = FCPATH . '../.env';
		$suffix = $private ? '.priv' : '';

		if ($enviorment != null) {
			$env_file_path = "{$file_path}.{$enviorment}{$suffix}";
			if (file_exists($env_file_path)) {
				return $env_file_path;
			}
		}

		$file_path_suffix = "{$file_path}{$suffix}";
		if (file_exists($file_path_suffix)) {
			return $file_path_suffix;
		}

		return null;
	}

	protected static function process_value($value, $default, $strict)
	{
		$use_strict = $strict || ($default === null);
		if ($use_strict == true && is_string($value) && trim($value) === '') {
			return $default;
		}

		return $value;
	}
}
