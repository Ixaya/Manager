<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$email_active_config = 'default';
$email_from_name 		 = "_from_name_";
$email_bbc_enabled 	 = FALSE;

$email_base_config['useragent'] = "_user_agent_";
$email_base_config['mailtype'] = 'html';
$email_base_config['wordwrap'] = TRUE;
$email_base_config['charset']  = 'utf-8';
$email_base_config['newline']  = '\r\n';


// Config Sendmail
// $email_config['default']['mailpath'] = "/usr/sbin/sendmail";
// $email_config['default']['protocol'] = 'sendmail';

// Config SMTP
// $email_config['default']['protocol'] = 'smtp';
// $email_config['default']['smtp_host'] = '_host_';
// $email_config['default']['smtp_port'] = _port_;
// $email_config['default']['smtp_crypto'] = 'ssl/tls/none';
// $email_config['default']['smtp_user'] = '_user_';
// $email_config['default']['smtp_pass'] = '_pass_';
