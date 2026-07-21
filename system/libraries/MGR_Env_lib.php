<?php

class MGR_Env_lib
{
	protected static $loaded = false;
	protected static $env_vars = [];

	/**
	 * Boot the env layer — parses .env / .env.priv (or their $enviorment
	 * suffixed variants) into the merged file cache. Idempotent.
	 */
	public static function load(?string $enviorment = null): void
	{
		if (self::$loaded) {
			return;
		}

		self::load_env($enviorment);
		self::load_env($enviorment, true);

		self::$loaded = true;
	}

	/**
	 * Parse one file (base or priv) and merge it into the file cache.
	 */
	public static function load_env(?string $enviorment = null, bool $private = false): void
	{
		$file_path = static::get_file_path($enviorment, $private);

		self::$env_vars = array_merge(self::$env_vars, self::parse_file($file_path));
	}

	/**
	 * Parse a dotenv-style file into key=>value pairs. Quoting and
	 * empty-value handling happen centrally in process_value(), not here.
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
			$vars[trim($key)] = trim($value);
		}

		return $vars;
	}

	/**
	 * Diagnostic only — reports which source get() would resolve from and
	 * whether it resolves to a value, without returning it. Stops at the
	 * first source that mentions the key even if blank: a blank .env.priv
	 * hides .env in the merged cache, it does not fall through to it.
	 *
	 * @return array{source: string, set: bool, length: int}
	 */
	public static function resolve_source(string $key, ?string $enviorment = null): array
	{
		$value = getenv($key);
		if ($value !== false) {
			return ['source' => 'process-env', 'set' => self::process_value($value, null, true) !== null, 'length' => strlen($value)];
		}

		if (isset($_ENV[$key])) {
			$value = (string) $_ENV[$key];

			return ['source' => '$_ENV', 'set' => self::process_value($value, null, true) !== null, 'length' => strlen($value)];
		}

		$priv = self::parse_file(static::get_file_path($enviorment, true));
		if (isset($priv[$key])) {
			return ['source' => '.env.priv', 'set' => self::process_value($priv[$key], null, true) !== null, 'length' => strlen($priv[$key])];
		}

		$base = self::parse_file(static::get_file_path($enviorment, false));
		if (isset($base[$key])) {
			return ['source' => '.env', 'set' => self::process_value($base[$key], null, true) !== null, 'length' => strlen($base[$key])];
		}

		return ['source' => 'MISSING', 'set' => false, 'length' => 0];
	}

	/**
	 * Resolve an env var: process env → $_ENV → parsed files → $default.
	 */
	public static function get(string $key, mixed $default = null, bool $strict = true): mixed
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
	public static function get_bool(string $key, mixed $default = false): bool
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
	public static function get_int(string $key, mixed $default = 0): int
	{
		$value = self::get($key, $default, true);

		return (int) $value;
	}

	/**
	 * Get environment variable as float
	 */
	public static function get_float(string $key, mixed $default = 0.0): float
	{
		$value = self::get($key, $default, true);

		return (float) $value;
	}

	/**
	 * Get environment variable as array (comma-separated)
	 *
	 * @return array<int, mixed>
	 */
	public static function get_array(string $key, array $default = [], string $separator = ','): array
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
	public static function get_json(string $key, mixed $default = null, bool $associative = true): mixed
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

	/**
	 * Resolve the effective env file path — the $enviorment-suffixed variant
	 * if it exists, else the plain fallback; null if neither exists.
	 */
	protected static function get_file_path(?string $enviorment, bool $private = false): ?string
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

	/**
	 * Normalize a resolved raw value: strip a matched quote pair, then
	 * collapse a blank result to $default (in strict mode, or when
	 * $default is null).
	 */
	protected static function process_value(mixed $value, mixed $default, bool $strict): mixed
	{
		// Strip a matched quote pair before the blank check, so KEY="" collapses
		// like bare KEY=. Only a genuine matching pair is stripped — a stray or
		// mismatched quote (e.g. a password ending in ') survives untouched.
		if (is_string($value)) {
			$length = strlen($value);
			if ($length >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[0] === $value[$length - 1]) {
				$value = substr($value, 1, -1);
			}
		}

		$use_strict = $strict || ($default === null);
		if ($use_strict == true && is_string($value) && trim($value) === '') {
			return $default;
		}

		return $value;
	}
}
