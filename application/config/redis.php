<?php
defined('BASEPATH') or exit('No direct script access allowed');

$config['socket_type'] = mngr_env('LIB_REDIS_SOCKET', 'tcp');
$config['host']        = mngr_env('LIB_REDIS_HOST', '127.0.0.1');
$config['password']    = mngr_env('LIB_REDIS_PASSWORD', NULL);
$config['port']        = mngr_env_int('LIB_REDIS_PORT', 6379);
$config['timeout']     = mngr_env_int('LIB_REDIS_TIMEOUT', 10);
$config['database']    = mngr_env_int('LIB_REDIS_DATABASE', 0);
$config['channel_prefix']    = mngr_env('LIB_REDIS_CHANNEL_PREFIX', '');
