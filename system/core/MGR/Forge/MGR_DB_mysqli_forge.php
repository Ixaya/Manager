<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MGR_DB_mysql_forge_family.php';

class MGR_DB_mysqli_forge extends CI_DB_mysqli_forge
{
	use MGR_DB_mysql_forge_family;
}
