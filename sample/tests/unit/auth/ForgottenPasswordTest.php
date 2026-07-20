<?php

/**
 * forgotten_password() code generation, code-to-user resolution, and clearing.
 */
class ForgottenPasswordTest extends AuthTestCase
{
	private const IDENTITY = 'phpunit_forgot_user';

	protected function setUp(): void
	{
		self::create_active_user(self::IDENTITY);
	}

	protected function tearDown(): void
	{
		self::delete_user_if_exists(self::IDENTITY);
	}

	public function test_forgotten_password_returns_a_code_string(): void
	{
		$code = self::$auth->forgotten_password(self::IDENTITY);

		$this->assertIsString($code);
		$this->assertGreaterThan(10, strlen($code));
	}

	public function test_code_resolves_back_to_the_user(): void
	{
		$code = self::$auth->forgotten_password(self::IDENTITY);
		$this->assertIsString($code);

		$user = self::$auth->get_user_by_forgotten_password_code($code);

		$this->assertIsObject($user);
		$this->assertSame((int) self::$auth->get_user_id_from_identity(self::IDENTITY), (int) $user->id);
	}

	public function test_clear_forgotten_password_code_returns_true(): void
	{
		$code = self::$auth->forgotten_password(self::IDENTITY);
		$this->assertIsString($code);

		$this->assertTrue(self::$auth->clear_forgotten_password_code(self::IDENTITY));
	}

	public function test_forgotten_password_fails_with_empty_identity(): void
	{
		$this->assertFalse(self::$auth->forgotten_password(''));
	}
}
