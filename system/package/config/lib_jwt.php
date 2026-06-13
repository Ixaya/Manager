<?php

$active_config = mgr_env('LIB_JWT_ACTIVE_CONFIG', 'default');

// Config
$jwt_config['default']['secret'] = mgr_env('LIB_JWT_DEFAULT_SECRET');
$jwt_config['default']['algorithm'] = mgr_env('LIB_JWT_DEFAULT_ALGORITHM', 'HS256');
$jwt_config['default']['expiry'] = mgr_env('LIB_JWT_DEFAULT_EXPIRY', 600);

// Secret first time setup
// # HS256 :: openssl rand -base64 32
// # HS384 :: openssl rand -base64 48
// # HS512 :: openssl rand -base64 64
