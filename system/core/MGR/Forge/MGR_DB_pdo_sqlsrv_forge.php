<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MGR_DB_sqlsrv_forge_family.php';

class MGR_DB_pdo_sqlsrv_forge extends CI_DB_pdo_sqlsrv_forge
{
	use MGR_DB_sqlsrv_forge_family;
}
