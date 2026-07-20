<?php

/**
 * update() and the fluent user()/users() accessor chains.
 */
class UpdateUserTest extends AuthTestCase
{
	private const IDENTITY = 'phpunit_update_user';

	private int $user_id;

	protected function setUp(): void
	{
		$this->user_id = self::create_active_user(self::IDENTITY);
	}

	protected function tearDown(): void
	{
		self::delete_user_if_exists(self::IDENTITY);
	}

	public function test_update_persists_profile_fields(): void
	{
		$updated = self::$auth->update($this->user_id, [
			'first_name' => 'Test',
			'last_name'  => 'User',
		]);
		$this->assertTrue($updated);

		$user = self::$auth->user($this->user_id)->row();
		$this->assertSame('Test', $user->first_name);
		$this->assertSame('User', $user->last_name);
	}

	public function test_user_by_id_returns_the_matching_object(): void
	{
		$user = self::$auth->user($this->user_id)->row();

		$this->assertIsObject($user);
		$this->assertSame($this->user_id, (int) $user->id);
	}

	public function test_where_users_chain_filters_to_one_row(): void
	{
		$result = self::$auth
			->where(self::$auth->identityColumn, self::IDENTITY)
			->users()
			->result();

		$this->assertCount(1, $result);
	}

	public function test_limit_users_chain_caps_the_rows(): void
	{
		$result = self::$auth->limit(1)->users()->result();

		$this->assertLessThanOrEqual(1, count($result));
	}
}
