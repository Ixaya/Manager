<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MGR_DB_pgsql_forge_family.php';

class MGR_DB_pdo_pgsql_forge extends CI_DB_pdo_pgsql_forge
{
	use MGR_DB_pgsql_forge_family;
}
