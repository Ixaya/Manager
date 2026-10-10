<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once MGRPATH . 'core/MGR/Controller/Dispatch_guard.php';

/**
 * Base for command-line controllers (CLI commands, crons, background jobs).
 * Refuses HTTP, and renders an uncaught exception to stderr with exit 1
 * even where display_errors is off.
 */
class MGR_Cli_Controller extends CI_Controller
{
	use MGR_Controller_Dispatch_guard;

	public function __construct()
	{
		parent::__construct();

		if (!is_cli()) {
			show_error('Direct access is not allowed. This is a command line tool, use the terminal', 403);
		}
	}
}
