<?php

/**
 * Login-attempt tracking and the lockout path: at maximumLoginAttempts even
 * the correct password must be rejected with login_timeout until cleared.
 */
class LoginAttemptsTest extends AuthTestCase
{
	private const IDENTITY = 'phpunit_attempts_user';

	protected function setUp(): void
	{
		self::create_active_user(self::IDENTITY);
		self::$auth->clear_login_attempts(self::IDENTITY);
		self::$auth->clear_errors();
	}

	protected function tearDown(): void
	{
		self::$auth->clear_login_attempts(self::IDENTITY);
		self::delete_user_if_exists(self::IDENTITY);
	}

	public function test_attempts_accumulate_and_clear(): void
	{
		self::$auth->increase_login_attempts(self::IDENTITY);
		self::$auth->increase_login_attempts(self::IDENTITY);

		$this->assertGreaterThan(0, self::$auth->get_attempts_num(self::IDENTITY));

		$last = self::$auth->get_last_attempt_time(self::IDENTITY);
		$this->assertGreaterThan(0, $last);
		$this->assertLessThanOrEqual(time(), $last);

		$this->assertTrue(self::$auth->clear_login_attempts(self::IDENTITY));
		$this->assertIsBool(self::$auth->is_max_login_attempts_exceeded(self::IDENTITY));
	}

	public function test_lockout_blocks_even_the_correct_password_until_cleared(): void
	{
		$config = (object) $this->load->config_read('ion_auth');
		for ($i = 0; $i < (int) $config->maximumLoginAttempts; $i++) {
			self::$auth->increase_login_attempts(self::IDENTITY);
		}
		$this->assertTrue(self::$auth->is_max_login_attempts_exceeded(self::IDENTITY));

		self::$auth->clear_errors();
		$this->assertFalse(self::$auth->login(self::IDENTITY, self::PASSWORD), 'locked-out login must fail');
		$this->assertContains('login_timeout', self::$auth->errors_array(false));

		self::$auth->clear_login_attempts(self::IDENTITY);
		self::$auth->clear_errors();
		$unlocked = self::$auth->login(self::IDENTITY, self::PASSWORD);
		$this->assertTrue($unlocked === true || is_object($unlocked), 'login must succeed again after clearing');
	}
}
