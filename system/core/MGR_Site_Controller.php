<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once MGRPATH . 'core/MGR/Controller/Dispatch_guard.php';

/**
 * Opt-in web-controller base that guards dispatch against an uncaught
 * exception. A project only gets this by extending it (or
 * APP_Site_Controller) — never on MGR_Controller, never a global handler.
 */
class MGR_Site_Controller extends MGR_Controller
{
	use MGR_Controller_Dispatch_guard;
}
