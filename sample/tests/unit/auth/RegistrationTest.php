<?php

/**
 * Ion_auth_model::register() happy path and rejection paths.
 *
 * Every test is order-independent: fixtures are created inside the test that
 * needs them and removed in tearDown() after each test.
 */
class RegistrationTest extends AuthTestCase
{
	private const IDENTITY = 'phpunit_reg_user';
	private const EMAIL    = 'phpunit_reg_user@example.com';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::delete_test_users();
	}

	protected function tearDown(): void
	{
		self::delete_test_users();
	}

	private static function delete_test_users(): void
	{
		self::delete_user_if_exists(self::IDENTITY);
		self::delete_user_if_exists('phpunit_reg_nopass');
	}

	public function test_register_returns_the_new_user_id(): void
	{
		$id = self::$auth->register(self::IDENTITY, self::PASSWORD, self::EMAIL);

		$this->assertIsNumeric($id);
		$this->assertGreaterThan(0, (int) $id);
		$this->assertTrue(self::$auth->identity_check(self::IDENTITY));
	}

	public function test_register_rejects_a_duplicate_identity(): void
	{
		$first = self::$auth->register(self::IDENTITY, self::PASSWORD, self::EMAIL);
		$this->assertNotFalse($first);

		$dup = self::$auth->register(self::IDENTITY, self::PASSWORD, 'other@example.com');
		$this->assertFalse($dup);
	}

	public function test_register_rejects_an_empty_password(): void
	{
		$empty = self::$auth->register('phpunit_reg_nopass', '', 'nopass@example.com');

		$this->assertFalse($empty);
		$this->assertFalse(self::$auth->identity_check('phpunit_reg_nopass'));
	}
}
