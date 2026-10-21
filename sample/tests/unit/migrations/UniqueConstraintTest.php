<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/../../support/MigrationBuilderHarness.php';

/**
 * On PostgreSQL a field's `unique` is a constraint, on every path including `modify_column()`.
 * The builder's `drop_index()` removes it as one, refuses the index behind a primary key, and
 * leaves a plain index alone even when a foreign key carries its name. Every case skips on the
 * other engines.
 */
class UniqueConstraintTest extends CITestCase
{
	private const TABLE = 'phpunit_unique';
	private const PARENT = 'phpunit_unique_parent';
	private const MIXED_CASE = 'Phpunit_Unique_Mixed';
	private const LONG_NAME = 'phpunit_unique_tttttttttttttttttttttttttttttttttttttttt';
	private const LONG_COLUMN = 'cccccccccccccccccccccccccccccc';

	private MigrationBuilderHarness $builder;
	private ?bool $saved_db_debug = null;

	protected function setUp(): void
	{
		$this->load->database();
		$this->load->dbforge();

		$driver = MgrDriver::fromCI($this->db->dbdriver ?? '', subdriver: $this->db->subdriver ?? null);
		if ($driver !== MgrDriver::Postgres) {
			$this->markTestSkipped('Unique constraints and their catalog are PostgreSQL specific.');
		}

		$this->builder        = new MigrationBuilderHarness();
		$this->saved_db_debug = $this->db->db_debug;
		$this->db->db_debug   = false;

		$this->drop_tables();
	}

	protected function tearDown(): void
	{
		if ($this->saved_db_debug === null) {
			return;
		}

		$this->db->db_debug = $this->saved_db_debug;
		$this->drop_tables();
	}

	public function test_drop_index_drops_an_add_path_unique_as_a_constraint(): void
	{
		$this->create_table([...$this->builder->build_field(name: 'id', type: MgrFieldType::Int)]);
		$this->dbforge->add_column(self::TABLE, $this->builder->build_field(name: 'email', type: MgrFieldType::VarChar, constraint: 100, nullable: true, unique: true));

		$this->assertSame(['phpunit_unique_email_key' => 'u'], $this->constraints());

		$this->assertTrue($this->builder->remove_index(self::TABLE, 'email', unique: true));
		$this->assertSame([], $this->constraints());
		$this->assertSame([], $this->indexes());
		$this->assertNotFalse($this->db->insert(self::TABLE, ['id' => 1, 'email' => 'dup@example.test']));
		$this->assertNotFalse($this->db->insert(self::TABLE, ['id' => 2, 'email' => 'dup@example.test']));

		$this->assertFalse($this->builder->remove_index(self::TABLE, 'email', unique: true));
	}

	public function test_drop_index_refuses_the_index_behind_a_primary_key_and_keeps_it(): void
	{
		$this->dbforge->add_field([...$this->builder->build_field(name: 'id', type: MgrFieldType::Int)]);
		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table(self::TABLE);

		[$primary_key] = array_keys($this->constraints());
		$failure       = $this->thrown(fn () => $this->builder->remove_index(self::TABLE, 'id', name: $primary_key));

		$this->assertInstanceOf(RuntimeException::class, $failure);
		$this->assertStringContainsString('drop_primary_key()', $failure->getMessage());
		$this->assertSame([$primary_key => 'p'], $this->constraints());
	}

	public function test_drop_index_drops_a_plain_index_whose_name_a_foreign_key_shares(): void
	{
		$this->dbforge->add_field([...$this->builder->build_field(name: 'id', type: MgrFieldType::Int)]);
		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table(self::PARENT);
		$this->create_table([...$this->builder->build_field(name: 'parent_id', type: MgrFieldType::Int)]);

		$this->db->query('ALTER TABLE ' . self::TABLE . ' ADD CONSTRAINT phpunit_unique_shared FOREIGN KEY (parent_id) REFERENCES ' . self::PARENT . ' (id)');
		$this->db->query('CREATE INDEX phpunit_unique_shared ON ' . self::TABLE . ' (parent_id)');

		$this->assertTrue($this->builder->remove_index(self::TABLE, 'parent_id', name: 'phpunit_unique_shared'));
		$this->assertSame([], $this->indexes());
		$this->assertSame(['phpunit_unique_shared' => 'f'], $this->constraints());
	}

	public function test_modify_column_unique_makes_a_constraint_that_drop_index_removes(): void
	{
		$this->create_table([...$this->builder->build_field(name: 'email', type: MgrFieldType::VarChar, constraint: 50, nullable: true)]);
		$this->dbforge->modify_column(self::TABLE, $this->builder->build_field(name: 'email', type: MgrFieldType::VarChar, constraint: 100, nullable: true, unique: true));

		$this->assertSame(['phpunit_unique_email_key' => 'u'], $this->constraints());
		$this->assertSame([], $this->indexes());

		$this->assertTrue($this->builder->remove_index(self::TABLE, 'email', unique: true));
		$this->assertSame([], $this->constraints());
	}

	public function test_modify_column_unique_over_an_add_path_unique_on_a_mixed_case_name_adds_no_second_constraint(): void
	{
		$this->assert_modify_unique_finds_the_add_path_unique(self::MIXED_CASE, 'Email');
	}

	public function test_modify_column_unique_over_an_add_path_unique_past_63_characters_adds_no_second_constraint(): void
	{
		$this->assert_modify_unique_finds_the_add_path_unique(self::LONG_NAME, self::LONG_COLUMN);
	}

	/** The engine names the ADD-path constraint; the CHANGE path must derive the same name and drop_index() find it. */
	private function assert_modify_unique_finds_the_add_path_unique(string $table, string $column): void
	{
		$this->create_table([...$this->builder->build_field(name: 'id', type: MgrFieldType::Int)], $table);
		$this->dbforge->add_column($table, $this->builder->build_field(name: $column, type: MgrFieldType::VarChar, constraint: 50, nullable: true, unique: true));

		$engine_named = $this->constraints($table);
		$this->assertCount(1, $engine_named);
		$this->assertSame($this->dbforge->index_name($table, [$column], unique: true), array_key_first($engine_named));

		$this->assertTrue($this->dbforge->modify_column($table, $this->builder->build_field(name: $column, type: MgrFieldType::VarChar, constraint: 60, nullable: true, unique: true)));
		$this->assertSame($engine_named, $this->constraints($table));

		$this->assertTrue($this->builder->remove_index($table, $column, unique: true));
		$this->assertSame([], $this->constraints($table));
	}

	private function create_table(array $fields, string $table = self::TABLE): void
	{
		$this->dbforge->add_field($fields);
		$this->dbforge->create_table($table);
	}

	private function drop_tables(): void
	{
		foreach ([self::TABLE, self::PARENT, self::MIXED_CASE, self::LONG_NAME] as $table) {
			$this->db->query('DROP TABLE IF EXISTS ' . $this->db->escape_identifiers($table));
		}
	}

	/** @return array<string, string> constraint name => kind, for a table; NOT NULL constraints (PostgreSQL 18) left out */
	private function constraints(string $table = self::TABLE): array
	{
		$rows = $this->db->query(
			"SELECT conname, contype FROM pg_constraint WHERE conrelid = ?::regclass AND contype <> 'n' ORDER BY conname",
			[$this->db->escape_identifiers($table)]
		)->result();

		return array_column(array_map(static fn ($row) => [$row->conname, $row->contype], $rows), 1, 0);
	}

	/** @return string[] index names on the table under test, the ones a constraint backs excluded */
	private function indexes(): array
	{
		$rows = $this->db->query(
			'SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? '
				. 'AND indexname NOT IN (SELECT conname FROM pg_constraint WHERE conrelid = ?::regclass) ORDER BY indexname',
			[self::TABLE, self::TABLE]
		)->result();

		return array_column($rows, 'indexname');
	}

	private function thrown(callable $callback): ?Throwable
	{
		try {
			$callback();
		} catch (Throwable $exception) {
			return $exception;
		}

		return null;
	}
}
