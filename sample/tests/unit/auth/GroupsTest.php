<?php

/**
 * Group management: create/update/delete, membership, and the fluent
 * groups()/group() accessors. Fresh user + group per test.
 */
class GroupsTest extends AuthTestCase
{
	private const IDENTITY = 'phpunit_groups_user';
	private const GROUP    = 'phpunit_groups_group';

	private int $user_id;
	private int $group_id;

	protected function setUp(): void
	{
		$this->user_id = self::create_active_user(self::IDENTITY);

		self::delete_group_if_exists(self::GROUP);
		self::delete_group_if_exists(self::GROUP . '_renamed');

		$gid = self::$auth->create_group(self::GROUP, 'PHPUnit fixture group');
		self::assertNotFalse($gid, 'fixture create_group() failed');
		$this->group_id = (int) $gid;
	}

	protected function tearDown(): void
	{
		self::delete_user_if_exists(self::IDENTITY);
		self::delete_group_if_exists(self::GROUP);
		self::delete_group_if_exists(self::GROUP . '_renamed');
	}

	public function test_create_group_rejects_a_duplicate_name(): void
	{
		$this->assertFalse(self::$auth->create_group(self::GROUP, 'Duplicate'));
	}

	public function test_add_to_group_and_membership_checks(): void
	{
		$added = self::$auth->add_to_group($this->group_id, $this->user_id);
		$this->assertSame(1, $added, 'add_to_group() must report 1 group added');

		$this->assertTrue(self::$auth->in_group($this->group_id, $this->user_id));

		$groups = self::$auth->get_users_groups($this->user_id)->result();
		$this->assertIsArray($groups);
		$ids = array_map(static fn ($g) => (int) $g->id, $groups);
		$this->assertContains($this->group_id, $ids);
	}

	public function test_remove_from_group_revokes_membership(): void
	{
		self::$auth->add_to_group($this->group_id, $this->user_id);

		$this->assertTrue(self::$auth->remove_from_group($this->group_id, $this->user_id));
		$this->assertFalse(self::$auth->in_group($this->group_id, $this->user_id));
	}

	public function test_update_group_renames(): void
	{
		$this->assertTrue(self::$auth->update_group($this->group_id, self::GROUP . '_renamed'));
	}

	public function test_update_group_data_only_works_on_the_admin_group(): void
	{
		// Only RENAMING the admin group is restricted; a data-only update must
		// pass. Description is restored before asserting so a failure never
		// leaves residue.
		$config   = (object) $this->load->config_read('ion_auth');
		$adminRow = $this->db->where('name', $config->adminGroup)
			->get(self::$auth->tables['groups'])->row();
		$this->assertNotNull($adminRow, 'admin group not found');

		$dataOnly = self::$auth->update_group((int) $adminRow->id, '', ['description' => 'phpunit_tmp_desc']);
		self::$auth->update_group((int) $adminRow->id, '', ['description' => $adminRow->description]);

		$this->assertTrue($dataOnly);
	}

	public function test_update_group_false_for_nonexistent_id(): void
	{
		$this->assertFalse(self::$auth->update_group(99999999));
	}

	public function test_groups_fluent_chain_returns_an_array(): void
	{
		$this->assertIsArray(self::$auth->groups()->result());
	}

	public function test_group_by_id_returns_an_object(): void
	{
		$this->assertIsObject(self::$auth->group($this->group_id)->row());
	}
}
