<?php

defined('BASEPATH') or exit('No direct script access allowed');

require MGRPATH . "core/MGR_Site_Controller.php";

/**
 * Minimal web page base controller — no theme, no session, no CMS state.
 * Extend it directly, or copy the pattern into a project-specific base
 * controller once real theming/session needs show up.
 */
class APP_Site_Controller extends MGR_Site_Controller
{
	public function __construct()
	{
		$this->_container = 'site';
		parent::__construct();
	}
}
