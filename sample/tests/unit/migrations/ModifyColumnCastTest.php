<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/../../support/MigrationBuilderHarness.php';

/**
 * On PostgreSQL a field that sets `cast` changes type across families with `USING`, while a plain
 * `modify_column()` keeps failing there and never rewrites a table it does not have to.
 * The attribute is a PostgreSQL forge feature, so every case skips elsewhere.
 */
class ModifyColumnCastTest extends CITestCase
{
	private const TABLE = 'phpunit_cast';

	private MigrationBuilderHarness $builder;
	private ?bool $saved_db_debug = null;

	protected function setUp(): void
	{
		$this->load->database();
		$this->load->dbforge();

		$driver = MgrDriver::fromCI($this->db->dbdriver ?? '', subdriver: $this->db->subdriver ?? null);
		if ($driver !== MgrDriver::Postgres) {
			$this->markTestSkipped('`cast` is a PostgreSQL forge attribute.');
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

	public function test_a_cross_family_change_fails_without_cast_and_succeeds_with_it(): void
	{
		$this->create_table(['code' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false]]);
		$this->db->insert(self::TABLE, ['code' => '42']);

		$plain = $this->expect_failed_query(fn () => $this->dbforge->modify_column(self::TABLE, [
			'code' => ['type' => 'INT', 'null' => false],
		]));

		$this->assertFalse($plain);
		$this->assertSame('character varying(20)', $this->column_type('code'));

		$cast = $this->dbforge->modify_column(self::TABLE, [
			'code' => ['type' => 'INT', 'null' => false, 'cast' => true],
		]);

		$this->assertTrue($cast);
		$this->assertSame('integer', $this->column_type('code'));
		$this->assertSame(42, (int) $this->db->get(self::TABLE)->row()->code);
	}

	public function test_an_overlong_value_is_rejected_and_the_stored_value_is_kept(): void
	{
		$this->create_table(['txt' => ['type' => 'TEXT', 'null' => true]]);
		$this->db->insert(self::TABLE, ['txt' => 'abcdefghij']);

		$narrowed = $this->expect_failed_query(fn () => $this->dbforge->modify_column(self::TABLE, [
			'txt' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true, 'cast' => true],
		]));

		$this->assertFalse($narrowed);
		$this->assertSame('text', $this->column_type('txt'));
		$this->assertSame('abcdefghij', $this->db->get(self::TABLE)->row()->txt);
	}

	public function test_char_casts_through_text_so_a_value_is_not_cut_to_one_character(): void
	{
		$this->create_table(['ch' => ['type' => 'CHAR', 'constraint' => 3, 'null' => true]]);
		$this->db->insert(self::TABLE, ['ch' => 'ab']);

		$this->assertTrue($this->dbforge->modify_column(self::TABLE, [
			'ch' => ['type' => 'CHAR', 'constraint' => 5, 'null' => true, 'cast' => true],
		]));

		$this->assertSame('character(5)', $this->column_type('ch'));
		$this->assertSame('ab', rtrim((string) $this->db->get(self::TABLE)->row()->ch));
	}

	public function test_a_multi_word_type_name_survives_the_cast(): void
	{
		$this->create_table(['n' => ['type' => 'INT', 'null' => true]]);
		$this->db->insert(self::TABLE, ['n' => 7]);

		$this->assertTrue($this->dbforge->modify_column(self::TABLE, [
			'n' => ['type' => 'DOUBLE PRECISION', 'null' => true, 'cast' => true],
		]));

		$this->assertSame('double precision', $this->column_type('n'));
		$this->assertSame(7.0, (float) $this->db->get(self::TABLE)->row()->n);
	}

	public function test_a_widened_unsigned_type_casts_to_its_widened_base(): void
	{
		$this->create_table(['v' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true]]);
		$this->db->insert(self::TABLE, ['v' => '18446744073709551615']);

		$this->builder->cast_column(self::TABLE, $this->builder->build_field(name: 'v', type: MgrFieldType::BigInt, unsigned: true, nullable: true));

		$this->assertSame('numeric(20,0)', $this->column_type('v'));
		$this->assertSame('18446744073709551615', (string) $this->db->get(self::TABLE)->row()->v);
	}

	public function test_a_plain_varchar_widening_does_not_rewrite_the_table(): void
	{
		$this->create_table(['v' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]]);
		$this->db->query("INSERT INTO " . self::TABLE . " (v) SELECT 'row ' || g FROM generate_series(1, 100) g");
		$before = $this->relfilenode();

		$this->assertTrue($this->dbforge->modify_column(self::TABLE, [
			'v' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
		]));

		$this->assertSame('character varying(120)', $this->column_type('v'));
		$this->assertSame($before, $this->relfilenode());
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

	private function column_type(string $column): string
	{
		return (string) $this->db->query(
			'SELECT format_type(a.atttypid, a.atttypmod) AS type FROM pg_attribute a WHERE a.attrelid = ?::regclass AND a.attname = ?',
			[self::TABLE, $column]
		)->row()->type;
	}

	/** Changes only when the table is rewritten. */
	private function relfilenode(): string
	{
		return (string) $this->db->query('SELECT relfilenode FROM pg_class WHERE oid = ?::regclass', [self::TABLE])->row()->relfilenode;
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
