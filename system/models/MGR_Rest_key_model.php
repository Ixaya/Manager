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

	/**
	 * Suspends a key (level 0, activated 0); returns false if it doesn't exist.
	 */
	public function suspend_key(string $key): bool
	{
		if (!$this->_key_exists($key)) {
			return false;
		}

		return $this->_update_key($key, ['level' => 0, 'activated' => 0]);
	}

	/**
	 * Suspends the old key (level 0, activated 0) and issues a new one for the same user and
	 * device, carrying its level and ignore_limits flag.
	 */
	public function regenerate_key(string $old_key): bool
	{
		$key_details = $this->_get_key($old_key);

		if (!$key_details) {
			return false;
		}

		$new_key = $this->_generate_key();

		$carried = [
			'user_id' => $key_details['user_id'],
			'device_uuid' => $key_details['device_uuid'],
			'level' => $key_details['level'],
			'ignore_limits' => $key_details['ignore_limits'],
		];
		if (!$this->_insert_key($new_key, $carried)) {
			return false;
		}

		// The new key exists either way, so a failed suspend is logged rather than returned.
		if (!$this->_update_key($old_key, ['level' => 0, 'activated' => 0])) {
			log_message('error', 'MGR_Rest_key_model: regenerate_key() issued a new key but could not suspend the old one (user_id ' . $key_details['user_id'] . ').');
		}

		return true;
	}

	/**
	 * Returns the user's newest usable key (level above 0, not deactivated), or issues one if
	 * none is left. The device UUID is stored on a new key but not matched.
	 *
	 * @return string|bool the key, or the result of add_key() when a new one had to be created
	 */
	public function get_user_key(int|string $user_id, ?string $device_uuid = null): string|bool
	{
		$this->check_connect();
		// Filter on the builder only: the insert data below must stay just user_id.
		$key_row = $this->my_db
			->where('user_id', $user_id)
			->where('level >', 0)
			->group_start()
				->where('activated IS NULL', null, false)
				->or_where('activated !=', 0)
			->group_end()
			->order_by('id', 'DESC')
			->limit(1)
			->get(config_item('rest_keys_table'))
			->row_array();
		if ($key_row) {
			return $key_row['key'];
		}

		return $this->add_key(data: ['user_id' => $user_id], level: 1, returnKey: true, device_uuid: $device_uuid);
	}

	/**
	 * Returns an unused key of `rest_key_length` lowercase hex characters from the system CSPRNG.
	 *
	 * @throws \Random\RandomException when no secure random source is available
	 */
	protected function _generate_key(): string
	{
		$length = (int) config_item('rest_key_length');

		do {
			$new_key = substr(bin2hex(random_bytes(intdiv($length + 1, 2))), 0, $length);
		} while ($this->_key_exists($new_key));

		return $new_key;
	}

	/**
	 * Every key is inserted active (activated 1) unless the caller sets activated itself.
	 *
	 * @param array<string, mixed> $data
	 */
	protected function _insert_key(string $key, array $data): bool
	{
		return parent::_insert_key($key, $data + ['activated' => 1]);
	}
}
