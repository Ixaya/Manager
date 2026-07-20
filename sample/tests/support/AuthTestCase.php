<?php

/**
 * Base for Ion Auth integration tests: loads the model once per class and
 * provides namespaced fixture helpers. Subclasses overriding
 * setUpBeforeClass() must call parent::setUpBeforeClass() first.
 */
abstract class AuthTestCase extends CITestCase
{
	protected const PASSWORD = 'TestPass123!';

	protected static Ion_auth_model $auth;

	public static function setUpBeforeClass(): void
	{
		$ci = get_instance();
		$ci->load->database();
		$ci->load->model('ion_auth_model');
		self::$auth = $ci->ion_auth_model;
	}

	/**
	 * Register + activate a fixture user (removing any leftover with the same
	 * identity first, so a crashed prior run never collides).
	 */
	protected static function create_active_user(string $identity, ?string $password = null): int
	{
		self::delete_user_if_exists($identity);

		$id = self::$auth->register($identity, $password ?? self::PASSWORD, $identity . '@example.com');
		self::assertNotFalse($id, "fixture register('{$identity}') failed");

		self::$auth->activate((int) $id);

		return (int) $id;
	}

	protected static function delete_user_if_exists(string $identity): void
	{
		$uid = self::$auth->get_user_id_from_identity($identity);
		if ($uid) {
			self::$auth->delete_user((int) $uid);
		}
	}

	protected static function delete_group_if_exists(string $name): void
	{
		$row = get_instance()->db->where('name', $name)
			->get(self::$auth->tables['groups'])->row();
		if ($row) {
			self::$auth->delete_group((int) $row->id);
		}
	}
}
