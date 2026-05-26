<?php

(defined('BASEPATH')) or exit('No direct script access allowed');

class Domain extends MY_Model
{
	public function __construct()
	{
		$this->table_name = 'domain';

		//initialize after overriding
		parent::__construct();
	}
}
