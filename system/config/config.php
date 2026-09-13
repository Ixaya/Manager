<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Enable/Disable System Hooks
|--------------------------------------------------------------------------
| Required: MGR_Bootstrap and any framework-shipped hook depend on this.
*/
$config['enable_hooks'] = true;

/*
|--------------------------------------------------------------------------
| Class Extension Prefix
|--------------------------------------------------------------------------
| Required: the whole MGR_ -> MY_/APP_ alias chain (MY_Config, MY_Router,
| MY_Loader, MY_Model, MY_Controller...) resolves through this value.
*/
$config['subclass_prefix'] = 'MY_';

/*
|--------------------------------------------------------------------------
| Error Logging Directory Path
|--------------------------------------------------------------------------
| Unifies CF_LOG_PATH (legacy, verbatim override) and MGR_LOG_PATH (shared
| log root, gets an /app/ suffix) into the one log_path CI reads.
*/
$cf_log_path  = mgr_env('CF_LOG_PATH');   // legacy override, verbatim; null when unset/empty
$mgr_log_path = mgr_env('MGR_LOG_PATH');  // unified log root; null when unset/empty
if ($cf_log_path !== null) {
	$config['log_path'] = $cf_log_path;
} elseif ($mgr_log_path !== null) {
	$config['log_path'] = rtrim($mgr_log_path, '/') . '/app/';
} else {
	$config['log_path'] = '';
}

/*
|--------------------------------------------------------------------------
| Module Locations
|--------------------------------------------------------------------------
| Defines the paths where CodeIgniter will look for HMVC modules.
| Multiple locations may be defined and will be checked in order.
|
| The first matching module found will be loaded, allowing later
| locations to act as fallbacks.
|
| In this setup:
| - Brand modules are checked first, allowing customer-specific
|   overrides and extensions.
| - Core modules are checked next, providing the shared default
|   implementation.
| - Vendor modules are checked next, providing the base default
|   implementation.
|
| NOTE: The relative path origin is APPPATH/controllers/
*/

$config['modules_locations'] = [
	APPPATH . 'modules/'  => '../modules/',
	MGRPATH . 'package/modules/' => '../' . APPMGRPATH . 'package/modules/'
];

if (defined('BRANDPATH') && defined('BRANDPATH_MODULES_OFFSET')) {
	$config['modules_locations'] = [
		BRANDPATH . 'modules/' => BRANDPATH_MODULES_OFFSET . 'modules/',
	] + $config['modules_locations'];
}
