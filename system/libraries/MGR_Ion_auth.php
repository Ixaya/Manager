<?php

defined('BASEPATH') or exit('No direct script access allowed');

require dirname(__FILE__) . "/../third_party/BE/Ion_auth.php";

class MGR_Ion_auth extends BE_Ion_auth
{
	/**
	 * Groups for a user, falling back to the session user when no id is given.
	 *
	 * @param int|null $id User id; omitted → current session user.
	 *
	 * @return object DB result (empty, still chainable, when there is no user)
	 */
	public function get_users_groups(?int $id = null): object
	{
		$id = (int)($id ?? $this->get_user_id());
		return $this->ion_auth_model->get_users_groups($id);
	}

	/**
	 * Add a user to group(s), falling back to the session user when no id is given.
	 *
	 * @param int|array $groupIds Group id(s) to add.
	 * @param int|null  $userId   User id; omitted → current session user.
	 *
	 * @return int The number of groups added (0 when there is no user)
	 */
	public function add_to_group(array|int $groupIds, ?int $userId = null): int
	{
		$userId = $userId ?? $this->get_user_id();
		if (empty($userId)) {
			return 0;
		}
		return $this->ion_auth_model->add_to_group($groupIds, $userId);
	}

	/**
	 * Client id from the session, or null in sessionless mode.
	 *
	 * Stored by the model's set_session() when the user row carries a
	 * client_id, cleared by logout and the deactivation recheck.
	 *
	 * @return integer|null
	 * @author gumoz
	 **/
	public function get_client_id(): ?int
	{
		if (!$this->use_sessions()) {
			return null;
		}

		$client_id = $this->session->userdata('client_id');
		return empty($client_id) ? null : (int)$client_id;
	}

	/**
	 * Atomically reset a password from a forgotten-password code.
	 *
	 * Validates code + expiration, then resets — the identity comes from the
	 * code's user row, never from the caller.
	 *
	 * @param string $code         Forgotten-password code from the reset link.
	 * @param string $new_password New password to set.
	 *
	 * @return bool True on reset; false on invalid/expired code or DB failure.
	 */
	public function reset_password_with_code(string $code, string $new_password): bool
	{
		$user = $this->forgotten_password_check($code);
		if (!is_object($user)) {
			return false;
		}

		$identity = $user->{$this->configAuth->identity};

		// the code pair is cleared by set_password_db() when the password is set
		return $this->ion_auth_model->reset_password($identity, $new_password);
	}
}
