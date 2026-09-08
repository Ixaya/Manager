<?php

defined('BASEPATH') or exit('No direct script access allowed');

require MGRPATH . "libraries/MGR_Mailing_lib.php";

class Auth_mailing extends MGR_Mailing_lib
{
	protected array $templates = ['welcome', 'password_reset'];
}
