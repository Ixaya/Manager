<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/../../support/DynProbeFixture.php';

/**
 * Proves a model that extends APP_Model_Dyn with zero added methods can
 * still do everything a hand-written model would need: plain CRUD, soft
 * delete, last_update stamping, and dynamic where/join queries. This is a
 * capability test, not a test of MGR_Model_Dyn's SQL-generation
 * correctness — clause escaping/edge cases are that class's own contract.
 *
 * The exception-safety and OR-clause-scoping tests below are regressions
 * for bugs found live (via the probes/ sweep) rather than capability checks.
 */
class DynModelTest extends CITestCase
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

	/**
	 * create_date is never stamped by the model itself — callers set it
	 * explicitly on insert, same as any MY_Model subclass.
	 */
	private function insert_probe(array $data): int|string|bool
	{
		return self::$model->insert([...$data, 'create_date' => date('Y-m-d H:i:s')]);
	}

	public function test_insert_get_update_and_delete_roundtrip(): void
	{
		$id = $this->insert_probe(['name' => 'alpha', 'status' => 1]);
		$this->assertNotFalse($id);

		$row = self::$model->get($id);
		$this->assertSame('alpha', $row['name']);

		$this->assertTrue(self::$model->update(['name' => 'alpha-2'], $id));
		$row = self::$model->get($id);
		$this->assertSame('alpha-2', $row['name']);

		$this->assertTrue(self::$model->delete($id));
		$this->assertNull(self::$model->get($id));
	}

	public function test_soft_delete_sets_flags_and_excludes_from_reads(): void
	{
		$id = $this->insert_probe(['name' => 'beta']);
		self::$model->delete($id);

		$raw = get_instance()->db->where('id', $id)->get('dyn_probe_test')->row_array();
		$this->assertSame(0, (int) $raw['enabled']);
		$this->assertSame(1, (int) $raw['deleted']);

		$this->assertNull(self::$model->get($id));
		$this->assertSame([], self::$model->get_all(where: ['id' => $id]));
	}

	public function test_last_update_is_stamped_on_write(): void
	{
		$id = $this->insert_probe(['name' => 'gamma']);
		$row = self::$model->get($id);

		$this->assertNotNull($row['last_update']);
	}

	public function test_get_all_dynamic_equal_and_like_filters_rows(): void
	{
		$this->insert_probe(['name' => 'Widget Alpha', 'status' => 1]);
		$this->insert_probe(['name' => 'Widget Beta', 'status' => 2]);
		$this->insert_probe(['name' => 'Gadget Gamma', 'status' => 2]);

		$rows = self::$model->get_all_dynamic(where: [
			MGR_Model_Dyn_clause::EQUAL => ['status' => 2],
			MGR_Model_Dyn_clause::LIKE  => ['name' => 'Widget'],
		]);

		$this->assertCount(1, $rows);
		$this->assertSame('Widget Beta', $rows[0]['name']);
	}

	public function test_get_all_dynamic_where_in_inside_group_and_or_group(): void
	{
		$this->insert_probe(['name' => 'Widget Alpha', 'status' => 1]);
		$this->insert_probe(['name' => 'Widget Beta', 'status' => 2]);
		$this->insert_probe(['name' => 'Gadget Gamma', 'status' => 3]);
		$this->insert_probe(['name' => 'Sprocket Delta', 'status' => 4]);

		$rows = self::$model->get_all_dynamic(where: [
			MGR_Model_Dyn_clause::GROUP => [
				MGR_Model_Dyn_clause::LIKE     => ['name' => 'Widget'],
				MGR_Model_Dyn_clause::WHERE_IN => ['status' => [1, 2]],
			],
			MGR_Model_Dyn_clause::OR_GROUP => [
				MGR_Model_Dyn_clause::WHERE_IN => ['status' => [3]],
			],
		]);

		$names = array_column($rows, 'name');
		sort($names);
		$this->assertSame(['Gadget Gamma', 'Widget Alpha', 'Widget Beta'], $names);
	}

	public function test_get_all_dynamic_join_resolves_related_table(): void
	{
		$category_id = get_instance()->db->insert('dyn_probe_category_test', ['name' => 'Category One'])
			? get_instance()->db->insert_id()
			: null;
		$this->insert_probe(['name' => 'Linked', 'category_id' => $category_id]);
		$this->insert_probe(['name' => 'Unlinked']);

		$join = self::$model->build_join(
			table: 'dyn_probe_category_test',
			type: MGR_Model_Dyn_join_type::Inner,
			on: [MGR_Model_Dyn_clause::EQUAL_COL => ['dyn_probe_category_test.id' => 'dyn_probe_test.category_id']],
		);

		$rows = self::$model->get_all_dynamic(
			fields: 'dyn_probe_test.name, dyn_probe_category_test.name as category_name',
			join: [$join],
		);

		$this->assertCount(1, $rows);
		$this->assertSame('Linked', $rows[0]['name']);
		$this->assertSame('Category One', $rows[0]['category_name']);
	}

	/**
	 * Regression: an OR_* clause used as the caller's condition must not
	 * glue onto the implicit soft-delete filter with OR and defeat it —
	 * every row here has enabled/deleted at their defaults, so an OR
	 * defeat would resurrect a soft-deleted row into the results.
	 */
	public function test_get_all_dynamic_or_clauses_do_not_defeat_soft_delete(): void
	{
		$live_id = $this->insert_probe(['name' => 'Widget Alpha', 'status' => 1]);
		$deleted_id = $this->insert_probe(['name' => 'Widget Beta', 'status' => 2]);
		self::$model->delete($deleted_id);

		$rows = self::$model->get_all_dynamic(where: [
			MGR_Model_Dyn_clause::OR_EQUAL    => ['status' => 1],
			MGR_Model_Dyn_clause::OR_LIKE     => ['name' => 'Widget'],
			MGR_Model_Dyn_clause::OR_WHERE_IN => ['status' => [2]],
		]);

		$ids = array_map('intval', array_column($rows, 'id'));
		$this->assertContains((int) $live_id, $ids);
		$this->assertNotContains((int) $deleted_id, $ids);
	}

	/**
	 * Regression: a thrown InvalidArgumentException (empty WHERE_IN list,
	 * here) must not leave the query builder holding an unclosed group()/
	 * stray where() for the next query on the same connection.
	 */
	public function test_get_all_dynamic_resets_query_builder_after_throw(): void
	{
		$this->insert_probe(['name' => 'Survives the throw']);

		try {
			self::$model->get_all_dynamic(where: [MGR_Model_Dyn_clause::WHERE_IN => ['status' => []]]);
			$this->fail('Expected an InvalidArgumentException.');
		} catch (InvalidArgumentException) {
			// expected
		}

		$rows = self::$model->get_all_dynamic(where: [MGR_Model_Dyn_clause::EQUAL => ['name' => 'Survives the throw']]);
		$this->assertCount(1, $rows);
	}

	/**
	 * Documents a real trap: EQUAL_COL in $where compares against the
	 * literal string given, NOT a second column — unlike EQUAL_COL inside
	 * a join's on[], where both sides are column identifiers.
	 */
	public function test_equal_col_in_where_is_a_literal_not_a_column_reference(): void
	{
		$this->insert_probe(['name' => 'category_id']);
		$this->insert_probe(['name' => 'something else']);

		$rows = self::$model->get_all_dynamic(where: [
			MGR_Model_Dyn_clause::EQUAL_COL => ['name' => 'category_id'],
		]);

		$this->assertCount(1, $rows);
		$this->assertSame('category_id', $rows[0]['name']);
	}

	/**
	 * Regression: override_column tenant scoping must apply to
	 * get_all_dynamic exactly like every other read method.
	 */
	public function test_get_all_dynamic_respects_override_column_scoping(): void
	{
		self::$model->set_override_column('tenant_id');

		self::$model->set_override(1);
		$this->insert_probe(['name' => 'Tenant 1 row']);

		self::$model->set_override(2);
		$this->insert_probe(['name' => 'Tenant 2 row']);

		self::$model->set_override(1);
		$rows = self::$model->get_all_dynamic(where: [MGR_Model_Dyn_clause::LIKE => ['name' => 'row']]);

		$this->assertCount(1, $rows);
		$this->assertSame('Tenant 1 row', $rows[0]['name']);
	}
}
