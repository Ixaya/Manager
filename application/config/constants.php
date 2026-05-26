<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Pull in the Global Framework Configuration
|--------------------------------------------------------------------------
*/

if (file_exists(MGRPATH . 'config/constants.php')) {
	include MGRPATH . 'config/constants.php';
}

/*
|--------------------------------------------------------------------------
| Manager Constants
|--------------------------------------------------------------------------
*/

defined('LEVEL_CUSTOMER') or define('LEVEL_CUSTOMER', 0);
defined('LEVEL_MEMBER') or define('LEVEL_MEMBER', 1);
defined('LEVEL_ADMIN') or define('LEVEL_ADMIN', 10);

defined('GROUP_ADMIN') or define('GROUP_ADMIN', 'admin');
defined('GROUP_MEMBER') or define('GROUP_MEMBER', 'members');

defined('GROUP_ADMIN_ID') or define('GROUP_ADMIN_ID', '1');
defined('GROUP_MEMBER_ID') or define('GROUP_MEMBER_ID', '2');
