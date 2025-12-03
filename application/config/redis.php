<?php

$config['socket_type'] = mngr_env('LIB_REDIS_SOCKET', 'tcp');
$config['host']        = mngr_env('LIB_REDIS_SOCKET', '127.0.0.1');
$config['password']    = mngr_env('LIB_REDIS_SOCKET', NULL);
$config['port']        = mngr_env_int('LIB_REDIS_SOCKET', 6379);
$config['timeout']     = mngr_env_int('LIB_REDIS_SOCKET', 10);
$config['database']     = mngr_env_int('LIB_REDIS_DATABASE', 0);
