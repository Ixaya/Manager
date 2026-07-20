<?php

/**
 * delete_user() / delete_group() semantics (the probe's cleanup group,
 * promoted to first-class assertions).
 */
class DeletionTest extends AuthTestCase
{
	private const IDENTITY = 'phpunit_del_user';
	private const GROUP    = 'phpunit_del_group';

	protected function tearDown(): void
	{
		self::delete_user_if_exists(self::IDENTITY);
		self::delete_group_if_exists(self::GROUP);
	}

	public function test_delete_user_removes_the_user(): void
	{
		$id = self::create_active_user(self::IDENTITY);

		$this->assertTrue(self::$auth->delete_user($id));
		$this->assertFalse(self::$auth->identity_check(self::IDENTITY));
	}

	public function test_delete_group_removes_the_group(): void
	{
		$gid = self::$auth->create_group(self::GROUP, 'PHPUnit deletion fixture');
		$this->assertNotFalse($gid);

		$this->assertTrue(self::$auth->delete_group((int) $gid));

		$row = $this->db->where('name', self::GROUP)
			->get(self::$auth->tables['groups'])->row();
		$this->assertNull($row);
	}
}
