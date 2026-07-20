<?php

/**
 * deactivate() / activate() — with an activation code (user flow) and
 * without one (admin flow).
 */
class ActivationTest extends AuthTestCase
{
	private const IDENTITY = 'phpunit_act_user';

	private int $user_id;

	protected function setUp(): void
	{
		$this->user_id = self::create_active_user(self::IDENTITY);
	}

	protected function tearDown(): void
	{
		self::delete_user_if_exists(self::IDENTITY);
	}

	public function test_deactivate_sets_an_activation_code(): void
	{
		$this->assertTrue(self::$auth->deactivate($this->user_id));
		$this->assertNotEmpty(self::$auth->activationCode);
	}

	public function test_activation_code_resolves_and_activates(): void
	{
		self::$auth->deactivate($this->user_id);
		$code = self::$auth->activationCode;
		$this->assertNotEmpty($code);

		$user = self::$auth->get_user_by_activation_code($code);
		$this->assertIsObject($user);
		$this->assertSame($this->user_id, (int) $user->id);

		$this->assertTrue(self::$auth->activate($this->user_id, $code));
	}

	public function test_activate_without_code_works_as_admin(): void
	{
		self::$auth->deactivate($this->user_id);

		$this->assertTrue(self::$auth->activate($this->user_id));
	}

	public function test_deactivate_zero_returns_false(): void
	{
		$this->assertFalse(self::$auth->deactivate(0));
	}
}
