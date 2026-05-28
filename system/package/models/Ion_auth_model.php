<?php

require dirname(__FILE__) . "/../../third_party/BE/Ion_auth_model.php";

class Ion_auth_model extends BE_Ion_auth_model
{
	/**
	 * Whether to check user privileges during login to apply different hashing costs
	 *
	 * @var bool
	 */
	protected $useRoleBasedHashing = false;

	/**
	 *  Retrieve hash parameters - uniform cost for all users to prevent timing-based admin enumeration.
	 *
	 * @param string $identity Identity
	 *
	 * @return array|boolean
	 */
	protected function get_hash_parameters(string $identity = '')
	{
		$identity = $this->useRoleBasedHashing ? $identity : '';

		return parent::get_hash_parameters($identity);
	}
}
