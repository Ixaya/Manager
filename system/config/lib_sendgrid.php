<?php

defined('BASEPATH') or exit('No direct script access allowed');

$active_config = mgr_env('LIB_SENDGRID_ACTIVE_CONFIG', 'default');

$config['default']['api_key']             = mgr_env('LIB_SENDGRID_API_KEY', null);
$config['default']['sandbox_mode']        = mgr_env_bool('LIB_SENDGRID_SANDBOX_MODE', false);
$config['default']['default_from_email']  = mgr_env('LIB_SENDGRID_DEFAULT_FROM_EMAIL', null);
$config['default']['default_from_name']   = mgr_env('LIB_SENDGRID_DEFAULT_FROM_NAME', null);

// {"logical_name":"d-xxxxxxxx..."} — template ids are account-scoped, never hardcode a real one
$config['default']['templates'] = mgr_env_json('LIB_SENDGRID_TEMPLATES', []);
