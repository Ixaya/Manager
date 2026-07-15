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

	/**
	 * Select columns for login query to include the extra columns from config
	 *
	 * @return string
	 */
	protected function login_select_columns(): string
	{
		$extraColumns = '';
		if (!empty($this->configAuth->identityExtraColumns)) {
			$extraColumns = ', ';
			$extraColumns .= implode(', ', $this->configAuth->identityExtraColumns);
		}

		return parent::login_select_columns() . $extraColumns;
	}

	/**
	 *  Select columns for user group query to include level column for REST controller
	 *
	 * @return string
	 */
	protected function users_groups_select_columns(): string
	{
		return parent::users_groups_select_columns() . ', ' . $this->tables['groups'] . '.level';
	}
}
