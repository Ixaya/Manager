<?php

defined('BASEPATH') or exit('No direct script access allowed');

require dirname(__FILE__) . "/../third_party/BE/Ion_auth_model.php";

class MGR_Ion_auth_model extends BE_Ion_auth_model
{
	/**
	 * Whether to check user privileges during login to apply different hashing costs
	 *
	 * @var bool
	 */
	protected $useRoleBasedHashing = false;

	/**
	 * When true, force the sessionless path even if a session library is loaded.
	 *
	 * @var bool
	 */
	protected bool $disable_session = false;

	/**
	 * Force (or restore) sessionless auth for this request.
	 *
	 * @param bool $disable Set false to re-enable session use.
	 *
	 * @return void
	 */
	public function disable_session(bool $disable = true): void
	{
		$this->disable_session = $disable;
	}

	/**
	 * Whether sessions are available, honoring an explicit disable_session().
	 *
	 * @return bool
	 */
	public function use_sessions(): bool
	{
		if ($this->disable_session) {
			return false;
		}
		return parent::use_sessions();
	}

	/**
	 * Set the session for a user; also stores extra session keys
	 * when the user row carries one (select it via identityExtraColumns).
	 *
	 * @param \stdClass $user User row from login.
	 *
	 * @return bool
	 */
	public function set_session(\stdClass $user): bool
	{
		// parent returns false in sessionless mode — no extra guard needed
		if (!parent::set_session($user)) {
			return false;
		}

		if (property_exists($user, 'client_id')) {
			if (empty($user->client_id)) {
				$this->session->unset_userdata('client_id');
			} else {
				$this->session->set_userdata('client_id', (int)$user->client_id);
			}
		}

		return true;
	}

	/**
	 * Session keys removed on the deactivation recheck, plus the client_id scope.
	 *
	 * @return array<int, string>
	 */
	protected function recheck_session_unset_keys(): array
	{
		return [...parent::recheck_session_unset_keys(), 'client_id'];
	}

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
			foreach ($this->configAuth->identityExtraColumns as $column) {
				$this->assert_plain_identifier($column);
			}
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
		$this->assert_plain_identifier($this->tables['groups']);
		return parent::users_groups_select_columns() . ', ' . $this->tables['groups'] . '.level';
	}

	/**
	 * Assert a config-sourced SQL identifier is a plain (optionally dotted) name.
	 *
	 * @param string $identifier Column or table name from config.
	 *
	 * @throws InvalidArgumentException When the value is not a plain identifier.
	 */
	private function assert_plain_identifier(string $identifier): void
	{
		if (!function_exists('mgr_is_sql_identifier')) {
			$this->load->helper('manager_db_function');
		}
		if (!mgr_is_sql_identifier($identifier)) {
			throw new InvalidArgumentException(
				"Ion_auth_model: config value '{$identifier}' is not a plain SQL identifier."
			);
		}
	}
}
