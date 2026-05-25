<?php

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

//'dev' for development or 'prod' for production
$config['edition']        = mgr_env('MGR_EDITION', 'dev');

$config['languages']      = mgr_env_array('MGR_LANGUAGES', ['english', 'spanish']);

$config['cache_enable']   = mgr_env_bool('MGR_CACHE_ENABLE', false);
$config['cache_time']     = mgr_env_int('MGR_CACHE_TIME', 600);

$config['rest_time_zone'] = mgr_env('MGR_REST_TIME_ZONE', null); // UTC | null (System default)

$config['migration_db'] = mgr_env_array('MGR_MIGRATION_DB', ['default']);
