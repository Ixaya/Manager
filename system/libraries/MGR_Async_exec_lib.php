<?php

defined('BASEPATH') or exit('No direct script access allowed');

class MGR_Async_exec_lib
{
	/**
	 * Build and execute a CLI call to a library function via the manager/tools/cli_exec.
	 *
	 * Arguments are treated as routing segments (CI-style), URL-encoded for compatibility,
	 * then safely shell-escaped at execution time.
	 *
	 * @param string      $module   Module name
	 * @param string      $library  Library name
	 * @param string      $function Function name
	 * @param string|null $identifier Optional model identifier
	 * @return void
	 */
	public function cli_run_lib($module, $library, $function, $identifier = null)
	{
		$args = [
			$module,
			$library,
			$function
		];

		// Default log name
		$log_name = implode('_', $args);

		if ($identifier !== null) {
			$args[] = $identifier;
		}

		$this->cli_run_uri('manager/tools/cli_exec', $args, $log_name);
	}

	/**
	 * Execute a CLI command based on a CodeIgniter-style URI.
	 *
	 * The URI is treated as a routing path (e.g. "module/api/v1/controller/method")
	 * and is split into individual routing segments.
	 *
	 * When not explicitly provided, the log file base name is generated from the
	 * URI path segments.
	 *
	 * @param string      $uri      CodeIgniter-style URI path (no leading slash)
	 * @param array|string|int|float|bool|null  $args Additional routing arguments
	 * @param string|null $log_name Optional base name for the log file
	 * @return void
	 */
	public function cli_run_uri($uri, $args = null, $log_name = null)
	{
		$uri_segments = explode('/', trim($uri, '/'));
		$final_args = $uri_segments;

		// Merge routing path + arguments
		if (is_array($args)) {
			$final_args = array_merge($final_args, $args);
		} elseif (is_scalar($args)) {
			$final_args[] = $args;
		}

		// Default log name
		if ($log_name === null) {
			$log_name = implode('_', $uri_segments);
		}

		$this->cli_run($final_args, $log_name);
	}
	/**
	 * Execute a CLI command asynchronously using bash and nohup.
	 *
	 * Expects arguments as an ordered array. Each argument is URL-encoded to match
	 * CodeIgniter routing semantics, then shell-escaped individually for OS safety.
	 * Log file names are normalized to lowercase alphanumeric characters and underscores.
	 *
	 * @param array|null  $args     CLI arguments (one value per positional argument)
	 * @param string      $log_name Base name for the log file
	 * @return void
	 */
	public function cli_run($args = null, $log_name = 'cli_run_async')
	{
		// Escape arguments
		// [PHP 8 MIGRATION] Added explicit (string) cast to urlencode
		$urlencode_args = array_map(
			fn ($v) => urlencode((string) $v),
			$args
		);

		$escaped_args = implode(' ', array_map('escapeshellarg', $urlencode_args));

		// Escape script path
		$base_path = mgr_app_file_path();
		$escaped_shell_script = escapeshellarg("{$base_path}bin/cli_run.sh");

		$log_name = $this->normalize_log_name((string) $log_name);
		$log_folder = $base_path . '../logs/cli/';

		$log_file_path = "{$log_folder}{$log_name}.log";

		// Ensure log directory exists
		if (!file_exists($log_folder)) {
			mkdir($log_folder, 0755, true);
		}

		// Ensure log file exists
		if (!file_exists($log_file_path)) {
			touch($log_file_path);
		}

		$escaped_log_path = escapeshellarg($log_file_path);

		$cmd = "nohup /bin/bash {$escaped_shell_script} {$escaped_args} >> {$escaped_log_path} 2>&1 &";

		log_message('debug', 'Executing CLI command: ' . $cmd);
		$stdout = exec($cmd);
		log_message('debug', 'Command output: ' . $stdout);
	}

	/**
	 * Execute a CodeIgniter library method via the CLI runner.
	 *
	 * This is the single audited entry point for all framework-level CLI executions.
	 * It resolves the target library, validates the callable method, and invokes it
	 * with the provided identifier.
	 *
	 * All arguments are expected to come from a CLI context (typically via cli_run.sh)
	 * and may be URL-encoded to match CodeIgniter routing semantics.
	 *
	 * @param string $module   Module name containing the library
	 * @param string $library  Library path or class name
	 * @param string $function Public method to invoke on the library
	 * @param string|null $identifier Optional identifier passed to the method
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If the target library or method is invalid
	 * @throws \Throwable        If the invoked method throws
	 *
	 */

	public function run_library_call($module, $library, $function, $identifier)
	{
		log_message('debug', "CLI run: {$module}/{$library}/{$function}/{$identifier}");

		try {
			$module   = urldecode($module);
			$library  = urldecode($library);
			$function = urldecode($function);

			$CI = &get_instance();
			$CI->load->library($module . '/' . $library);

			if (strpos($library, '/') !== false) {
				$library = basename($library);
			}

			if (!method_exists($CI->{$library}, $function)) {
				throw new RuntimeException('Invalid CLI target');
			}

			$CI->{$library}->{$function}($identifier);
		} catch (Throwable $e) {
			log_message('error', 'CLI run failed: ' . $e->getMessage());
			throw $e; // optional: fail fast in CLI
		}
	}


	/**
	 * Normalize a log file base name.
	 *
	 * Converts to lowercase, replaces spaces with underscores, and removes all
	 * characters except letters, numbers, and underscores.
	 *
	 * @param string $name Raw log name
	 * @return string Normalized, filesystem-safe log name
	 */
	protected function normalize_log_name(string $name): string
	{
		$name = strtolower($name);
		$name = str_replace(' ', '_', $name);
		$name = preg_replace('/[^a-z0-9_]/', '', $name);

		return trim($name, '_');
	}
}
