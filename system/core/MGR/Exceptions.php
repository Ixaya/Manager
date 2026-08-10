<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Renders CodeIgniter's error output as JSON for API clients.
 *
 * Every path splits on should_disclose_details(): internals for a developer or
 * the CLI, a fixed generic envelope for everyone else. Detail is always logged.
 */
class MGR_Exceptions extends CI_Exceptions
{
	protected $api_only = true;

	// Statement-execution calls whose native warning preempts CI's db_debug report.
	// mysqli is absent on purpose: it reports through CI already (MYSQLI_REPORT_OFF).
	protected array $statement_warning_prefixes = ['pg_query()', 'SQLite3::query()', 'SQLite3::exec()'];

	/**
	 * Renders a 404. Logging is forced off — 404s are left to the server logs.
	 *
	 * @param  string $page
	 * @param  bool   $log_error Ignored.
	 * @return void
	 */
	public function show_404($page = '', $log_error = false)
	{
		parent::show_404($page, false);
	}

	/**
	 * Renders a framework error, including 404s and CI's parsed DB errors.
	 * Logs 5xx that no caller logged first.
	 *
	 * @param  string       $heading
	 * @param  string|array $message
	 * @param  string       $template    'error_db' switches to the parsed DB envelope.
	 * @param  int          $status_code
	 * @return mixed '' once rendered; CI's value if handed to the HTML views.
	 */
	public function show_error($heading, $message, $template = 'error_general', $status_code = 500)
	{
		// Nothing else logs this path — CI's show_error() has no logging of its own.
		// error_db is excluded: DB_driver already logged before rendering.
		if ($status_code >= 500 && $template !== 'error_db') {
			log_message('error', $heading . ': ' . (is_array($message) ? implode(' ', $message) : $message));
		}

		if ($this->validate_html_accept()) {
			return parent::show_error($heading, $message, $template, $status_code);
		}

		// 5xx is an internal failure the client can do nothing with; 4xx is deliberate and
		// client-facing, and this method is also the 404 renderer via parent::show_404().
		if ($status_code >= 500 && !$this->should_disclose_details()) {
			$this->show_error_data($this->build_generic_error(), $status_code);

			return '';
		}

		if ($template == 'error_db') {
			$data = $this->_parse_db_error($message);
		} else {
			$data = [
				'status'  => 0,
				'message' => $message,
				'error'   => ['heading' => $heading],
			];
		}


		$this->show_error_data($data, $status_code);

		return '';
	}

	/**
	 * Renders an uncaught exception. Always HTTP 500.
	 *
	 * @param  Throwable $exception
	 * @return mixed Nothing once rendered; CI's value if handed to the HTML views.
	 */
	public function show_exception($exception)
	{
		if ($this->validate_html_accept()) {
			return parent::show_exception($exception);
		}

		if (!$this->should_disclose_details()) {
			$this->show_error_data($this->build_generic_error(), 500);

			return;
		}

		$data = [
			'status'  => 0,
			'message' => $exception->getMessage(),
			'error'   => [
				'class' => get_class($exception),
				'file'  => $this->clean_file_path($exception->getFile()),
				'line'  => $exception->getLine(),
			],
		];

		$this->show_error_data($data, 500);
	}

	/**
	 * Renders a PHP error, notice or warning that got past error_reporting().
	 *
	 * Only reached while display_errors is on, so it needs no disclosure gate
	 * of its own.
	 *
	 * @param  int    $severity
	 * @param  string $message
	 * @param  string $filepath
	 * @param  int    $line
	 * @return void
	 */
	public function show_php_error($severity, $message, $filepath, $line)
	{
		if ($this->validate_html_accept()) {
			parent::show_php_error($severity, $message, $filepath, $line);
			return;
		}

		// Yields to CI's db_debug report, which names the failing SQL.
		if ($this->is_non_fatal_statement_warning($severity, $message)) {
			return;
		}

		$data = [
			'status'  => 0,
			'message' => $message,
			'error'   => [
				'severity' => $severity,
				'file'     => $this->clean_file_path($filepath),
				'line'     => $line,
			],
		];

		$this->show_error_data($data, 500);
	}

	/**
	 * Sends $data as the response body and stops execution.
	 *
	 * @param  array $data
	 * @param  int   $error_code HTTP status; ignored under CLI.
	 * @return void
	 */
	protected function show_error_data($data, $error_code)
	{
		if (is_cli()) {
			echo "**ERROR($error_code)**\r\n";
			foreach ($data as $k => $v) {
				if (is_array($v)) {
					foreach ($v as $i => $line) {
						echo $k . '[' . $i . ']: ' . $line . "\r\n";
					}
				} else {
					echo $k . ': ' . $v . "\r\n";
				}
			}
		} else {
			// If something already wrote to the output buffer, do not emit again.
			// Prevents multiple JSON payloads in the response.
			if (ob_get_length() > 0) {
				return;
			}

			$is_options = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS');
			$this->_add_cors($is_options);

			// Never $error_code: a non-2xx preflight is not cached, so the browser would
			// re-send it before every request. 204 is what a routed URL answers.
			if ($is_options) {
				http_response_code(204);
			} else {
				header('Content-Type: application/json', true, $error_code);
				echo json_encode($data);
			}
		}

		// Stop normal execution; shutdown handlers may still run.
		exit;
	}

	/**
	 * The envelope a suppressed 5xx returns: no error/file/line keys, so failure
	 * modes cannot be told apart by response shape.
	 *
	 * @return array{status: int, message: string}
	 */
	protected function build_generic_error(): array
	{
		return [
			'status'  => 0,
			'message' => 'An unexpected error occurred.',
		];
	}

	/**
	 * Whether this client may be shown error internals (class, message, file, line, SQL).
	 *
	 * @return bool True under CLI or while display_errors is on.
	 */
	protected function should_disclose_details(): bool
	{
		// CI's own display_errors test, copied so the two cannot disagree.
		// is_cli() is separate: CLI runs with display_errors off, but tools need the output.
		return is_cli()
			|| (bool) str_ireplace(['off', 'none', 'no', 'false', 'null'], '', (string) ini_get('display_errors'));
	}

	/**
	 * Whether a PHP error is a driver's warning about a statement that failed.
	 *
	 * Non-fatal only: _error_handler() exits on a fatal whatever this returns,
	 * which would leave the response body empty.
	 *
	 * @param  int    $severity
	 * @param  string $message
	 * @return bool
	 */
	protected function is_non_fatal_statement_warning($severity, $message): bool
	{
		if ($severity !== E_WARNING && $severity !== E_NOTICE) {
			return false;
		}

		foreach ($this->statement_warning_prefixes as $prefix) {
			if (strpos($message, $prefix) === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Makes a filesystem path project-relative so responses disclose no server layout.
	 *
	 * CI strips APPPATH/BASEPATH only; the framework matches neither, so a path
	 * that has not been through here is absolute.
	 *
	 * @param  string $filepath
	 * @return string The path unchanged if it falls outside the project root.
	 */
	protected function clean_file_path($filepath)
	{
		$root = dirname(FCPATH);
		if (strpos($filepath, $root) === 0) {
			return substr($filepath, strlen($root) + 1);
		}

		return $filepath;
	}

	/**
	 * Whether to hand this request to CI's HTML error views instead of rendering JSON.
	 *
	 * @return bool Always false while $api_only is true.
	 */
	protected function validate_html_accept()
	{
		if ($this->api_only === true) {
			return false;
		}

		$acceptHeader = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';

		return (strpos($acceptHeader, 'text/html') !== false);
	}

	/**
	 * Adds CORS headers to an error response, when the caller sent an Origin.
	 *
	 * @param  bool $is_options Preflight requests also get the headers/methods/max-age set.
	 * @return void
	 */
	protected function _add_cors(bool $is_options): void
	{
		$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

		// No Origin header = same-origin request, no CORS needed
		if (empty($origin)) {
			return;
		}

		// If we want to allow any domain to access the API
		if (mgr_env_bool('REST_ALLOW_ANY_CORS_DOMAIN', false) === true) {
			header('Access-Control-Allow-Origin: *');
		} else {
			// If the origin domain is in the allowed_cors_origins list, then add the Access Control headers
			if (in_array($origin, mgr_env_array('REST_ALLOWED_CORS', []))) {
				header('Access-Control-Allow-Origin: ' . $origin);
			} else {
				return;
			}
		}

		if (!$is_options) {
			return;
		}

		if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
			// Echo back requested headers (more compatible with older browsers)
			header('Access-Control-Allow-Headers: ' . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
		} else {
			header('Access-Control-Allow-Headers: *');
		}

		$cors_max_age =  mgr_env_int('REST_CORS_MAX_AGE', 86400);
		if ($cors_max_age > 0) {
			header('Access-Control-Max-Age: ' . $cors_max_age);
		}

		header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
	}

	/**
	 * Turns CI's multi-line database error text into the error_db envelope.
	 *
	 * @param  string|array $message CI's 'Error Number:' / 'Filename:' / 'Line Number:' block.
	 * @return array{status: int, message: string, error: array{heading: string, errno: int|string|null, file: ?string, line: ?int, query?: string}}
	 */
	protected function _parse_db_error($message)
	{
		$parts = is_array($message) ? $message : explode("\n", $message);

		$data = [
			'status'  => 0,
			'message' => 'A Database Error Occurred',
			'error'   => [
				'heading' => 'A Database Error Occurred',
				'errno'   => null,
				'file'    => null,
				'line'    => null,
			],
		];

		// The only caller (DB_driver::display_error()) always builds this in the
		// same order — errno, message, SQL — so position, not keyword-sniffing,
		// is what's safe to rely on here.
		$message_set = false;

		foreach ($parts as $part) {
			$part = trim($part);

			if (preg_match('/^Error Number:\s*(.*)$/i', $part, $m)) {
				$data['error']['errno'] = $m[1] !== '' ? $m[1] : null;
			} elseif (preg_match('/^Filename:\s*(.+)$/i', $part, $m)) {
				$data['error']['file'] = $this->clean_file_path(trim($m[1]));
			} elseif (preg_match('/^Line Number:\s*(\d+)$/i', $part, $m)) {
				$data['error']['line'] = (int) $m[1];
			} elseif (!$message_set) {
				if (!empty($part)) {
					$data['message'] = $this->clean_driver_message($part);
				}
				$message_set = true;
			} elseif (!empty($part)) {
				$data['error']['query'] = $part;
			}
		}

		return $data;
	}

	/**
	 * Strips driver noise already captured elsewhere (`error.errno`,
	 * `error.query`): libpq's `LINE N:` echo (`postgre` and `pdo/pgsql`
	 * both hit this), FreeTDS's trailing `[<code>] (severity <n>) [<sql>]`
	 * block. Other messages pass through.
	 *
	 * @param  string $message
	 * @return string
	 */
	protected function clean_driver_message(string $message): string
	{
		if (preg_match('/^(ERROR|WARNING|NOTICE|FATAL|PANIC):\s+/', $message)) {
			$message = preg_replace('/\nLINE \d+:.*/s', '', $message);

			return preg_replace('/^(ERROR|WARNING|NOTICE|FATAL|PANIC):\s+/', '$1: ', $message);
		}

		return preg_replace('/\s*\[\d+\]\s*\(severity\s*\d+\)\s*\[.*\]$/is', '', $message);
	}
}
