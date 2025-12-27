<?php
defined('BASEPATH') or exit('No direct script access allowed');

$config['url'] = mngr_env('WEBSOCKET_URL', 'ws://localhost:9000');
$config['host'] = mngr_env('WEBSOCKET_HOST', '0.0.0.0');
$config['port'] = mngr_env_int('WEBSOCKET_PORT', 9000);
$config['max_connections'] = mngr_env_int('WEBSOCKET_MAX_CON', 1000);
$config['max_connections_per_ip'] = mngr_env_int('WEBSOCKET_MAX_CON_IP', 100);
$config['log_level'] = mngr_env('WEBSOCKET_LOG_LEVEL', 'Notice'); //'Debug'|'Info'|'Notice'|'Warning'|'Error'|'Critical'|'Alert'|'Emergency
$config['jwt_audience'] = mngr_env('WEBSOCKET_JWT_AUDIENCE', 'websocket');