<?php
// application/controllers/Websockets.php

defined('BASEPATH') or exit('No direct script access allowed');

class Websockets extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();

		if (!is_cli()) {
			show_error('This controller can only be accessed via CLI');
		}
	}

	public function generate_link($user_identifier = null, $channel = null)
	{
		$this->load->library('websocket_lib');
		$link = $this->websocket_lib->generateLink($user_identifier, $channel);

		echo "{$link}\r\n";
	}

	/**
	 * Start WebSocket server
	 * Usage: ./bin/cli_run.sh manager websockets serve
	 */
	public function serve()
	{
		$this->load->library('websocket_lib');
		$this->websocket_lib->serve();
	}
}
