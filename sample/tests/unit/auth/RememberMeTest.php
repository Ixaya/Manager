<?php

/**
 * remember_user() / clear_remember_code() — DB side only; cookie/session
 * behavior is covered by the REST probes, not here.
 */
class RememberMeTest extends AuthTestCase
{
	private const IDENTITY = 'phpunit_rem_user';

	private int $user_id;

	protected function setUp(): void
	{
		$this->user_id = self::create_active_user(self::IDENTITY);
	}

	protected function tearDown(): void
	{
		self::delete_user_if_exists(self::IDENTITY);
	}

	private function remember_columns(): ?object
	{
		return $this->db->select('remember_selector, remember_code')
			->where('id', $this->user_id)
			->get(self::$auth->tables['users'])->row();
	}

	public function test_remember_user_populates_selector_and_code(): void
	{
		$this->assertTrue(self::$auth->remember_user(self::IDENTITY));

		$row = $this->remember_columns();
		$this->assertNotNull($row);
		$this->assertNotEmpty($row->remember_selector);
		$this->assertNotEmpty($row->remember_code);
	}

	public function test_remember_user_false_for_nonexistent_identity(): void
	{
		$this->assertFalse(self::$auth->remember_user('ghost_user_xyz'));
	}

	public function test_clear_remember_code_nulls_both_columns(): void
	{
		self::$auth->remember_user(self::IDENTITY);

		$this->assertTrue(self::$auth->clear_remember_code(self::IDENTITY));

		$row = $this->remember_columns();
		$this->assertNotNull($row);
		$this->assertEmpty($row->remember_selector);
		$this->assertEmpty($row->remember_code);
	}
}
