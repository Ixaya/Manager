<?php

class MY_Exceptions extends CI_Exceptions
{
	public function show_error($heading, $message, $template = 'error_general', $status_code = 500)
	{
		if (!$this->is_api_cli()) {
			return parent::show_error($heading, $message, $template, $status_code);
		}

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
		if (!$this->is_api_cli()) {
			return parent::show_exception($exception);
		}

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
		$filepath = $this->clean_file_path($filepath);
		if (!$this->is_api_cli()) {
			return parent::show_php_error($severity, $message, $filepath, $line);
		}

		$data = [
			'status'   => -1,
			'severity' => $severity,
			'message'  => $message,
			'file'     => $filepath,
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
			header('Content-Type: application/json', true, $error_code);
			echo json_encode($data);
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

	private function is_api_cli()
	{
		$request_uri = $_SERVER['REQUEST_URI'] ?? '/api/';

		return (strpos($request_uri, '/api/') !== false);
	}
}
