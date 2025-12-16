<?php
defined('BASEPATH') or exit('No direct script access allowed');

$config['host']        = mngr_env('WEBSOCKET_HOST', '0.0.0.0');
$config['port']        = mngr_env_int('WEBSOCKET_PORT', 9000);
$config['max_connections'] = mngr_env_int('WEBSOCKET_MAX_CON', 1000);
$config['max_connections_per_ip'] = mngr_env_int('WEBSOCKET_MAX_CON_IP', 100);
