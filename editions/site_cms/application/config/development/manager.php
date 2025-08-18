<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');


//'dev' for development or 'prod' for production
$config['edition']        = mngr_env('MNGR_EDITION', 'dev');

$config['languages']      = mngr_env_array('MNGR_LANGUAGES', ['english', 'spanish']);

$config['project_name']   = mngr_env('MNGR_PROJECT_NAME', 'Manager');
$config['copyright']      = mngr_env('MNGR_COPYRIGHT', '&copy;  Ixaya Business SA de CV 2020, All Rights Reserved.');

$config['cache_enable']   = mngr_env_bool('MNGR_CACHE_ENABLE', false);
$config['cache_time']     = mngr_env_int('MNGR_CACHE_TIME', 5);

$config['rest_time_zone'] = mngr_env('MNGR_REST_TIME_ZONE', null); // UTC | null (System default)

$config['migration_db'] = mngr_env_array('MNGR_MIGRATION_DB', ['default']);

?>