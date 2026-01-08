<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$email_active_config = mngr_env('LIB_MAIL_ACTIVE_CONFIG', 'default');
$email_from_name     = mngr_env('LIB_MAIL_FROM_NAME', null); // "_from_name_"
$email_bbc_enabled   = mngr_env_bool('LIB_MAIL_BBC_ENABLED', false);

$email_base_config['useragent'] = mngr_env('LIB_MAIL_USERAGENT', null); // "_user_agent_"
$email_base_config['mailtype'] = 'html';
$email_base_config['wordwrap'] = TRUE;
$email_base_config['charset']  = 'utf-8';
$email_base_config['newline']  = '\r\n';

// Config
$email_config['default']['protocol']    = mngr_env('LIB_MAIL_CF_DEFAULT_PROTOCOL', 'smtp'); // 'smtp/sendmail'
$email_config['default']['smtp_host']   = mngr_env('LIB_MAIL_CF_DEFAULT_SMTP_HOST', null); // '_host_'
$email_config['default']['smtp_port']   = mngr_env_int('LIB_MAIL_CF_DEFAULT_SMTP_PORT', 587); // _port_
$email_config['default']['smtp_crypto'] = mngr_env('LIB_MAIL_CF_DEFAULT_SMTP_CRYPTO', 'none'); // 'ssl/tls/none'
$email_config['default']['smtp_user']   = mngr_env('LIB_MAIL_CF_DEFAULT_SMTP_USER', null); // '_user_'
$email_config['default']['smtp_pass']   = mngr_env('LIB_MAIL_CF_DEFAULT_SMTP_PASS', null); // '_pass_'
$email_config['default']['email_from']  = mngr_env('LIB_MAIL_CF_DEFAULT_EMAIL_FROM', null); // null (use smtp_user if empty)
