<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Black-box coverage of MGR_Log_prune_lib::prune_api() against a throwaway
 * table shaped like api_log (id + unix `time`), never the real api_log.
 * Tools::log_prune()'s dispatch onto it is thin glue and isn't re-tested here.
 */
class LogPruneApiTest extends CITestCase
{
	public const TABLE = 'api_log_prune_test';
	private const NOW   = 1_800_000_000;
	private const DAY   = 86400;

	private static ?object $migration = null;

	public static function setUpBeforeClass(): void
	{
		get_instance()->load->dbforge();
		require_once MGRPATH . 'libraries/MGR_Migration_builder.php';

		self::$migration = new class () extends MGR_Migration_builder {
			public function up(): void
			{
				$this->dbforge->drop_table(LogPruneApiTest::TABLE, true);
				$this->dbforge->add_field([
					...$this->field_id('id'),
					...$this->field(name: 'time', type: MgrFieldType::BigInt),
				]);
				$this->dbforge->add_key('id', true);
				$this->dbforge->create_table(LogPruneApiTest::TABLE);
			}

			public function down(): void
			{
				$this->dbforge->drop_table(LogPruneApiTest::TABLE, true);
			}
		};

		self::$migration->up();
	}

	public static function tearDownAfterClass(): void
	{
		self::$migration?->down();
	}

	protected function setUp(): void
	{
		$this->db->empty_table(self::TABLE);

		// CI shares one library instance across tests — reset the threshold so none leaks.
		$this->load->library('log_prune_lib');
		$this->log_prune_lib->api_delete_after_days = 60;
	}

	/**
	 * Inserts one row per age in days (ids ascend in the order given) and returns their ids.
	 *
	 * @param int[] $ages_in_days
	 * @return int[]
	 */
	private function seed(array $ages_in_days): array
	{
		$ids = [];
		foreach ($ages_in_days as $age) {
			$this->db->insert(self::TABLE, ['time' => self::NOW - $age * self::DAY]);
			$ids[] = (int) $this->db->insert_id();
		}

		return $ids;
	}

	/**
	 * @return int[]
	 */
	private function remaining_ids(): array
	{
		$rows = $this->db->select('id')->order_by('id')->get(self::TABLE)->result();

		return array_map(fn ($row) => (int) $row->id, $rows);
	}

	public function test_deletes_rows_older_than_the_threshold_and_keeps_the_rest(): void
	{
		$ids = $this->seed([90, 61, 59, 1]);

		$result = $this->log_prune_lib->prune_api(db: $this->db, table: self::TABLE, now: self::NOW);

		$this->assertSame(2, $result['deleted']);
		$this->assertSame([$ids[2], $ids[3]], $this->remaining_ids());
	}

	public function test_row_exactly_at_the_cutoff_is_kept(): void
	{
		$ids = $this->seed([60]);

		$result = $this->log_prune_lib->prune_api(db: $this->db, table: self::TABLE, now: self::NOW);

		$this->assertSame(0, $result['deleted']);
		$this->assertSame($ids, $this->remaining_ids());
	}

	public function test_stage_is_off_at_zero(): void
	{
		$ids = $this->seed([400, 90]);
		$this->log_prune_lib->api_delete_after_days = 0;

		$result = $this->log_prune_lib->prune_api(db: $this->db, table: self::TABLE, now: self::NOW);

		$this->assertSame(0, $result['deleted']);
		$this->assertSame($ids, $this->remaining_ids());
	}

	public function test_deletes_across_several_chunks(): void
	{
		$ids = $this->seed([100, 99, 98, 97, 96, 95, 94, 3, 2]);

		$result = $this->log_prune_lib->prune_api(db: $this->db, table: self::TABLE, now: self::NOW, chunk_rows: 2);

		$this->assertSame(7, $result['deleted']);
		$this->assertSame([$ids[7], $ids[8]], $this->remaining_ids());
	}

	public function test_a_newer_row_between_expired_ids_survives(): void
	{
		$ids = $this->seed([90, 5, 80]);

		$result = $this->log_prune_lib->prune_api(db: $this->db, table: self::TABLE, now: self::NOW, chunk_rows: 10);

		$this->assertSame(2, $result['deleted']);
		$this->assertSame([$ids[1]], $this->remaining_ids());
	}

	public function test_ids_with_gaps_are_walked(): void
	{
		$ids = $this->seed([90, 90, 90, 90, 90, 1]);
		$this->db->where_in('id', [$ids[1], $ids[2], $ids[3]])->delete(self::TABLE);

		$result = $this->log_prune_lib->prune_api(db: $this->db, table: self::TABLE, now: self::NOW, chunk_rows: 2);

		$this->assertSame(2, $result['deleted']);
		$this->assertSame([$ids[5]], $this->remaining_ids());
	}

	public function test_dry_run_counts_and_changes_nothing(): void
	{
		$ids = $this->seed([90, 61, 59]);

		$result = $this->log_prune_lib->prune_api(db: $this->db, table: self::TABLE, dry_run: true, now: self::NOW);

		$this->assertSame(2, $result['deleted']);
		$this->assertSame($ids, $this->remaining_ids());
	}

	public function test_empty_table_and_nothing_expired_delete_nothing(): void
	{
		$this->assertSame(['deleted' => 0], $this->log_prune_lib->prune_api(db: $this->db, table: self::TABLE, now: self::NOW));

		$ids = $this->seed([10, 1]);

		$this->assertSame(['deleted' => 0], $this->log_prune_lib->prune_api(db: $this->db, table: self::TABLE, now: self::NOW));
		$this->assertSame($ids, $this->remaining_ids());
	}

	public function test_missing_table_is_a_no_op(): void
	{
		$result = $this->log_prune_lib->prune_api(db: $this->db, table: 'api_log_prune_missing_test', now: self::NOW);

		$this->assertSame(['deleted' => 0], $result);
	}

	public function test_chunk_rows_below_one_is_rejected(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->log_prune_lib->prune_api(db: $this->db, table: self::TABLE, now: self::NOW, chunk_rows: 0);
	}
}
