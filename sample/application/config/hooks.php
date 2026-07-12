<?php

defined('BASEPATH') or exit('No direct script access allowed');

$hook = [];
if (file_exists(MGRPATH . 'config/hooks.php')) {
	include MGRPATH . 'config/hooks.php';
}

/*
| -----------------------------------------------------------------------
| MGR Framework Bootstrap (optional)
| -----------------------------------------------------------------------
| Uncomment if you need to force-load MGR libraries into memory before
| any controller runs — for example if a library is used in a base
| controller constructor or another hook.
|
| By default MGR libraries are lazy-loaded via $this->load->library()
| which is preferred. Only use this if you have a specific early-load need.
| -----------------------------------------------------------------------
*/

// $hook['pre_controller'][] = [
// 	'class'    => 'MGR_Bootstrap',
// 	'function' => 'init',
// 	'filename' => 'MGR_Bootstrap.php',
// 	'filepath' => APPMGRPATH . 'hooks/',
// ];
