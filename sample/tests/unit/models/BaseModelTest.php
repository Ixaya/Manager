<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/../../support/DynProbeFixture.php';

/**
 * Covers the MY_Model surface DynModelTest doesn't: plain query variants
 * (get_where, get_min_max, count_all, get_all_join/like/or_like/in/updated),
 * bulk/upsert writes, and override_column/replace/sync_* — several of these
 * are regressions for bugs found live via the probes/ sweep, not just
 * capability checks.
 */
class BaseModelTest extends CITestCase
{
	private static Dyn_probe_model $model;

	public static function setUpBeforeClass(): void
	{
		DynProbeFixture::create();
		self::$model = new Dyn_probe_model();
	}

	public static function tearDownAfterClass(): void
	{
		DynProbeFixture::drop();
	}

	protected function tearDown(): void
	{
		get_instance()->db->empty_table('dyn_probe_test');
		get_instance()->db->empty_table('dyn_probe_category_test');
		self::$model->del_override();
	}

	/** create_date is never stamped by the model itself. */
	private function insert_probe(array $data): int|string|bool
	{
		return self::$model->insert([...$data, 'create_date' => date('Y-m-d H:i:s')]);
	}

	public function test_get_where_and_by_hash(): void
	{
		$this->insert_probe(['name' => 'Findable', 'status' => 7]);
		$this->insert_probe(['name' => 'Other', 'status' => 1]);

		$via_get_where = self::$model->get_where(['status' => 7]);
		// by_hash($value, $field) is a generic get_where([$field => $value]) —
		// it doesn't require an actual "hash" column.
		$via_by_hash = self::$model->by_hash('Findable', 'name');

		$this->assertNotNull($via_get_where);
		$this->assertSame($via_get_where['id'], $via_by_hash['id']);
	}

	public function test_get_min_max_and_count_all(): void
	{
		$this->insert_probe(['name' => 'A', 'status' => 3]);
		$this->insert_probe(['name' => 'B', 'status' => 9]);
		$this->insert_probe(['name' => 'C', 'status' => 1]);

		$min_max = self::$model->get_min_max('status');

		$this->assertSame('1', (string) $min_max['min_status']);
		$this->assertSame('9', (string) $min_max['max_status']);
		$this->assertSame(3, self::$model->count_all());
		$this->assertSame(2, self::$model->count_all(['status >' => 2]));
	}

	public function test_get_all_join_legacy(): void
	{
		$category_id = get_instance()->db->insert('dyn_probe_category_test', ['name' => 'Legacy Cat'])
			? get_instance()->db->insert_id()
			: null;
		$this->insert_probe(['name' => 'Legacy linked', 'category_id' => $category_id]);
		$this->insert_probe(['name' => 'Legacy unlinked']);

		$rows = self::$model->get_all_join(
			fields: 'dyn_probe_test.name, dyn_probe_category_test.name as category_name',
			join_table: 'dyn_probe_category_test',
			join_where: 'dyn_probe_category_test.id = dyn_probe_test.category_id',
			join_method: 'inner',
		);

		$this->assertCount(1, $rows);
		$this->assertSame('Legacy linked', $rows[0]['name']);
		$this->assertSame('Legacy Cat', $rows[0]['category_name']);
	}

	public function test_get_all_like_or_like_in_and_updated(): void
	{
		$this->insert_probe(['name' => 'Widget Alpha', 'status' => 1]);
		$this->insert_probe(['name' => 'Widget Beta', 'status' => 2]);
		$this->insert_probe(['name' => 'Gadget Gamma', 'status' => 9]);
		$marker = date('Y-m-d H:i:s', time() - 5);

		$this->assertSame(
			['Widget Alpha', 'Widget Beta'],
			array_column(self::$model->get_all_like(where: ['name' => 'Widget']), 'name'),
		);
		$this->assertSame(
			['Widget Alpha', 'Widget Beta'],
			array_column(self::$model->get_all_or_like(where: ['name' => 'Widget']), 'name'),
		);
		$this->assertSame(
			['Widget Alpha', 'Gadget Gamma'],
			array_column(self::$model->get_all_in('status', [1, 9]), 'name'),
		);
		$this->assertCount(3, self::$model->get_all_updated($marker));
	}

	/**
	 * Regression: or_like()'s OR must not glue onto the implicit
	 * soft-delete filter and resurrect a deleted row.
	 */
	public function test_get_all_or_like_does_not_defeat_soft_delete(): void
	{
		$live_id = $this->insert_probe(['name' => 'Widget Alpha']);
		$deleted_id = $this->insert_probe(['name' => 'Widget Beta']);
		self::$model->delete($deleted_id);

		$rows = self::$model->get_all_or_like(where: ['name' => 'Widget']);
		$ids = array_column($rows, 'id');

		$this->assertContains((string) $live_id, $ids);
		$this->assertNotContains((string) $deleted_id, $ids);
	}

	public function test_insert_bulk_and_update_where(): void
	{
		$now = date('Y-m-d H:i:s');
		$affected = self::$model->insert_bulk([
			['name' => 'Bulk A', 'status' => 5, 'create_date' => $now],
			['name' => 'Bulk B', 'status' => 5, 'create_date' => $now],
			['name' => 'Bulk C', 'status' => 6, 'create_date' => $now],
		]);
		$this->assertSame(3, $affected);

		$this->assertTrue(self::$model->update_where(['status' => 50], ['status' => 5]));

		$rows = self::$model->get_all(order_by: 'name');
		$statuses = array_combine(array_column($rows, 'name'), array_map('intval', array_column($rows, 'status')));
		$this->assertSame(['Bulk A' => 50, 'Bulk B' => 50, 'Bulk C' => 6], $statuses);
	}

	public function test_upsert_and_upsert_where(): void
	{
		$inserted_id = self::$model->upsert(['name' => 'Upserted', 'status' => 1, 'create_date' => date('Y-m-d H:i:s')]);
		$this->assertNotFalse($inserted_id);

		$updated_id = self::$model->upsert(['name' => 'Upserted (renamed)'], (int) $inserted_id);
		$this->assertEquals($inserted_id, $updated_id);
		$this->assertSame('Upserted (renamed)', self::$model->get((int) $inserted_id)['name']);

		$via_insert = self::$model->upsert_where(
			data: ['status' => 2],
			where: ['name' => 'Upsert-where new'],
			insert_data: ['create_date' => date('Y-m-d H:i:s')],
		);
		$via_update = self::$model->upsert_where(data: ['status' => 3], where: ['name' => 'Upsert-where new']);

		$this->assertEquals($via_insert, $via_update);
		$this->assertSame(3, (int) self::$model->get_where(['name' => 'Upsert-where new'])['status']);
	}

	/** Regression: an explicit set_override($id) must always take effect, even after a previous one already resolved. */
	public function test_override_column_set_override_switches_explicitly(): void
	{
		self::$model->set_override_column('tenant_id');

		self::$model->set_override(1);
		$this->insert_probe(['name' => 'Tenant 1 row']);

		self::$model->set_override(2);
		$this->insert_probe(['name' => 'Tenant 2 row']);

		$row = get_instance()->db->where('name', 'Tenant 2 row')->get('dyn_probe_test')->row_array();
		$this->assertSame(2, (int) $row['tenant_id']);
	}

	/** Regression: del_override(false) resets the id but keeps override_column configured. */
	public function test_del_override_can_reset_id_while_keeping_column(): void
	{
		self::$model->set_override_column('tenant_id');
		self::$model->set_override(1);

		self::$model->del_override(false);
		self::$model->set_override(3);

		$this->insert_probe(['name' => 'Tenant 3 row']);
		$row = get_instance()->db->where('name', 'Tenant 3 row')->get('dyn_probe_test')->row_array();
		$this->assertSame(3, (int) $row['tenant_id']);
	}

	/** Regression: replace() is a full row replacement — omitted columns revert to their default, not the old value. */
	public function test_replace_is_a_full_row_replacement_not_a_partial_update(): void
	{
		$category_id = get_instance()->db->insert('dyn_probe_category_test', ['name' => 'Cat'])
			? get_instance()->db->insert_id()
			: null;
		$id = $this->insert_probe(['name' => 'Original', 'status' => 5, 'category_id' => $category_id]);

		self::$model->replace(['id' => $id, 'name' => 'Replaced', 'create_date' => date('Y-m-d H:i:s')]);

		$row = self::$model->get($id);
		$this->assertSame('Replaced', $row['name']);
		$this->assertSame(0, (int) $row['status']); // reverted to default, not the old 5
		$this->assertNull($row['category_id']);     // reverted to default, not the old category
	}

	public function test_sync_update_insert_and_sync_update_diffing(): void
	{
		$now = date('Y-m-d H:i:s');
		// No add_sync here — this isolates the diff-detection itself.
		// add_sync always writes sync_enabled regardless of a data diff
		// (that's how a later sync_commit_enabled() pass knows a row was
		// seen this run), which is a different behavior than tested here.
		$modified = false;
		$first_id = self::$model->sync_update_insert(
			data: ['name' => 'Synced item', 'status' => 1, 'create_date' => $now],
			where: ['name' => 'Synced item'],
			modified: $modified,
		);
		$this->assertTrue($modified);

		$modified = false;
		$second_id = self::$model->sync_update_insert(
			data: ['name' => 'Synced item', 'status' => 1, 'create_date' => $now], // identical data — no-op
			where: ['name' => 'Synced item'],
			modified: $modified,
		);
		$this->assertEquals($first_id, $second_id);
		$this->assertFalse($modified);

		$row = self::$model->get((int) $first_id);
		$this->assertTrue(self::$model->sync_update((int) $first_id, ['status' => 2], row: $row));
		$this->assertSame(2, (int) self::$model->get((int) $first_id)['status']);
	}

	/** Regression: sync_enabled can hold values above 1 (multi-stage sync); sync_commit_enabled must still treat any nonzero as "enabled." */
	public function test_sync_commit_enabled_binarizes_multistage_values(): void
	{
		// Both rows insert with enabled=1 (the table default); sync_enabled
		// differs from that default on both, so sync_commit_enabled() must
		// reconcile each — synced_id survives (sync_enabled != 0), stale_id
		// gets soft-deleted (sync_enabled == 0).
		$synced_id = $this->insert_probe(['name' => 'Multi-stage synced', 'sync_enabled' => 2]);
		$stale_id = $this->insert_probe(['name' => 'Not re-synced', 'sync_enabled' => 0]);

		self::$model->sync_commit_enabled();

		$this->assertNotNull(self::$model->get($synced_id));
		$this->assertNull(self::$model->get($stale_id));
	}
}
