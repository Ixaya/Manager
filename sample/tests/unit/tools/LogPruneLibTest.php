<?php

/**
 * Black-box coverage of MGR_Log_prune_lib's public API against real temp
 * directories, never the app's own configured log paths. Tools::log_prune()'s
 * dispatch onto these two methods is thin glue and isn't re-tested here.
 */
class LogPruneLibTest extends CITestCase
{
	private string $root;
	private string $app_dir;
	private string $cli_dir;

	protected function setUp(): void
	{
		$this->root    = sys_get_temp_dir() . '/log_prune_test_' . bin2hex(random_bytes(8));
		$this->app_dir = $this->root . '/app/';
		$this->cli_dir = $this->root . '/cli/';
		mkdir($this->app_dir, 0755, true);
		mkdir($this->cli_dir, 0755, true);

		// CI shares one library instance across tests — reset every threshold so none leaks.
		$this->load->library('log_prune_lib');
		$this->log_prune_lib->app_compress_after_days = 0;
		$this->log_prune_lib->app_delete_after_days   = 0;
		$this->log_prune_lib->cli_max_size_bytes      = 0;
		$this->log_prune_lib->cli_keep                = 0;
	}

	protected function tearDown(): void
	{
		$this->remove_tree($this->root);
	}

	private function remove_tree(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			$path = $dir . '/' . $entry;
			is_dir($path) ? $this->remove_tree($path) : unlink($path);
		}
		rmdir($dir);
	}

	private function write_app_file(string $name, string $content = 'x'): string
	{
		$path = $this->app_dir . $name;
		file_put_contents($path, $content);

		return $path;
	}

	private function dated(int $days_ago): string
	{
		return (new DateTimeImmutable('today'))->modify("-{$days_ago} days")->format('Y-m-d');
	}

	private function dated_future(int $days_ahead): string
	{
		return (new DateTimeImmutable('today'))->modify("+{$days_ahead} days")->format('Y-m-d');
	}

	public function test_thresholds_default_from_lib_log_prune_config(): void
	{
		$config = $this->config->read('lib_log_prune', fail_gracefully: false);
		$lib    = new Log_prune_lib();

		$this->assertSame($config['app_compress_after_days'], $lib->app_compress_after_days);
		$this->assertSame($config['app_delete_after_days'], $lib->app_delete_after_days);
		$this->assertSame($config['cli_max_size_mb'] * 1024 * 1024, $lib->cli_max_size_bytes);
		$this->assertSame($config['cli_keep'], $lib->cli_keep);
	}

	public function test_app_compresses_files_older_than_compress_after_and_leaves_recent_ones(): void
	{
		$old_name    = 'log-' . $this->dated(10) . '.log';
		$recent_name = 'log-' . $this->dated(3) . '.log';
		$this->write_app_file($old_name, 'old content');
		$this->write_app_file($recent_name, 'recent content');

		$this->log_prune_lib->app_compress_after_days = 7;
		$this->log_prune_lib->app_delete_after_days   = 30;

		$result = $this->log_prune_lib->prune_app(log_dir: $this->app_dir, extension: 'log', dry_run: false);

		$this->assertSame([$old_name], $result['compressed']);
		$this->assertFileDoesNotExist($this->app_dir . $old_name);
		$this->assertSame('old content', gzdecode((string) file_get_contents($this->app_dir . $old_name . '.gz')));
		$this->assertFileExists($this->app_dir . $recent_name);
	}

	public function test_app_never_touches_todays_file(): void
	{
		$today_name = 'log-' . $this->dated(0) . '.log';
		$this->write_app_file($today_name);

		$this->log_prune_lib->app_compress_after_days = 1;
		$this->log_prune_lib->app_delete_after_days   = 30;

		$result = $this->log_prune_lib->prune_app(log_dir: $this->app_dir, extension: 'log', dry_run: false);

		$this->assertSame([], $result['compressed']);
		$this->assertFileExists($this->app_dir . $today_name);
	}

	/**
	 * Clock skew or a writer/pruner timezone mismatch can produce a future-dated filename.
	 */
	public function test_app_never_touches_future_dated_files(): void
	{
		$future_name = 'log-' . $this->dated_future(30) . '.log';
		$future_gz   = 'log-' . $this->dated_future(30) . '.log.gz';
		$this->write_app_file($future_name);
		$this->write_app_file($future_gz);

		$this->log_prune_lib->app_compress_after_days = 7;
		$this->log_prune_lib->app_delete_after_days   = 7;

		$result = $this->log_prune_lib->prune_app(log_dir: $this->app_dir, extension: 'log', dry_run: false);

		$this->assertSame([], $result['compressed']);
		$this->assertSame([], $result['deleted']);
		$this->assertFileExists($this->app_dir . $future_name);
		$this->assertFileExists($this->app_dir . $future_gz);
	}

	public function test_app_deletes_gz_archives_older_than_delete_after(): void
	{
		$old_gz    = 'log-' . $this->dated(40) . '.log.gz';
		$recent_gz = 'log-' . $this->dated(20) . '.log.gz';
		$this->write_app_file($old_gz);
		$this->write_app_file($recent_gz);

		$this->log_prune_lib->app_compress_after_days = 7;
		$this->log_prune_lib->app_delete_after_days   = 30;

		$result = $this->log_prune_lib->prune_app(log_dir: $this->app_dir, extension: 'log', dry_run: false);

		$this->assertSame([$old_gz], $result['deleted']);
		$this->assertFileDoesNotExist($this->app_dir . $old_gz);
		$this->assertFileExists($this->app_dir . $recent_gz);
	}

	public function test_app_compress_after_zero_disables_compression_but_delete_still_runs(): void
	{
		$old_name = 'log-' . $this->dated(90) . '.log';
		$old_gz   = 'log-' . $this->dated(91) . '.log.gz';
		$this->write_app_file($old_name);
		$this->write_app_file($old_gz);

		$this->log_prune_lib->app_compress_after_days = 0;
		$this->log_prune_lib->app_delete_after_days   = 30;

		$result = $this->log_prune_lib->prune_app(log_dir: $this->app_dir, extension: 'log', dry_run: false);

		$this->assertSame([], $result['compressed']);
		$this->assertFileExists($this->app_dir . $old_name);
		$this->assertSame([$old_gz], $result['deleted']);
	}

	public function test_app_delete_after_zero_disables_deletion_but_compress_still_runs(): void
	{
		$old_name = 'log-' . $this->dated(90) . '.log';
		$old_gz   = 'log-' . $this->dated(91) . '.log.gz';
		$this->write_app_file($old_name);
		$this->write_app_file($old_gz);

		$this->log_prune_lib->app_compress_after_days = 7;
		$this->log_prune_lib->app_delete_after_days   = 0;

		$result = $this->log_prune_lib->prune_app(log_dir: $this->app_dir, extension: 'log', dry_run: false);

		$this->assertSame([$old_name], $result['compressed']);
		$this->assertSame([], $result['deleted']);
		$this->assertFileExists($this->app_dir . $old_gz);
	}

	public function test_app_dry_run_reports_the_plan_but_changes_nothing(): void
	{
		$old_name = 'log-' . $this->dated(90) . '.log';
		$old_gz   = 'log-' . $this->dated(91) . '.log.gz';
		$this->write_app_file($old_name);
		$this->write_app_file($old_gz);

		$this->log_prune_lib->app_compress_after_days = 7;
		$this->log_prune_lib->app_delete_after_days   = 30;

		$result = $this->log_prune_lib->prune_app(log_dir: $this->app_dir, extension: 'log', dry_run: true);

		$this->assertSame([$old_name], $result['compressed']);
		$this->assertSame([$old_gz], $result['deleted']);
		$this->assertFileExists($this->app_dir . $old_name);
		$this->assertFileDoesNotExist($this->app_dir . $old_name . '.gz');
		$this->assertFileExists($this->app_dir . $old_gz);
	}

	/**
	 * A failed archive write (a full disk, in practice) must never cost the original.
	 */
	public function test_app_keeps_the_original_when_its_archive_cannot_be_written(): void
	{
		$old_name = 'log-' . $this->dated(10) . '.log';
		$this->write_app_file($old_name, 'old content');
		mkdir($this->app_dir . $old_name . '.gz');

		$this->log_prune_lib->app_compress_after_days = 7;
		$this->log_prune_lib->app_delete_after_days   = 30;

		$result = $this->log_prune_lib->prune_app(log_dir: $this->app_dir, extension: 'log', dry_run: false);

		$this->assertSame([], $result['compressed']);
		$this->assertSame('old content', file_get_contents($this->app_dir . $old_name));
	}

	public function test_app_ignores_files_that_do_not_match_the_dated_pattern(): void
	{
		$this->write_app_file('notes.txt');
		$this->write_app_file('log-' . $this->dated(90) . '.other');

		$this->log_prune_lib->app_compress_after_days = 1;
		$this->log_prune_lib->app_delete_after_days   = 1;

		$result = $this->log_prune_lib->prune_app(log_dir: $this->app_dir, extension: 'log', dry_run: false);

		$this->assertSame([], $result['compressed']);
		$this->assertSame([], $result['deleted']);
		$this->assertFileExists($this->app_dir . 'notes.txt');
	}

	public function test_cli_copytruncates_oversized_job_log_and_a_held_open_appender_follows_it(): void
	{
		$path = $this->cli_dir . 'myjob.log';
		file_put_contents($path, str_repeat('a', 200));

		// Simulates the detached `nohup ... >> file` writer that stays open
		// across the truncate.
		$writer = fopen($path, 'ab');

		$this->log_prune_lib->cli_max_size_bytes = 100;
		$this->log_prune_lib->cli_keep           = 3;

		$result = $this->log_prune_lib->prune_cli(log_dir: $this->cli_dir, dry_run: false);

		$this->assertSame(['myjob.log'], $result['truncated']);
		$this->assertSame(0, filesize($path));

		fwrite($writer, 'new entry');
		fclose($writer);

		$this->assertSame('new entry', file_get_contents($path));

		$archives = glob($this->cli_dir . 'myjob-*.log.gz');
		$this->assertCount(1, $archives);
		$this->assertSame(str_repeat('a', 200), gzdecode((string) file_get_contents($archives[0])));
	}

	public function test_cli_max_size_zero_disables_truncation(): void
	{
		$path = $this->cli_dir . 'myjob.log';
		file_put_contents($path, str_repeat('a', 200));

		$this->log_prune_lib->cli_max_size_bytes = 0;
		$this->log_prune_lib->cli_keep           = 3;

		$result = $this->log_prune_lib->prune_cli(log_dir: $this->cli_dir, dry_run: false);

		$this->assertSame([], $result['truncated']);
		$this->assertSame(200, filesize($path));
	}

	public function test_cli_keeps_newest_archives_per_job_and_deletes_older_ones(): void
	{
		$archives = [
			'myjob-20260101-000000.log.gz',
			'myjob-20260102-000000.log.gz',
			'myjob-20260103-000000.log.gz',
			'otherjob-20260101-000000.log.gz',
		];
		foreach ($archives as $name) {
			file_put_contents($this->cli_dir . $name, 'x');
		}

		$this->log_prune_lib->cli_max_size_bytes = 999999;
		$this->log_prune_lib->cli_keep           = 2;

		$result = $this->log_prune_lib->prune_cli(log_dir: $this->cli_dir, dry_run: false);

		sort($result['deleted']);
		$this->assertSame(['myjob-20260101-000000.log.gz'], $result['deleted']);
		$this->assertFileExists($this->cli_dir . 'myjob-20260102-000000.log.gz');
		$this->assertFileExists($this->cli_dir . 'myjob-20260103-000000.log.gz');
		$this->assertFileExists($this->cli_dir . 'otherjob-20260101-000000.log.gz');
	}

	public function test_cli_keep_zero_disables_archive_deletion(): void
	{
		file_put_contents($this->cli_dir . 'myjob-20260101-000000.log.gz', 'x');
		file_put_contents($this->cli_dir . 'myjob-20260102-000000.log.gz', 'x');

		$this->log_prune_lib->cli_max_size_bytes = 999999;
		$this->log_prune_lib->cli_keep           = 0;

		$result = $this->log_prune_lib->prune_cli(log_dir: $this->cli_dir, dry_run: false);

		$this->assertSame([], $result['deleted']);
		$this->assertFileExists($this->cli_dir . 'myjob-20260101-000000.log.gz');
	}

	public function test_cli_dry_run_reports_the_plan_but_changes_nothing(): void
	{
		$path = $this->cli_dir . 'myjob.log';
		file_put_contents($path, str_repeat('a', 200));
		file_put_contents($this->cli_dir . 'myjob-20260101-000000.log.gz', 'x');
		file_put_contents($this->cli_dir . 'myjob-20260102-000000.log.gz', 'x');

		$this->log_prune_lib->cli_max_size_bytes = 100;
		$this->log_prune_lib->cli_keep           = 1;

		$result = $this->log_prune_lib->prune_cli(log_dir: $this->cli_dir, dry_run: true);

		$this->assertSame(['myjob.log'], $result['truncated']);
		$this->assertSame(200, filesize($path));
		$this->assertSame(['myjob-20260101-000000.log.gz'], $result['deleted']);
		$this->assertFileExists($this->cli_dir . 'myjob-20260101-000000.log.gz');

		$existing = array_map('basename', glob($this->cli_dir . 'myjob-*.log.gz') ?: []);
		sort($existing);
		$this->assertSame(['myjob-20260101-000000.log.gz', 'myjob-20260102-000000.log.gz'], $existing);
	}

	public function test_cli_ignores_logrotate_produced_files(): void
	{
		file_put_contents($this->cli_dir . 'myjob.log.1', str_repeat('a', 200));
		file_put_contents($this->cli_dir . 'myjob.log.2.gz', 'x');

		$this->log_prune_lib->cli_max_size_bytes = 1;
		$this->log_prune_lib->cli_keep           = 5;

		$result = $this->log_prune_lib->prune_cli(log_dir: $this->cli_dir, dry_run: false);

		$this->assertSame([], $result['truncated']);
		$this->assertSame([], $result['deleted']);
		$this->assertFileExists($this->cli_dir . 'myjob.log.1');
		$this->assertFileExists($this->cli_dir . 'myjob.log.2.gz');
	}

	/**
	 * Overlapping runs landing in the same wall-clock second.
	 */
	public function test_cli_never_overwrites_an_archive_from_a_same_second_collision(): void
	{
		$path = $this->cli_dir . 'myjob.log';
		file_put_contents($path, str_repeat('a', 200));

		$now     = new DateTimeImmutable('2026-05-01 12:00:00');
		$archive = $this->cli_dir . 'myjob-20260501-120000.log.gz';
		file_put_contents($archive, 'a prior run already wrote this archive');

		$this->log_prune_lib->cli_max_size_bytes = 100;
		$this->log_prune_lib->cli_keep           = 3;

		$result = $this->log_prune_lib->prune_cli(log_dir: $this->cli_dir, dry_run: false, now: $now);

		$this->assertSame([], $result['truncated']);
		$this->assertSame(200, filesize($path));
		$this->assertSame('a prior run already wrote this archive', file_get_contents($archive));
	}
}
