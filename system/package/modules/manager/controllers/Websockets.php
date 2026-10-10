<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once MGRPATH . 'core/MGR_Cli_Controller.php';

class Websockets extends MGR_Cli_Controller
{
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
