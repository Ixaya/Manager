<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Keys Model
 * This is a basic Key Management REST controller to make and delete keys
 *
 * @package		 CodeIgniter
 * @subpackage	  Rest Server
 * @category		Controller
 * @author		  Phil Sturgeon, Chris Kacerguis
 * @author		  ho <ixaya.com> -> converted controller into model.
 * @license		 MIT
 * @link			https://github.com/chriskacerguis/codeigniter-restserver

 */
class Rest_key_model extends MY_Model
{
	protected bool $lazy_connect = true;

	public function __construct()
	{
		// Construct the parent class
		parent::__construct();

		$this->load->config('rest');
		$this->connection_name = config_item('rest_database_group') ?? 'default';
	}

	/**
	 * Inserts a new key, stamping the caller's IP/user-agent and an optional device UUID.
	 *
	 * @param array<string, mixed> $data extra columns to store alongside the generated key (e.g. user_id)
	 * @return string|bool the generated key when $returnKey is true, otherwise the insert result
	 */
	public function add_key(array $data = [], int|false $level = false, bool $returnKey = false, ?string $device_uuid = null): string|bool
	{
		if (!empty($device_uuid)) {
			$data['device_uuid'] = $device_uuid;
		}

		$data['ip_addresses'] = $_SERVER['REMOTE_ADDR'];
		$data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

		$key = $this->_generate_key();
		if ($level) {
			$data['level'] = $level;
		}

		$result = $this->_insert_key($key, $data);
		if ($returnKey) {
			return $key;
		}

		return $result;
	}

	/**
	 * Deletes a key; returns false if it doesn't exist.
	 */
	public function delete_key(string $key): bool
	{
		if (!$this->_key_exists($key)) {
			return false;
		}

		$this->_delete_key($key);

		return true;
	}

	/**
	 * Changes a key's level; returns false if the key doesn't exist.
	 */
	public function set_key_level(string $key, int $new_level): bool
	{
		if (!$this->_key_exists($key)) {
			return false;
		}

		return $this->_update_key($key, ['level' => $new_level]);
	}

	/**
	 * Suspends a key by setting its level to 0; returns false if it doesn't exist.
	 */
	public function suspend_key(string $key): bool
	{
		if (!$this->_key_exists($key)) {
			return false;
		}

		return $this->_update_key($key, ['level' => 0]);
	}

	/**
	 * Suspends the old key and issues a new one carrying its level and ignore_limits flag.
	 */
	public function regenerate_key(string $old_key): bool
	{
		$key_details = $this->_get_key($old_key);

		if (!$key_details) {
			return false;
		}

		$new_key = $this->_generate_key();

		if (!$this->_insert_key($new_key, ['level' => $key_details['level'], 'ignore_limits' => $key_details['ignore_limits']])) {
			return false;
		}

		$this->_update_key($old_key, ['level' => 0]);

		return true;
	}

	/**
	 * Returns the existing API key for a user's device, or creates one if none exists.
	 *
	 * @return string|bool the key, or the result of add_key() when a new one had to be created
	 */
	public function get_user_key(int|string $user_id, ?string $device_uuid = null): string|bool
	{
		$where = ['user_id' => $user_id];

		$this->check_connect();
		$keyRow = $this->my_db
			->where($where)
			->get(config_item('rest_keys_table'))
			->row_array();
		if ($keyRow) {
			return $keyRow['key'];
		}

		return $this->add_key($where, 1, true, $device_uuid);
	}

	/**
	 * Deletes every key belonging to a user.
	 */
	public function delete_user_key(int|string $user_id): bool
	{
		$this->check_connect();

		return $this->my_db
			->where('user_id', $user_id)
			->delete(config_item('rest_keys_table'));
	}

	/* Helper Methods */

	/**
	 * Generates a random key, falling back to a time-seeded hash if secure bytes are unavailable.
	 */
	protected function _generate_key(): string
	{
		do {
			$salt = base_convert(bin2hex($this->security->get_random_bytes(64)), 16, 36);

			if (empty($salt)) {
				$salt = hash('sha256', time() . mt_rand());
			}

			$new_key = substr($salt, 0, config_item('rest_key_length'));
		} while ($this->_key_exists($new_key));

		return $new_key;
	}

	/* Private Data Methods */

	/**
	 * @return array<string, mixed> empty when the key doesn't exist
	 */
	protected function _get_key(string $key): array
	{
		$this->check_connect();

		return $this->my_db
			->where(config_item('rest_key_column'), $key)
			->get(config_item('rest_keys_table'))
			->row_array();
	}

	protected function _key_exists(string $key): bool
	{
		$this->check_connect();

		return $this->my_db
			->where(config_item('rest_key_column'), $key)
			->count_all_results(config_item('rest_keys_table')) > 0;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	protected function _insert_key(string $key, array $data): bool
	{
		$this->check_connect();

		$data[config_item('rest_key_column')] = $key;
		$data['date_created'] = function_exists('now') ? now() : time();

		return $this->my_db
			->set($data)
			->insert(config_item('rest_keys_table'));
	}

	/**
	 * @param array<string, mixed> $data
	 */
	protected function _update_key(string $key, array $data): bool
	{
		$this->check_connect();

		return $this->my_db
			->where(config_item('rest_key_column'), $key)
			->update(config_item('rest_keys_table'), $data);
	}

	protected function _delete_key(string $key): bool
	{
		$this->check_connect();

		return $this->my_db
			->where(config_item('rest_key_column'), $key)
			->delete(config_item('rest_keys_table'));
	}
}
