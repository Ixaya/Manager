<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/../../support/MigrationBuilderHarness.php';

/**
 * The essentials of the case-insensitive column declaration and the schema paths it rides on:
 * the field shape, the loud failures, comparison semantics, COLLATE through alter paths, the
 * PostgreSQL collation guard, modify_column(unique), and the PostgreSQL DDL transaction.
 * Engine-specific cases skip on the engines they do not apply to.
 */
class CaseInsensitiveColumnsTest extends CITestCase
{
	private const TABLE = 'phpunit_ci_columns';
	private const TABLE_ROLLBACK = 'phpunit_ci_rollback';
	private const PG_COLLATION = 'manager_case_insensitive';

	private MigrationBuilderHarness $builder;
	private MgrDriver $driver;

	/** @var array<string, mixed> connection properties to put back in tearDown() */
	private array $saved = [];

	protected function setUp(): void
	{
		$this->load->dbforge();
		$this->builder = new MigrationBuilderHarness();
		$this->driver = MgrDriver::fromCI($this->db->dbdriver ?? '', subdriver: $this->db->subdriver ?? null);

		if ($this->driver === MgrDriver::SQLServer) {
			$this->markTestSkipped('case_insensitive is not supported on SQL Server.');
		}

		$this->drop_tables();
		$this->pin_setting('db_debug', $this->db->db_debug);

		// Fall back to the recommended value so the suite runs without a project's CI config.
		if ($this->driver === MgrDriver::Postgres) {
			$this->pin_setting('dblocaleci', ($this->db->dblocaleci ?? '') ?: 'und-u-ks-level2');
		} elseif ($this->driver->isMysqlFamily()) {
			$is_mariadb = str_contains((string) $this->db->version(), 'MariaDB');
			$this->pin_setting('dbcollatci', ($this->db->dbcollatci ?? '') ?: ($is_mariadb ? 'utf8mb4_uca1400_nopad_as_ci' : 'utf8mb4_0900_as_ci'));
		}
	}

	protected function tearDown(): void
	{
		foreach ($this->saved as $property => $value) {
			$this->db->{$property} = $value;
		}
		$this->saved = [];

		$this->drop_tables();
	}

	public function test_ci_field_folds_the_collation_and_an_exact_field_does_not(): void
	{
		$exact = $this->builder->build_field(name: 'a', type: MgrFieldType::VarChar, constraint: 50);
		$ci    = $this->builder->build_field(name: 'a', type: MgrFieldType::VarChar, constraint: 50, case_insensitive: true);

		$this->assertSame('VARCHAR', $exact['a']['type']);
		$this->assertSame('50', (string) $exact['a']['constraint']);
		$this->assertArrayNotHasKey('base_type', $exact['a']);

		$this->assertSame('VARCHAR(50) COLLATE ' . $this->ci_clause(), $ci['a']['type']);
		$this->assertArrayNotHasKey('constraint', $ci['a']);
		$this->assertSame('VARCHAR', $ci['a']['base_type']);
	}

	public function test_ci_field_fails_loudly_on_a_wrong_type_or_a_missing_setting(): void
	{
		$wrong_type = $this->thrown(fn () => $this->builder->build_field(name: 'u', type: MgrFieldType::Uuid, case_insensitive: true));
		$this->assertInstanceOf(InvalidArgumentException::class, $wrong_type);

		if ($this->driver === MgrDriver::SQLite) {
			return;
		}

		$key = $this->driver === MgrDriver::Postgres ? 'dblocaleci' : 'dbcollatci';
		$this->db->{$key} = '';
		$missing = $this->thrown(fn () => $this->builder->build_field(name: 'c', type: MgrFieldType::VarChar, constraint: 20, case_insensitive: true));

		$this->assertInstanceOf(RuntimeException::class, $missing);
		$this->assertStringContainsString("'{$key}'", $missing->getMessage());
		$this->assertStringContainsString((string) $this->db->database, $missing->getMessage());
	}

	public function test_a_ci_column_matches_case_insensitively_and_an_exact_column_does_not(): void
	{
		$this->create_table([
			...$this->builder->build_field(name: 'ci_col', type: MgrFieldType::VarChar, constraint: 50, nullable: true, case_insensitive: true),
			...$this->builder->build_field(name: 'plain_col', type: MgrFieldType::VarChar, constraint: 50, nullable: true),
		]);
		$this->db->insert(self::TABLE, ['ci_col' => 'abc', 'plain_col' => 'abc']);

		$this->assertSame(1, $this->db->where('ci_col', 'ABC')->count_all_results(self::TABLE));
		$this->assertSame(0, $this->db->where('plain_col', 'ABC')->count_all_results(self::TABLE));
	}

	public function test_the_collation_survives_modify_column_and_modify_column_cast(): void
	{
		$this->skip_unless_alterable();

		$this->create_table([
			...$this->builder->build_field(name: 'ci_col', type: MgrFieldType::VarChar, constraint: 50, nullable: true, case_insensitive: true),
			...$this->builder->build_field(name: 'plain_col', type: MgrFieldType::VarChar, constraint: 50, nullable: true),
			...$this->builder->build_field(name: 'ci_text', type: MgrFieldType::Text, nullable: true, case_insensitive: true),
		]);
		$this->db->insert(self::TABLE, ['plain_col' => 'abc', 'ci_text' => 'abcdefghij']);
		$expected = $this->ci_collation_name();

		$this->dbforge->modify_column(self::TABLE, $this->builder->build_field(name: 'ci_col', type: MgrFieldType::VarChar, constraint: 80, nullable: true, case_insensitive: true));
		$this->assertSame($expected, $this->column_collation('ci_col'));

		$this->builder->cast_column(self::TABLE, $this->builder->build_field(name: 'plain_col', type: MgrFieldType::VarChar, constraint: 50, nullable: true, case_insensitive: true));
		$this->assertSame($expected, $this->column_collation('plain_col'));
		$this->assertSame(1, $this->db->where('plain_col', 'ABC')->count_all_results(self::TABLE));

		// Narrowing through the cast must reject the value, not truncate it.
		$this->db->db_debug = false;
		$this->builder->cast_column(self::TABLE, $this->builder->build_field(name: 'ci_text', type: MgrFieldType::VarChar, constraint: 5, nullable: true, case_insensitive: true));
		$this->assertSame('abcdefghij', $this->db->select('ci_text')->get(self::TABLE)->row()->ci_text);

		// Restating without case_insensitive goes back to the exact default.
		$this->dbforge->modify_column(self::TABLE, $this->builder->build_field(name: 'ci_col', type: MgrFieldType::VarChar, constraint: 80, nullable: true));
		$this->assertNotSame($expected, $this->column_collation('ci_col'));
	}

	public function test_postgres_rejects_a_collation_that_exists_with_another_locale(): void
	{
		if ($this->driver !== MgrDriver::Postgres) {
			$this->markTestSkipped('The collation guard is PostgreSQL only.');
		}

		$this->builder->build_field(name: 'c', type: MgrFieldType::VarChar, constraint: 20, case_insensitive: true);
		$level = $this->db->query('SHOW icu_validation_level')->row()->icu_validation_level;

		$this->db->dblocaleci = $this->db->dblocaleci === 'und-u-ks-level1' ? 'und-u-ks-level2' : 'und-u-ks-level1';
		$mismatch = $this->thrown(fn () => $this->builder->build_field(name: 'c', type: MgrFieldType::VarChar, constraint: 20, case_insensitive: true));

		$this->assertInstanceOf(RuntimeException::class, $mismatch);
		$this->assertStringContainsString('different definition', $mismatch->getMessage());
		$this->assertSame($level, $this->db->query('SHOW icu_validation_level')->row()->icu_validation_level);
	}

	public function test_modify_column_unique_creates_a_key_that_drop_index_finds(): void
	{
		$this->skip_unless_alterable();

		$this->create_table([...$this->builder->build_field(name: 'email', type: MgrFieldType::VarChar, constraint: 100, nullable: true)]);
		$this->dbforge->modify_column(self::TABLE, $this->builder->build_field(name: 'email', type: MgrFieldType::VarChar, constraint: 100, nullable: true, unique: true));

		if ($this->driver === MgrDriver::Postgres) {
			$this->assertStringStartsWith('MGR_DB_', get_class($this->dbforge));
		}

		$this->db->db_debug = false;
		$this->assertNotFalse($this->db->insert(self::TABLE, ['email' => 'dup@example.test']));
		$this->assertFalse($this->db->insert(self::TABLE, ['email' => 'dup@example.test']));
		$this->assertTrue($this->builder->remove_index(self::TABLE, 'email'));
	}

	public function test_the_ddl_transaction_is_postgres_only_and_rolls_back_a_failed_statement(): void
	{
		if ($this->driver !== MgrDriver::Postgres) {
			$this->builder->begin_ddl();
			$this->assertFalse($this->db->trans_active());
			$this->builder->end_ddl();

			return;
		}

		$this->builder->begin_ddl();
		$this->assertTrue($this->db->trans_active());
		$this->create_table([...$this->builder->build_field(name: 'id', type: MgrFieldType::Int)]);
		$this->builder->end_ddl();
		$this->assertFalse($this->db->trans_active());
		$this->assertTrue($this->in_catalog(self::TABLE));

		$this->db->db_debug = false;
		$this->builder->begin_ddl();
		$this->dbforge->add_field(['id' => ['type' => 'INT']]);
		$this->dbforge->create_table(self::TABLE_ROLLBACK);
		$this->assertFalse($this->db->query('SELECT * FROM phpunit_ci_no_such_table'));

		$failure = $this->thrown(fn () => $this->builder->end_ddl());
		$this->assertInstanceOf(RuntimeException::class, $failure);
		$this->assertFalse($this->db->trans_active());
		$this->assertFalse($this->in_catalog(self::TABLE_ROLLBACK));
	}

	public function test_the_runner_rolls_back_a_migration_that_throws_mid_transaction(): void
	{
		if ($this->driver !== MgrDriver::Postgres) {
			$this->markTestSkipped('The DDL transaction is PostgreSQL only.');
		}

		$directory = sys_get_temp_dir() . '/phpunit_ci_runner_' . bin2hex(random_bytes(4));
		mkdir($directory);
		file_put_contents($directory . '/20260101000001_Phpunit_ci_runner_boom.php', <<<'PHP'
<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Phpunit_ci_runner_boom extends MGR_Migration_builder
{
	public function up()
	{
		$this->start_ddl_transaction();
		$this->dbforge->add_field(['id' => ['type' => 'INT']]);
		$this->dbforge->create_table('phpunit_ci_rollback');
		throw new RuntimeException('thrown between start and end');
	}

	public function down()
	{
	}
}
PHP);

		// The library swaps the instance's connection and forge for its own; put both back afterwards.
		$instance        = get_instance();
		$original_db     = $instance->db;
		$original_forge  = $instance->dbforge;
		$library_name    = 'phpunit_ci_runner';
		$runner_db       = null;

		try {
			$this->load->library('migration', ['migration_path' => $directory, 'db_group' => 'default'], $library_name);
			$runner = $instance->{$library_name};
			$runner->set_path($directory);
			$runner->set_migration_key('phpunit_ci_runner');
			$runner_db = $instance->db;

			$failure = $this->thrown(fn () => $runner->latest());

			$this->assertInstanceOf(RuntimeException::class, $failure);
			$this->assertFalse($runner_db->trans_active());
			$this->assertFalse($this->in_catalog(self::TABLE_ROLLBACK));
			$this->assertSame(1, (int) $runner_db->query('SELECT 1 AS ok')->row()->ok);
		} finally {
			$runner_db?->close();
			unset($instance->{$library_name});
			$instance->db      = $original_db;
			$instance->dbforge = $original_forge;
			array_map('unlink', glob($directory . '/*'));
			rmdir($directory);
		}
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/** Set a connection property for this test; tearDown() restores the original. */
	private function pin_setting(string $property, mixed $value): void
	{
		$this->saved[$property] = $this->db->{$property} ?? null;
		$this->db->{$property} = $value;
	}

	private function skip_unless_alterable(): void
	{
		if ($this->driver === MgrDriver::SQLite) {
			$this->markTestSkipped('SQLite cannot alter a column.');
		}
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

	/** The clause the builder is expected to fold into the type. */
	private function ci_clause(): string
	{
		return match ($this->driver) {
			MgrDriver::Postgres => '"' . self::PG_COLLATION . '"',
			MgrDriver::SQLite   => 'NOCASE',
			default             => (string) $this->db->dbcollatci,
		};
	}

	/** The collation name a catalog reports for a CI column. */
	private function ci_collation_name(): string
	{
		return $this->driver === MgrDriver::Postgres ? self::PG_COLLATION : (string) $this->db->dbcollatci;
	}

	private function create_table(array $fields, string $table = self::TABLE): void
	{
		$this->dbforge->add_field($fields);
		$this->dbforge->create_table($table);
	}

	private function drop_tables(): void
	{
		foreach ([self::TABLE, self::TABLE_ROLLBACK] as $table) {
			$this->db->query("DROP TABLE IF EXISTS {$table}");
		}
	}

	private function column_collation(string $column): ?string
	{
		$scope = $this->driver === MgrDriver::Postgres ? 'current_schema()' : 'DATABASE()';
		$row   = $this->db->query(
			"SELECT collation_name AS collation FROM information_schema.columns WHERE table_schema = {$scope} AND table_name = ? AND column_name = ?",
			[self::TABLE, $column]
		)->row();

		return $row->collation ?? null;
	}

	/** A straight catalog lookup: CI's table_exists() reads a cached list that a rollback does not clear. */
	private function in_catalog(string $table): bool
	{
		$scope = $this->driver === MgrDriver::Postgres ? 'current_schema()' : 'DATABASE()';

		return (int) $this->db->query(
			"SELECT count(*) AS n FROM information_schema.tables WHERE table_schema = {$scope} AND table_name = ?",
			[$table]
		)->row()->n > 0;
	}
}
