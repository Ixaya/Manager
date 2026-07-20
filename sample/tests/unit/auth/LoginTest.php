<?php

/**
 * login() credential outcomes only — session/cookie behavior is covered by
 * the REST probes, not here.
 */
class LoginTest extends AuthTestCase
{
	private const IDENTITY = 'phpunit_login_user';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::create_active_user(self::IDENTITY);
	}

	public static function tearDownAfterClass(): void
	{
		self::delete_user_if_exists(self::IDENTITY);
	}

	protected function setUp(): void
	{
		// Failed logins accumulate attempts; a prior test must never push
		// this fixture into lockout.
		self::$auth->clear_login_attempts(self::IDENTITY);
		self::$auth->clear_errors();
	}

	public function test_login_succeeds_with_correct_credentials(): void
	{
		$result = self::$auth->login(self::IDENTITY, self::PASSWORD);

		$this->assertTrue($result === true || is_object($result), 'login() must return true or a user object');
	}

	public function test_login_fails_with_wrong_password(): void
	{
		$this->assertFalse(self::$auth->login(self::IDENTITY, 'WrongPassword!'));
	}

	public function test_login_fails_with_empty_credentials(): void
	{
		$this->assertFalse(self::$auth->login('', ''));
	}
}
