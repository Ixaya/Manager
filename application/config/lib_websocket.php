<?php

defined('BASEPATH') or exit('No direct script access allowed');

$config['url'] = mgr_env('WEBSOCKET_URL', 'ws://localhost:9000');
$config['host'] = mgr_env('WEBSOCKET_HOST', '0.0.0.0');
$config['port'] = mgr_env_int('WEBSOCKET_PORT', 9000);
$config['max_connections'] = mgr_env_int('WEBSOCKET_MAX_CON', 1000);
$config['max_connections_per_ip'] = mgr_env_int('WEBSOCKET_MAX_CON_IP', 100);
$config['log_level'] = mgr_env('WEBSOCKET_LOG_LEVEL', 'Notice'); //'Debug'|'Info'|'Notice'|'Warning'|'Error'|'Critical'|'Alert'|'Emergency
$config['jwt_audience'] = mgr_env('WEBSOCKET_JWT_AUDIENCE', 'websocket');
