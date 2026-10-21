<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/../../support/MigrationBuilderHarness.php';

/**
 * `modify_column()` with a field's `unique` leaves exactly one unique on the column however often it
 * runs, finds a unique another path made, puts it on the new name after a rename, and refuses a plain
 * index that holds the name, or a rename over an existing unique, without leaving that call's fields
 * on the shared forge.
 * SQL Server and SQLite are not covered.
 */
class ModifyColumnUniqueTest extends CITestCase
{
	private const TABLE = 'phpunit_modify_unique';

	private MigrationBuilderHarness $builder;
	private MgrDriver $driver;
	private ?bool $saved_db_debug = null;

	protected function setUp(): void
	{
		$this->load->database();
		$this->load->dbforge();

		$this->driver = MgrDriver::fromCI($this->db->dbdriver ?? '', subdriver: $this->db->subdriver ?? null);
		if ($this->driver !== MgrDriver::Postgres && !$this->driver->isMysqlFamily()) {
			$this->markTestSkipped('Covered on PostgreSQL, MySQL and MariaDB.');
		}

		$this->builder        = new MigrationBuilderHarness();
		$this->saved_db_debug = $this->db->db_debug;
		// A failed statement must come back as false, not halt the run.
		$this->db->db_debug = false;

		$this->drop_table();
	}

	protected function tearDown(): void
	{
		if ($this->saved_db_debug === null) {
			return;
		}

		$this->db->db_debug = $this->saved_db_debug;
		$this->drop_table();
	}

	public function test_a_repeated_call_leaves_one_unique_index_that_rejects_a_duplicate(): void
	{
		$this->create_table(['email' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]]);
		$field = $this->builder->build_field(name: 'email', type: MgrFieldType::VarChar, constraint: 100, nullable: true, unique: true);

		$this->assertTrue($this->dbforge->modify_column(self::TABLE, $field));
		$this->assertTrue($this->dbforge->modify_column(self::TABLE, $field));

		$this->assertSame([$this->derived_unique_name('email')], $this->unique_indexes('email'));
		$this->assertNotFalse($this->db->insert(self::TABLE, ['email' => 'dup@example.test']));
		$this->assertFalse($this->expect_failed_query(fn () => $this->db->insert(self::TABLE, ['email' => 'dup@example.test'])));
	}

	public function test_a_unique_another_path_made_is_found_and_nothing_is_added(): void
	{
		$this->create_table(['id' => ['type' => 'INT', 'null' => false]]);
		$this->dbforge->add_column(self::TABLE, $this->builder->build_field(name: 'email', type: MgrFieldType::VarChar, constraint: 50, nullable: true, unique: true));
		$before = $this->unique_indexes('email');

		$this->assertCount(1, $before);
		$this->assertTrue($this->dbforge->modify_column(self::TABLE, $this->builder->build_field(name: 'email', type: MgrFieldType::VarChar, constraint: 60, nullable: true, unique: true)));
		$this->assertSame($before, $this->unique_indexes('email'));
	}

	public function test_a_rename_in_the_same_call_puts_the_unique_on_the_new_name(): void
	{
		$this->create_table(['old_email' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]]);

		$this->assertTrue($this->dbforge->modify_column(self::TABLE, $this->builder->build_field(
			name: 'old_email',
			type: MgrFieldType::VarChar,
			constraint: 50,
			nullable: true,
			unique: true,
			new_name: 'new_email',
		)));

		$this->assertSame([$this->derived_unique_name('new_email')], $this->unique_indexes('new_email'));
		$this->assertNotFalse($this->db->insert(self::TABLE, ['new_email' => 'dup@example.test']));
		$this->assertFalse($this->expect_failed_query(fn () => $this->db->insert(self::TABLE, ['new_email' => 'dup@example.test'])));
	}

	public function test_a_rename_with_unique_over_an_existing_unique_throws_before_anything_runs(): void
	{
		$this->create_table(['other' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]]);
		$this->dbforge->add_column(self::TABLE, $this->builder->build_field(name: 'old_email', type: MgrFieldType::VarChar, constraint: 50, nullable: true, unique: true));
		$before = $this->unique_indexes('old_email');

		$this->assertCount(1, $before);

		$failure = $this->thrown(fn () => $this->dbforge->modify_column(self::TABLE, [
			...$this->builder->build_field(name: 'other', type: MgrFieldType::VarChar, constraint: 60, nullable: true),
			...$this->builder->build_field(name: 'old_email', type: MgrFieldType::VarChar, constraint: 50, nullable: true, unique: true, new_name: 'new_email'),
		]));

		$this->assertInstanceOf(RuntimeException::class, $failure);
		$this->assertStringContainsString('drop_index()', $failure->getMessage());
		$this->assertSame($before, $this->unique_indexes('old_email'));
		$this->assertSame(50, $this->column_length('other'));

		// A leaked `old_email` field would rename it with this next call.
		$this->assertTrue($this->dbforge->modify_column(self::TABLE, $this->builder->build_field(name: 'other', type: MgrFieldType::VarChar, constraint: 70, nullable: true)));
		$this->assertSame(50, $this->column_length('old_email'));
		$this->assertSame(70, $this->column_length('other'));
	}

	public function test_dropping_the_old_unique_first_leaves_one_unique_on_the_new_name(): void
	{
		$this->create_table(['id' => ['type' => 'INT', 'null' => false]]);
		$this->dbforge->add_column(self::TABLE, $this->builder->build_field(name: 'old_email', type: MgrFieldType::VarChar, constraint: 50, nullable: true, unique: true));

		$this->assertTrue($this->builder->remove_index(self::TABLE, 'old_email', unique: true));
		$this->assertTrue($this->dbforge->modify_column(self::TABLE, $this->builder->build_field(
			name: 'old_email',
			type: MgrFieldType::VarChar,
			constraint: 50,
			nullable: true,
			unique: true,
			new_name: 'new_email',
		)));

		$this->assertSame([$this->derived_unique_name('new_email')], $this->unique_indexes('new_email'));
		$this->assertSame([], $this->unique_indexes('old_email'));
	}

	public function test_a_plain_index_under_the_name_throws_and_leaves_no_field_on_the_forge(): void
	{
		$this->create_table([
			'email' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
			'other' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
		]);
		$this->create_plain_index($this->derived_unique_name('email'), 'email');

		$failure = $this->thrown(fn () => $this->dbforge->modify_column(
			self::TABLE,
			$this->builder->build_field(name: 'email', type: MgrFieldType::VarChar, constraint: 100, nullable: true, unique: true)
		));

		$this->assertInstanceOf(RuntimeException::class, $failure);
		$this->assertStringContainsString('drop_index()', $failure->getMessage());
		$this->assertSame([], $this->unique_indexes('email'));

		// A leaked `email` field would widen it to 100 with this next call.
		$this->assertTrue($this->dbforge->modify_column(self::TABLE, $this->builder->build_field(name: 'other', type: MgrFieldType::VarChar, constraint: 60, nullable: true)));
		$this->assertSame(50, $this->column_length('email'));
		$this->assertSame(60, $this->column_length('other'));
	}

	private function derived_unique_name(string $column): string
	{
		return $this->dbforge->index_name(self::TABLE, [$column], unique: true);
	}

	private function create_table(array $fields): void
	{
		$this->dbforge->add_field($fields);
		$this->dbforge->create_table(self::TABLE);
	}

	private function drop_table(): void
	{
		$this->db->query('DROP TABLE IF EXISTS ' . self::TABLE);
	}

	private function create_plain_index(string $index_name, string $column): void
	{
		$index_ident  = $this->db->escape_identifiers($index_name);
		$column_ident = $this->db->escape_identifiers($column);

		$this->db->query($this->driver === MgrDriver::Postgres
			? "CREATE INDEX {$index_ident} ON " . self::TABLE . " ({$column_ident})"
			: 'ALTER TABLE ' . self::TABLE . " ADD INDEX {$index_ident} ({$column_ident})");
	}

	/** @return string[] names of the unique indexes (constraint-backed included) over a column of the table */
	private function unique_indexes(string $column): array
	{
		$rows = $this->driver === MgrDriver::Postgres
			? $this->db->query(
				'SELECT ic.relname AS index_name FROM pg_index i '
					. 'JOIN pg_class ic ON ic.oid = i.indexrelid '
					. 'JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) '
					. 'WHERE i.indrelid = ?::regclass AND a.attname = ? AND i.indisunique AND NOT i.indisprimary ORDER BY ic.relname',
				[self::TABLE, $column]
			)->result()
			: $this->db->query(
				'SELECT DISTINCT index_name AS index_name FROM information_schema.statistics '
					. 'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? AND non_unique = 0 AND index_name <> \'PRIMARY\' '
					. 'ORDER BY index_name',
				[self::TABLE, $column]
			)->result();

		return array_map(static fn ($row) => (string) $row->index_name, $rows);
	}

	private function column_length(string $column): int
	{
		$scope = $this->driver === MgrDriver::Postgres ? 'current_schema()' : 'DATABASE()';

		return (int) $this->db->query(
			"SELECT character_maximum_length AS length FROM information_schema.columns WHERE table_schema = {$scope} AND table_name = ? AND column_name = ?",
			[self::TABLE, $column]
		)->row()->length;
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

	/** Runs a query that is meant to fail; the native postgre driver also raises an E_WARNING for it, which PHPUnit would report. */
	private function expect_failed_query(callable $callback): mixed
	{
		$previous = null;
		$previous = set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$previous): bool {
			if ($severity === E_WARNING && str_contains($message, 'Query failed')) {
				return true;
			}

			return $previous !== null && $previous($severity, $message, $file, $line);
		});

		try {
			return $callback();
		} finally {
			restore_error_handler();
		}
	}
}
