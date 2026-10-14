<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MGR_DB_pgsql_forge_unique.php';

class MGR_DB_postgre_forge extends CI_DB_postgre_forge
{
	use MGR_DB_pgsql_forge_unique;
}
