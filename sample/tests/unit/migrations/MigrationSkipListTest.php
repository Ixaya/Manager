<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * The runner's skip list: migration numbers recorded as applied without running their up() or
 * down(), in both directions. Drives the runner directly under a version key of its own; the
 * coordinator's property, validation and CLI output are not covered here.
 */
class MigrationSkipListTest extends CITestCase
{
	/** Migration name => number. The file named 'f' always throws from up(). */
	private const NUMBERS = [
		'a' => '20260101000001',
		'b' => '20260101000002',
		'c' => '20260101000003',
		'd' => '20260101000004',
		'f' => '20260101000005',
	];

	private string $token;
	private string $key;
	private string $directory;
	private string $library_name;
	private string $tracking_table;
	private object $runner;
	private object $runner_db;
	private object $original_db;
	private object $original_forge;

	protected function setUp(): void
	{
		// Main is this test's own connection; dbforge() skips loading it once any other handle exists.
		$this->load->database();
		$this->load->dbforge();

		$this->token     = bin2hex(random_bytes(4));
		$this->key       = 'phpunit_skip_' . $this->token;
		$this->directory = sys_get_temp_dir() . '/phpunit_skip_' . $this->token;
		mkdir($this->directory);

		// The library swaps the instance's connection and forge for its own; tearDown() puts both back.
		$instance             = get_instance();
		$this->original_db    = $instance->db;
		$this->original_forge = $instance->dbforge;
		$this->library_name   = 'phpunit_skip_runner_' . $this->token;

		$this->load->library('migration', ['migration_path' => $this->directory, 'db_group' => 'default'], $this->library_name);
		$this->runner         = $instance->{$this->library_name};
		$this->runner_db      = $instance->db;
		$this->tracking_table = $this->config->read('migration')['migration_table'] . '_path';

		$this->runner->set_path($this->directory);
		$this->runner->set_migration_key($this->key);
	}

	protected function tearDown(): void
	{
		$this->runner_db->where('module', $this->key)->delete($this->tracking_table);

		$instance = get_instance();
		unset($instance->{$this->library_name});
		$instance->db      = $this->original_db;
		$instance->dbforge = $this->original_forge;

		array_map('unlink', glob($this->directory . '/*'));
		rmdir($this->directory);
	}

	public function test_a_skipped_middle_file_is_recorded_without_running(): void
	{
		$this->create_files('a', 'b', 'c');
		$this->skip('b');

		$this->assertNotFalse($this->runner->latest());

		$this->assertSame('up a,up c', $this->log());
		$this->assertSame(self::NUMBERS['c'], $this->stored());
	}

	public function test_a_skipped_last_file_leaves_a_later_file_to_run_alone(): void
	{
		$this->create_files('a', 'b', 'c');
		$this->skip('c');

		$this->assertNotFalse($this->runner->latest());
		$this->assertSame('up a,up b', $this->log());
		$this->assertSame(self::NUMBERS['c'], $this->stored());

		$this->create_files('d');
		$this->clear_log();

		$this->assertNotFalse($this->runner->latest());
		$this->assertSame('up d', $this->log());
		$this->assertSame(self::NUMBERS['d'], $this->stored());
	}

	public function test_a_target_whose_every_file_is_skipped_runs_nothing_and_succeeds(): void
	{
		$this->create_files('a', 'b');
		$this->skip('a', 'b');

		$this->assertNotFalse($this->runner->latest());

		$this->assertSame('', $this->log());
		$this->assertSame(self::NUMBERS['b'], $this->stored());
	}

	public function test_a_downgrade_across_a_skipped_file_never_runs_its_down(): void
	{
		$this->create_files('a', 'b', 'c');
		$this->skip('b');
		$this->runner->latest();
		$this->clear_log();

		$this->assertNotFalse($this->runner->version(0));

		$this->assertSame('down c,down a', $this->log());
		$this->assertSame('0', $this->stored());
	}

	public function test_consecutive_skips_hold_going_up_and_down_whatever_the_order_given(): void
	{
		$this->create_files('a', 'b', 'c', 'd');
		$this->skip('c', 'b');

		$this->assertNotFalse($this->runner->latest());
		$this->assertSame('up a,up d', $this->log());
		$this->assertSame(self::NUMBERS['d'], $this->stored());

		$this->clear_log();

		$this->assertNotFalse($this->runner->version(0));
		$this->assertSame('down d,down a', $this->log());
		$this->assertSame('0', $this->stored());
	}

	public function test_a_skipped_number_is_a_valid_target_in_both_directions(): void
	{
		$this->create_files('a', 'b', 'c');
		$this->skip('b');

		$this->assertNotFalse($this->runner->version(self::NUMBERS['b']));
		$this->assertSame('up a', $this->log());
		$this->assertSame(self::NUMBERS['b'], $this->stored());

		$this->clear_log();
		$this->assertNotFalse($this->runner->latest());
		$this->assertSame('up c', $this->log());

		$this->clear_log();
		$this->assertNotFalse($this->runner->version(self::NUMBERS['b']));
		$this->assertSame('down c', $this->log());
		$this->assertSame(self::NUMBERS['b'], $this->stored());
	}

	public function test_removing_a_skip_after_the_fact_runs_nothing(): void
	{
		$this->create_files('a', 'b', 'c');
		$this->skip('b');
		$this->runner->latest();
		$this->clear_log();

		$this->runner->set_skipped([]);

		$this->assertNotFalse($this->runner->latest());
		$this->assertSame('', $this->log());
		$this->assertSame(self::NUMBERS['c'], $this->stored());
	}

	public function test_a_skip_added_for_an_applied_file_only_affects_a_later_downgrade(): void
	{
		$this->create_files('a', 'b', 'c');
		$this->runner->latest();
		$this->assertSame('up a,up b,up c', $this->log());
		$this->clear_log();

		$this->skip('a');

		$this->assertNotFalse($this->runner->latest());
		$this->assertSame('', $this->log());

		$this->assertNotFalse($this->runner->version(0));
		$this->assertSame('down c,down b', $this->log());
		$this->assertSame('0', $this->stored());
	}

	public function test_a_failing_migration_after_a_skip_keeps_the_last_good_version(): void
	{
		$this->create_files('a', 'b', 'c', 'f');
		$this->skip('b');

		$failure = $this->thrown(fn () => $this->runner->latest());

		$this->assertInstanceOf(RuntimeException::class, $failure);
		$this->assertSame('up a,up c,up f', $this->log());
		$this->assertSame(self::NUMBERS['c'], $this->stored());
		$this->assertFalse($this->runner_db->trans_active());
	}

	public function test_an_unknown_target_fails_before_anything_runs_even_with_a_skip_in_range(): void
	{
		$this->create_files('a', 'b', 'c');
		$this->skip('b');

		$this->assertFalse($this->runner->version('20260101009999'));

		$this->assertNotSame('', $this->runner->error_string());
		$this->assertSame('', $this->log());
		$this->assertSame('0', $this->stored());
	}

	public function test_a_stored_version_that_is_not_a_file_still_steps_over_a_later_skip(): void
	{
		$this->create_files('a', 'c');
		$this->skip('c');
		$this->runner->version_set(self::NUMBERS['b']);

		$writes = $this->tracking_writes(fn () => $this->assertNotFalse($this->runner->latest()));

		$this->assertSame('', $this->log());
		$this->assertSame(self::NUMBERS['c'], $this->stored());
		// One write: never a step back to the file below the skip, which a crash would leave behind.
		$this->assertCount(1, $writes);
	}

	public function test_an_empty_skip_list_reads_the_stored_version_once_like_the_parent(): void
	{
		$this->create_files('a');
		$this->runner->latest();

		$this->runner_db->save_queries = true;
		$this->runner_db->queries      = [];
		$this->runner->version(self::NUMBERS['a']);

		$reads = array_filter(
			$this->runner_db->queries,
			fn (string $sql) => stripos($sql, 'SELECT') === 0 && str_contains($sql, $this->tracking_table)
		);

		$this->assertCount(1, $reads);
	}

	public function test_set_skipped_replaces_the_previous_list(): void
	{
		$this->create_files('a', 'b', 'c');
		$this->skip('b');
		$this->runner->set_skipped([]);

		$this->assertNotFalse($this->runner->latest());

		$this->assertSame('up a,up b,up c', $this->log());
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/** Writes one migration file per name; each up() and down() appends a line to the log. */
	private function create_files(string ...$names): void
	{
		$log = var_export($this->directory . '/log', true);

		foreach ($names as $name) {
			$class = "Migration_Phpunit_skip_{$this->token}_{$name}";
			$fail  = $name === 'f' ? "throw new RuntimeException('phpunit skip list: f failed');" : '';

			file_put_contents($this->directory . '/' . self::NUMBERS[$name] . "_Phpunit_skip_{$this->token}_{$name}.php", <<<PHP
<?php

class {$class}
{
	public function up()
	{
		file_put_contents({$log}, "up {$name}\\n", FILE_APPEND);
		{$fail}
	}

	public function down()
	{
		file_put_contents({$log}, "down {$name}\\n", FILE_APPEND);
	}
}
PHP);
		}
	}

	private function skip(string ...$names): void
	{
		$this->runner->set_skipped(array_map(fn (string $name) => (int) self::NUMBERS[$name], $names));
	}

	/** The up/down lines the migrations wrote, in order. */
	private function log(): string
	{
		$file = $this->directory . '/log';

		return is_file($file) ? implode(',', file($file, FILE_IGNORE_NEW_LINES)) : '';
	}

	private function clear_log(): void
	{
		if (is_file($this->directory . '/log')) {
			unlink($this->directory . '/log');
		}
	}

	/** The version stored under this test's key, '0' before the first write. */
	private function stored(): string
	{
		$row = $this->runner_db->where('module', $this->key)->get($this->tracking_table)->row();

		return $row ? (string) $row->version : '0';
	}

	/** The statements that wrote this test's tracking row while $callback ran. */
	private function tracking_writes(callable $callback): array
	{
		$this->runner_db->save_queries = true;
		$this->runner_db->queries      = [];
		$callback();

		return array_values(array_filter(
			$this->runner_db->queries,
			fn (string $sql) => preg_match('/^\s*(UPDATE|INSERT)/i', $sql) === 1 && str_contains($sql, $this->tracking_table)
		));
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
