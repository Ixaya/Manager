<?php

defined('BASEPATH') or exit('No direct script access allowed');

require dirname(__FILE__) . "/../third_party/RestServer/Rest_key_model.php";

class MGR_Rest_key_model extends RS_Rest_key_model
{
	public function __construct()
	{
		parent::__construct();

		// A missing rest_database_group means the main handle, matching the package default (RS_ falls back to 'default').
		$this->connection_name = config_item('rest_database_group') ?? '';
	}
}
