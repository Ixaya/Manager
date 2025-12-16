<?php

class MY_Exceptions extends CI_Exceptions
{
	public function show_error($heading, $message, $template = 'error_general', $status_code = 500)
	{
		$data = [
			'status' => -1,
			'error'  => $heading,
			'details' => is_array($message) ? implode("\n", $message) : $message
		];

		$this->show_error_data($data, $status_code);

		return '';
	}

	public function show_exception($exception)
	{
		$data = [
			'status'  => -1,
			'error'   => get_class($exception),
			'message' => $exception->getMessage(),
			'file'    => $this->clean_file_path($exception->getFile()),
			'line'    => $exception->getLine()
		];

		$this->show_error_data($data, 500);
	}

	public function show_php_error($severity, $message, $filepath, $line)
	{
		$data = [
			'status'   => -1,
			'severity' => $severity,
			'message'  => $message,
			'file'     => $this->clean_file_path($filepath),
			'line'     => $line
		];

		$this->show_error_data($data, 500);
	}

	private function show_error_data($data, $error_code)
	{
		if (is_cli()) {
			echo "**ERROR($error_code)**\r\n";
			foreach ($data as $k => $v) {
				echo $k . ': ' . $v . "\r\n";
			}
		} else {
			$this->_add_cors();

			if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
				http_response_code(200);
			} else {
				header('Content-Type: application/json', true, $error_code);
				echo json_encode($data);
			}
		}

		exit;
	}

	private function clean_file_path($filepath)
	{
		$root = dirname(FCPATH);
		if (strpos($filepath, $root) === 0) {
			return substr($filepath, strlen($root) + 1);
		}

		return $filepath; // file outside project
	}

	/**
	 * Adds permissive CORS headers for HTTP access control (CORS)
	 *
	 * @access protected
	 * @return void
	 */
	protected function _add_cors()
	{
		$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

		// No Origin header = same-origin request, no CORS needed
		if (empty($origin)) {
			return;
		}
		
		header('Access-Control-Allow-Origin: *');

		if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
			// Echo back requested headers (more compatible with older browsers)
			header('Access-Control-Allow-Headers: ' . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
		} else {
			header('Access-Control-Allow-Headers: *');
		}

		header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
	}
}
