<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/../../support/MigrationBuilderHarness.php';

/**
 * `auto_increment` numbers inserts on every engine, continues after a `modify_column()`, and
 * `add_auto_increment()` numbers the rows holding `0` or `NULL` past the highest existing value
 * instead of in the engine's scan order, which could duplicate a real id.
 */
class AutoIncrementTest extends CITestCase
{
	private const TABLE = 'phpunit_auto_increment';

	private MigrationBuilderHarness $builder;
	private MgrDriver $driver;

	protected function setUp(): void
	{
		$this->load->database();
		$this->load->dbforge();
		$this->builder = new MigrationBuilderHarness();
		$this->driver  = MgrDriver::fromCI($this->db->dbdriver ?? '', subdriver: $this->db->subdriver ?? null);

		$this->drop_table();
	}

	protected function tearDown(): void
	{
		$this->drop_table();
	}

	public function test_a_field_id_primary_key_numbers_each_insert(): void
	{
		$this->create_keyed_table($this->id_field(MgrFieldType::Int));

		$this->db->insert(self::TABLE, ['label' => 'a']);
		$first = (int) $this->db->insert_id();
		$this->db->insert(self::TABLE, ['label' => 'b']);
		$second = (int) $this->db->insert_id();

		$this->assertSame([1, 2], [$first, $second]);
	}

	public function test_widening_an_auto_increment_column_keeps_numbering(): void
	{
		$this->create_keyed_table($this->id_field(MgrFieldType::Int));
		$this->db->insert(self::TABLE, ['label' => 'a']);
		$this->db->insert(self::TABLE, ['label' => 'b']);

		$this->dbforge->modify_column(self::TABLE, $this->id_field(MgrFieldType::BigInt));
		$this->db->insert(self::TABLE, ['label' => 'c']);

		$this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $this->ids_by_label());
	}

	public function test_modify_column_auto_increment_continues_past_the_highest_id(): void
	{
		$this->create_keyed_table(['id' => ['type' => 'INT', 'null' => false]]);
		$this->db->query('INSERT INTO ' . self::TABLE . " (id, label) VALUES (5, 'five'), (9, 'nine')");

		$this->dbforge->modify_column(self::TABLE, ['id' => ['type' => 'INT', 'null' => false, 'auto_increment' => true]]);
		$this->db->insert(self::TABLE, ['label' => 'next']);

		$this->assertSame(['five' => 5, 'next' => 10, 'nine' => 9], $this->ids_by_label());
	}

	public function test_add_auto_increment_numbers_a_zero_row_past_the_highest_id(): void
	{
		$this->create_plain_table("(1, 'a'), (2, 'b'), (0, 'c')");

		$this->add_auto_increment();
		$this->db->insert(self::TABLE, ['label' => 'next']);

		$this->assertSame(['a' => 1, 'b' => 2, 'c' => 3, 'next' => 4], $this->ids_by_label());
	}

	public function test_add_auto_increment_never_gives_a_zero_row_the_id_of_a_later_row(): void
	{
		$this->create_plain_table("(0, 'a'), (1, 'b'), (NULL, 'c'), (7, 'd')");

		$this->add_auto_increment();
		$this->db->insert(self::TABLE, ['label' => 'next']);

		$ids = $this->ids_by_label();
		$this->assertSame(1, $ids['b']);
		$this->assertSame(7, $ids['d']);
		$this->assertCount(5, array_unique($ids));
		$this->assertSame(max($ids), $ids['next']);
	}

	public function test_add_auto_increment_starts_an_empty_table_at_one(): void
	{
		$this->create_plain_table(null);

		$this->add_auto_increment();
		$this->db->insert(self::TABLE, ['label' => 'first']);

		$this->assertSame(['first' => 1], $this->ids_by_label());
	}

	public function test_postgres_makes_an_identity_not_a_serial_default(): void
	{
		if ($this->driver !== MgrDriver::Postgres) {
			$this->markTestSkipped('Identity columns are a PostgreSQL feature.');
		}

		$this->create_keyed_table($this->id_field(MgrFieldType::BigInt));

		$row = $this->db->query(
			'SELECT a.attidentity AS identity, pg_get_expr(d.adbin, d.adrelid) AS column_default '
				. 'FROM pg_attribute a LEFT JOIN pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum '
				. "WHERE a.attrelid = ?::regclass AND a.attname = 'id'",
			[self::TABLE]
		)->row_array();

		$this->assertSame('d', $row['identity']);
		$this->assertNull($row['column_default']);
	}

	/** @return array<string, array<string, mixed>> */
	private function id_field(MgrFieldType $type): array
	{
		return $this->builder->build_field(name: 'id', type: $type, unsigned: true, nullable: false, auto_increment: true);
	}

	/** A keyed table whose first column is `$id_field`, plus a `label`. */
	private function create_keyed_table(array $id_field): void
	{
		$this->dbforge->add_field([...$id_field, 'label' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true]]);
		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table(self::TABLE);
	}

	/** A table whose nullable `id` holds the given `(id, label)` rows and is not yet auto-increment. */
	private function create_plain_table(?string $rows): void
	{
		$this->dbforge->add_field([
			'id'    => ['type' => 'INT', 'null' => true],
			'label' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
		]);
		$this->dbforge->create_table(self::TABLE);

		if ($rows !== null) {
			$this->db->query('INSERT INTO ' . self::TABLE . " (id, label) VALUES {$rows}");
		}
	}

	/** MySQL and MariaDB need the column keyed first; the other engines ignore the key. */
	private function add_auto_increment(): void
	{
		$this->builder->key_column(self::TABLE, 'id');
		$this->builder->make_auto_increment(self::TABLE, 'id');
	}

	/** @return array<string, int> label => id */
	private function ids_by_label(): array
	{
		$ids = [];
		foreach ($this->db->query('SELECT id, label FROM ' . self::TABLE . ' ORDER BY label')->result_array() as $row) {
			$ids[$row['label']] = (int) $row['id'];
		}

		return $ids;
	}

	private function drop_table(): void
	{
		$this->db->query('DROP TABLE IF EXISTS ' . self::TABLE);
	}
}
