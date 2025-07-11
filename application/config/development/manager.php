<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');


//'dev' for devleopment or 'prod' for production
$config['edition'] = 'dev';
// $config['edition'] = 'prod';

$config['frontend_theme'] = 'default';
// $config['frontend_theme'] = 'soon';

$config['admin_theme'] = 'default';
// $config['admin_theme'] = 'default2';

$config['languages'] = ['english','spanish'];

$config['project_name'] = 'Manager';
//Ixaya/Manager HMVC PHP Framework
$config['copyright'] = '&copy;  Ixaya Business SA de CV 2020, All Rights Reserved.';

$config['cache_enable'] = FALSE;
$config['cache_time'] = 5;

$config['rest_time_zone'] = null; //System default
// $config['rest_time_zone'] = 'UTC'; //Specify UTC

$config['captcha_site_key'] = '';
$config['captcha_secret_key'] = '';

?>
