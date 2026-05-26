<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| 1. Pull in the Global Framework Configuration
|--------------------------------------------------------------------------
*/

$hook = [];
if (file_exists(MGRPATH . 'config/hooks.php')) {
	include MGRPATH . 'config/hooks.php';
}
