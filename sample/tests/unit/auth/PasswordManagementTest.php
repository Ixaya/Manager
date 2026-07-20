<?php

/**
 * change_password() / reset_password() — each test gets a fresh user with a
 * known password, so password state never leaks between tests.
 */
class PasswordManagementTest extends AuthTestCase
{
	private const IDENTITY     = 'phpunit_pass_user';
	private const NEW_PASSWORD = 'NewPass456!';

	protected function setUp(): void
	{
		self::create_active_user(self::IDENTITY);
	}

	protected function tearDown(): void
	{
		self::delete_user_if_exists(self::IDENTITY);
	}

	public function test_change_password_succeeds_with_correct_old_password(): void
	{
		$changed = self::$auth->change_password(self::IDENTITY, self::PASSWORD, self::NEW_PASSWORD);

		$this->assertTrue($changed);

		$login = self::$auth->login(self::IDENTITY, self::NEW_PASSWORD);
		$this->assertTrue($login === true || is_object($login), 'new password must log in');
	}

	public function test_change_password_fails_with_wrong_old_password(): void
	{
		$this->assertFalse(self::$auth->change_password(self::IDENTITY, 'WrongOldPass!', self::NEW_PASSWORD));
	}

	public function test_reset_password_sets_the_given_password(): void
	{
		$reset = self::$auth->reset_password(self::IDENTITY, self::NEW_PASSWORD);

		$this->assertTrue($reset);

		$login = self::$auth->login(self::IDENTITY, self::NEW_PASSWORD);
		$this->assertTrue($login === true || is_object($login), 'reset password must log in');
	}
}
