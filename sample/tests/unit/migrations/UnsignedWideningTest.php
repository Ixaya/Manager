<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/../../support/MigrationBuilderHarness.php';

/**
 * `unsigned` on PostgreSQL widens an integer to the type holding its unsigned range, through the
 * builder and raw dbforge alike, and leaves Float, Double and Decimal alone; MySQL/MariaDB keep
 * their native UNSIGNED. Engine-specific cases skip on the engines they do not apply to.
 */
class UnsignedWideningTest extends CITestCase
{
	private const TABLE = 'phpunit_unsigned';

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

	public function test_the_builder_widens_unsigned_integers_and_leaves_the_other_types_alone(): void
	{
		$this->skip_unless_postgres();

		$this->create_table([
			...$this->builder->build_field(name: 'tiny_u', type: MgrFieldType::TinyInt, unsigned: true, nullable: true),
			...$this->builder->build_field(name: 'small_u', type: MgrFieldType::SmallInt, unsigned: true, nullable: true),
			...$this->builder->build_field(name: 'int_u', type: MgrFieldType::Int, unsigned: true, nullable: true),
			...$this->builder->build_field(name: 'big_u', type: MgrFieldType::BigInt, unsigned: true, nullable: true),
			...$this->builder->build_field(name: 'float_u', type: MgrFieldType::Float, unsigned: true, nullable: true),
			...$this->builder->build_field(name: 'double_u', type: MgrFieldType::Double, unsigned: true, nullable: true),
			...$this->builder->build_field(name: 'dec_u', type: MgrFieldType::Decimal, unsigned: true, nullable: true, precision: 10, scale: 2),
			...$this->builder->build_field(name: 'small_s', type: MgrFieldType::SmallInt, nullable: true),
			...$this->builder->build_field(name: 'int_s', type: MgrFieldType::Int, nullable: true),
			...$this->builder->build_field(name: 'big_s', type: MgrFieldType::BigInt, nullable: true),
		]);

		$this->assertSame([
			'tiny_u'   => 'smallint',
			'small_u'  => 'integer',
			'int_u'    => 'bigint',
			'big_u'    => 'numeric(20,0)',
			'float_u'  => 'real',
			'double_u' => 'double precision',
			'dec_u'    => 'numeric(10,2)',
			'small_s'  => 'smallint',
			'int_s'    => 'integer',
			'big_s'    => 'bigint',
		], $this->column_types());
	}

	public function test_raw_dbforge_widens_the_same_way_on_create_and_add_column(): void
	{
		$this->skip_unless_postgres();

		$this->create_table([
			'tiny_u'  => ['type' => 'TINYINT', 'unsigned' => true, 'null' => true],
			'small_u' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
			'int_u'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
			'big_u'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
			'real_u'  => ['type' => 'REAL', 'unsigned' => true, 'null' => true],
			'int_s'   => ['type' => 'INT', 'null' => true],
		]);
		$this->dbforge->add_column(self::TABLE, ['int_added_u' => ['type' => 'INT', 'unsigned' => true, 'null' => true]]);

		$this->assertSame([
			'tiny_u'      => 'smallint',
			'small_u'     => 'integer',
			'int_u'       => 'bigint',
			'big_u'       => 'numeric(20,0)',
			'real_u'      => 'real',
			'int_s'       => 'integer',
			'int_added_u' => 'bigint',
		], $this->column_types());
	}

	public function test_an_unsigned_tinyint_stays_smallint_through_modify_column_while_a_smallint_widens(): void
	{
		$this->skip_unless_postgres();

		$this->create_table([
			...$this->builder->build_field(name: 'tiny_u', type: MgrFieldType::TinyInt, unsigned: true, nullable: true),
			...$this->builder->build_field(name: 'small_u', type: MgrFieldType::SmallInt, unsigned: true, nullable: true),
		]);

		$this->dbforge->modify_column(self::TABLE, [
			...$this->builder->build_field(name: 'tiny_u', type: MgrFieldType::TinyInt, unsigned: true, nullable: true, new_name: 'tiny_r'),
			...$this->builder->build_field(name: 'small_u', type: MgrFieldType::SmallInt, unsigned: true, nullable: true, new_name: 'small_r'),
		]);

		$this->assertSame(['tiny_r' => 'smallint', 'small_r' => 'integer'], $this->column_types());
	}

	public function test_a_widened_column_holds_the_full_unsigned_range(): void
	{
		$this->skip_unless_postgres();

		$this->create_table([
			...$this->builder->build_field(name: 'small_u', type: MgrFieldType::SmallInt, unsigned: true, nullable: true),
			...$this->builder->build_field(name: 'int_u', type: MgrFieldType::Int, unsigned: true, nullable: true),
			...$this->builder->build_field(name: 'big_u', type: MgrFieldType::BigInt, unsigned: true, nullable: true),
		]);
		$this->db->query('INSERT INTO ' . self::TABLE . ' (small_u, int_u, big_u) VALUES (65535, 4294967295, 18446744073709551615)');

		$row = $this->db->get(self::TABLE)->row_array();

		$this->assertSame(
			['small_u' => '65535', 'int_u' => '4294967295', 'big_u' => '18446744073709551615'],
			array_map('strval', $row)
		);
	}

	public function test_mysql_and_mariadb_keep_a_native_unsigned(): void
	{
		if (!$this->driver->isMysqlFamily()) {
			$this->markTestSkipped('Native UNSIGNED is MySQL/MariaDB only.');
		}

		$this->create_table([
			...$this->builder->build_field(name: 'int_u', type: MgrFieldType::Int, unsigned: true, nullable: true),
			...$this->builder->build_field(name: 'big_u', type: MgrFieldType::BigInt, unsigned: true, nullable: true),
			...$this->builder->build_field(name: 'int_s', type: MgrFieldType::Int, nullable: true),
		]);

		$types = $this->column_types();

		$this->assertStringContainsString('unsigned', $types['int_u']);
		$this->assertStringContainsString('unsigned', $types['big_u']);
		$this->assertStringNotContainsString('unsigned', $types['int_s']);
	}

	private function skip_unless_postgres(): void
	{
		if ($this->driver !== MgrDriver::Postgres) {
			$this->markTestSkipped('The widening is a PostgreSQL forge behavior.');
		}
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

	/** @return array<string, string> column name => the engine's own type text, in column order */
	private function column_types(): array
	{
		$rows = $this->driver === MgrDriver::Postgres
			? $this->db->query(
				'SELECT a.attname AS name, format_type(a.atttypid, a.atttypmod) AS type FROM pg_attribute a '
					. 'WHERE a.attrelid = ?::regclass AND a.attnum > 0 AND NOT a.attisdropped ORDER BY a.attnum',
				[self::TABLE]
			)->result_array()
			: $this->db->query(
				'SELECT column_name AS name, column_type AS type FROM information_schema.columns '
					. 'WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position',
				[self::TABLE]
			)->result_array();

		return array_column($rows, 'type', 'name');
	}
}
