<?php

/**
 * identity_check() / email_check() / username_check() /
 * get_user_id_from_identity() — read-only lookups against one class fixture.
 */
class IdentityChecksTest extends AuthTestCase
{
	private const IDENTITY = 'phpunit_idch_user';

	private static int $user_id;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::$user_id = self::create_active_user(self::IDENTITY);
	}

	public static function tearDownAfterClass(): void
	{
		self::delete_user_if_exists(self::IDENTITY);
	}

	public function test_identity_check_true_for_existing_user(): void
	{
		$this->assertTrue(self::$auth->identity_check(self::IDENTITY));
	}

	public function test_identity_check_false_for_unknown_user(): void
	{
		$this->assertFalse(self::$auth->identity_check('ghost_user_xyz'));
	}

	public function test_email_check_true_for_existing_email(): void
	{
		$this->assertTrue(self::$auth->email_check(self::IDENTITY . '@example.com'));
	}

	public function test_email_check_false_for_unknown_email(): void
	{
		$this->assertFalse(self::$auth->email_check('nobody@nowhere.com'));
	}

	public function test_username_check_true_for_existing_username(): void
	{
		$this->assertTrue(self::$auth->username_check(self::IDENTITY));
	}

	public function test_get_user_id_from_identity_returns_correct_id(): void
	{
		$this->assertSame(self::$user_id, (int) self::$auth->get_user_id_from_identity(self::IDENTITY));
	}
}
