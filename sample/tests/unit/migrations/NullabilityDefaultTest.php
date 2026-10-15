<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/../../support/MigrationBuilderHarness.php';

/**
 * An unspecified `nullable` is NOT NULL on create, add and modify alike, and assert_no_nulls()
 * guards a tightening. Schema checks skip where information_schema is not scoped by DATABASE()/current_schema().
 */
class NullabilityDefaultTest extends CITestCase
{
	private const TABLE = 'phpunit_nullability';

	private MigrationBuilderHarness $builder;
	private MgrDriver $driver;

	protected function setUp(): void
	{
		$this->load->dbforge();
		$this->builder = new MigrationBuilderHarness();
		$this->driver  = MgrDriver::fromCI($this->db->dbdriver ?? '', subdriver: $this->db->subdriver ?? null);

		$this->drop_table();
	}

	protected function tearDown(): void
	{
		$this->drop_table();
	}

	public function test_an_unspecified_nullable_resolves_to_not_null_and_an_explicit_one_is_kept(): void
	{
		$unspecified = $this->builder->build_field(name: 'a', type: MgrFieldType::VarChar, constraint: 10);
		$nullable    = $this->builder->build_field(name: 'a', type: MgrFieldType::VarChar, constraint: 10, nullable: true);
		$not_null    = $this->builder->build_field(name: 'a', type: MgrFieldType::VarChar, constraint: 10, nullable: false);

		$this->assertFalse($unspecified['a']['null']);
		$this->assertTrue($nullable['a']['null']);
		$this->assertFalse($not_null['a']['null']);
	}

	public function test_modify_column_without_nullable_keeps_a_not_null_column_not_null(): void
	{
		$this->skip_without_information_schema();

		$this->create_table([
			...$this->builder->build_field(name: 'kept', type: MgrFieldType::VarChar, constraint: 50),
			...$this->builder->build_field(name: 'loose', type: MgrFieldType::VarChar, constraint: 50, nullable: true),
		]);

		$this->dbforge->modify_column(self::TABLE, [
			...$this->builder->build_field(name: 'kept', type: MgrFieldType::VarChar, constraint: 80),
			...$this->builder->build_field(name: 'loose', type: MgrFieldType::VarChar, constraint: 80, nullable: true),
		]);

		$this->assertSame('NO', $this->is_nullable('kept'));
		$this->assertSame('YES', $this->is_nullable('loose'));
	}

	public function test_add_column_without_nullable_is_not_null(): void
	{
		$this->skip_without_information_schema();

		$this->create_table($this->builder->build_field(name: 'seed', type: MgrFieldType::VarChar, constraint: 10));
		$this->dbforge->add_column(self::TABLE, [
			...$this->builder->build_field(name: 'added', type: MgrFieldType::VarChar, constraint: 10),
			...$this->builder->build_field(name: 'added_loose', type: MgrFieldType::VarChar, constraint: 10, nullable: true),
		]);

		$this->assertSame('NO', $this->is_nullable('added'));
		$this->assertSame('YES', $this->is_nullable('added_loose'));
	}

	public function test_assert_no_nulls_names_the_column_and_the_row_ids_and_passes_once_clean(): void
	{
		$this->create_table([
			...$this->builder->build_field(name: 'id', type: MgrFieldType::Int, unsigned: true, auto_increment: true),
			...$this->builder->build_field(name: 'clean', type: MgrFieldType::VarChar, constraint: 10, nullable: true),
			...$this->builder->build_field(name: 'dirty', type: MgrFieldType::VarChar, constraint: 10, nullable: true),
		], primary_key: 'id');
		$this->db->insert(self::TABLE, ['clean' => 'x', 'dirty' => null]);
		$row_id = $this->db->insert_id();

		try {
			$this->builder->check_no_nulls(table: self::TABLE, columns: ['clean', 'dirty']);
			$this->fail('assert_no_nulls() should have thrown.');
		} catch (RuntimeException $exception) {
			$this->assertStringContainsString(self::TABLE . '.dirty: 1 row(s)', $exception->getMessage());
			$this->assertStringContainsString("[{$row_id}]", $exception->getMessage());
			$this->assertStringNotContainsString(self::TABLE . '.clean', $exception->getMessage());
		}

		$this->db->update(self::TABLE, ['dirty' => 'y']);
		$this->builder->check_no_nulls(table: self::TABLE, columns: ['clean', 'dirty']);
		$this->addToAssertionCount(1);
	}

	private function skip_without_information_schema(): void
	{
		if (in_array($this->driver, [MgrDriver::SQLite, MgrDriver::SQLServer], true)) {
			$this->markTestSkipped('Needs ALTER COLUMN and an information_schema scoped by DATABASE()/current_schema().');
		}
	}

	private function is_nullable(string $column): string
	{
		$scope = $this->driver === MgrDriver::Postgres ? 'current_schema()' : 'DATABASE()';
		$row   = $this->db->query(
			"SELECT is_nullable AS nullable FROM information_schema.columns WHERE table_schema = {$scope} AND table_name = ? AND column_name = ?",
			[self::TABLE, $column]
		)->row();

		return (string) $row->nullable;
	}

	private function create_table(array $fields, ?string $primary_key = null): void
	{
		$this->dbforge->add_field($fields);
		if ($primary_key !== null) {
			$this->dbforge->add_key($primary_key, true);
		}
		$this->dbforge->create_table(self::TABLE);
	}

	private function drop_table(): void
	{
		$this->db->query('DROP TABLE IF EXISTS ' . self::TABLE);
	}
}
