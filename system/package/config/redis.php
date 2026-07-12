<?php

defined('BASEPATH') or exit('No direct script access allowed');

$config['socket_type'] = mgr_env('LIB_REDIS_SOCKET_TYPE', 'tcp');
$config['host']        = mgr_env('LIB_REDIS_HOST', '127.0.0.1');
$config['password']    = mgr_env('LIB_REDIS_PASSWORD', null);
$config['port']        = mgr_env_int('LIB_REDIS_PORT', 6379);
$config['timeout']     = mgr_env_int('LIB_REDIS_TIMEOUT', 10);
$config['database']    = mgr_env_int('LIB_REDIS_DATABASE', 0);
$config['channel_prefix']    = mgr_env('LIB_REDIS_CHANNEL_PREFIX', '');
$config['default_ttl']       = mgr_env_int('CACHE_DEFAULT_TTL', 600);
