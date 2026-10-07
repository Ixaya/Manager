<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Age/size-based retention for the app's own log directories and the REST
 * api_log table. Thresholds come from lib_log_prune.php and may be overridden
 * per instance; directories and the database connection are always passed in —
 * Tools::log_prune() resolves the real ones.
 */
class MGR_Log_prune_lib
{
	// No initializers: lib_log_prune.php is the one place defaults live. 0 = stage off.
	public int $app_compress_after_days;
	public int $app_delete_after_days;
	public int $cli_max_size_bytes;
	public int $cli_keep;
	public int $api_delete_after_days;

	public function __construct()
	{
		$config = get_instance()->config->read('lib_log_prune', fail_gracefully: false) ?? [];

		$this->app_compress_after_days = (int) ($config['app_compress_after_days'] ?? 0);
		$this->app_delete_after_days   = (int) ($config['app_delete_after_days'] ?? 0);
		$this->cli_max_size_bytes      = (int) ($config['cli_max_size_mb'] ?? 0) * 1024 * 1024;
		$this->cli_keep                = (int) ($config['cli_keep'] ?? 0);
		$this->api_delete_after_days   = (int) ($config['api_delete_after_days'] ?? 0);
	}

	/**
	 * Gzips `log-YYYY-MM-DD.<extension>` files older than $app_compress_after_days,
	 * then deletes `.gz` archives older than $app_delete_after_days. Age comes
	 * from the filename date, not mtime.
	 *
	 * @return array{compressed: string[], deleted: string[], compressed_bytes: int, deleted_bytes: int}
	 */
	public function prune_app(string $log_dir, string $extension, bool $dry_run = false): array
	{
		$result = ['compressed' => [], 'deleted' => [], 'compressed_bytes' => 0, 'deleted_bytes' => 0];

		if (!is_dir($log_dir)) {
			return $result;
		}

		$log_dir     = rtrim($log_dir, '/\\') . DIRECTORY_SEPARATOR;
		$today       = new DateTimeImmutable('today');
		$ext_pattern = preg_quote($extension, '/');

		foreach (scandir($log_dir) ?: [] as $entry) {
			$path = $log_dir . $entry;
			if ($entry === '.' || $entry === '..' || is_link($path) || !is_file($path)) {
				continue;
			}

			if ($this->app_compress_after_days > 0 && preg_match("/^log-(\d{4}-\d{2}-\d{2})\.{$ext_pattern}$/D", $entry, $matches) === 1) {
				$age = $this->age_in_days($matches[1], $today);
				if ($age !== null && $age > $this->app_compress_after_days) {
					$bytes = filesize($path) ?: 0;
					if (!$dry_run) {
						if (!$this->gzip_stream($path, $path . '.gz', $error)) {
							log_message('error', "MGR_Log_prune_lib: could not archive '{$path}' ({$error}) — leaving it in place.");
							continue;
						}
						unlink($path);
					}
					$result['compressed'][]    = $entry;
					$result['compressed_bytes'] += $bytes;
				}
				continue;
			}

			if ($this->app_delete_after_days > 0 && preg_match("/^log-(\d{4}-\d{2}-\d{2})\.{$ext_pattern}\.gz$/D", $entry, $matches) === 1) {
				$age = $this->age_in_days($matches[1], $today);
				if ($age !== null && $age > $this->app_delete_after_days) {
					$bytes = filesize($path) ?: 0;
					if (!$dry_run) {
						unlink($path);
					}
					$result['deleted'][]    = $entry;
					$result['deleted_bytes'] += $bytes;
				}
			}
		}

		return $result;
	}

	/**
	 * Copytruncates `<job>.log` files over $cli_max_size_bytes to
	 * `<job>-YYYYMMDD-HHMMSS.log.gz` (lines written between copy and truncate
	 * are lost, as with logrotate's copytruncate), then keeps the newest
	 * $cli_keep archives per job.
	 *
	 * @param ?DateTimeImmutable $now Archive clock — a test seam; defaults to now.
	 * @return array{truncated: string[], deleted: string[], truncated_bytes: int, deleted_bytes: int}
	 */
	public function prune_cli(string $log_dir, bool $dry_run = false, ?DateTimeImmutable $now = null): array
	{
		$now ??= new DateTimeImmutable();
		$result = ['truncated' => [], 'deleted' => [], 'truncated_bytes' => 0, 'deleted_bytes' => 0];

		if (!is_dir($log_dir)) {
			return $result;
		}

		$log_dir = rtrim($log_dir, '/\\') . DIRECTORY_SEPARATOR;

		if ($this->cli_max_size_bytes > 0) {
			foreach (scandir($log_dir) ?: [] as $entry) {
				$path = $log_dir . $entry;
				if ($entry === '.' || $entry === '..' || is_link($path) || !is_file($path)) {
					continue;
				}
				if (preg_match('/^([a-z0-9_]+)\.log$/D', $entry, $matches) !== 1) {
					continue;
				}

				$size = filesize($path) ?: 0;
				if ($size <= $this->cli_max_size_bytes) {
					continue;
				}

				$archive = $log_dir . $matches[1] . '-' . $now->format('Ymd-His') . '.log.gz';

				if (!$dry_run) {
					// Same-second collision (overlapping runs): defer to the next run, never overwrite.
					if (file_exists($archive)) {
						log_message('error', "MGR_Log_prune_lib: archive '{$archive}' already exists — skipping '{$entry}' this run.");
						continue;
					}

					if (!$this->gzip_stream($path, $archive, $error)) {
						log_message('error', "MGR_Log_prune_lib: could not archive '{$path}' ({$error}) — leaving it oversized.");
						continue;
					}

					$handle = fopen($path, 'r+b');
					if ($handle === false) {
						log_message('error', "MGR_Log_prune_lib: could not open '{$path}' to truncate it after archiving — leaving it oversized.");
						continue;
					}
					$truncated = ftruncate($handle, 0);
					fclose($handle);
					if (!$truncated) {
						log_message('error', "MGR_Log_prune_lib: ftruncate failed on '{$path}' after archiving — leaving it oversized.");
						continue;
					}
				}

				$result['truncated'][]    = $entry;
				$result['truncated_bytes'] += $size;
			}
		}

		if ($this->cli_keep > 0) {
			$archives_by_job = [];
			foreach (scandir($log_dir) ?: [] as $entry) {
				$path = $log_dir . $entry;
				if ($entry === '.' || $entry === '..' || is_link($path) || !is_file($path)) {
					continue;
				}
				if (preg_match('/^([a-z0-9_]+)-\d{8}-\d{6}\.log\.gz$/D', $entry, $matches) !== 1) {
					continue;
				}
				$archives_by_job[$matches[1]][] = $entry;
			}

			foreach ($archives_by_job as $archives) {
				// Fixed-width YYYYMMDD-HHMMSS suffix sorts lexically the same as chronologically.
				rsort($archives, SORT_STRING);
				foreach (array_slice($archives, $this->cli_keep) as $stale) {
					$path  = $log_dir . $stale;
					$bytes = filesize($path) ?: 0;
					if (!$dry_run) {
						unlink($path);
					}
					$result['deleted'][]    = $stale;
					$result['deleted_bytes'] += $bytes;
				}
			}
		}

		return $result;
	}

	/**
	 * Deletes `$table` rows whose `time` is older than $api_delete_after_days, in primary-key
	 * chunks of $chunk_rows; a failed chunk is logged and stops the stage (the next run
	 * resumes). A dry run only counts.
	 *
	 * @param ?int $now Clock — a test seam; defaults to now.
	 * @return array{deleted: int} Rows deleted, or that would be in a dry run.
	 * @throws InvalidArgumentException When $chunk_rows is below 1.
	 */
	public function prune_api(object $db, string $table, bool $dry_run = false, ?int $now = null, int $chunk_rows = 10000): array
	{
		if ($chunk_rows < 1) {
			throw new InvalidArgumentException("MGR_Log_prune_lib::prune_api: chunk_rows must be at least 1, got {$chunk_rows}.");
		}

		$result = ['deleted' => 0];

		if ($this->api_delete_after_days <= 0 || !$db->table_exists($table)) {
			return $result;
		}

		$cutoff = ($now ?? time()) - $this->api_delete_after_days * 86400;

		$bounds = $db->select_min('id', 'first_id')->select_max('id', 'last_id')->where('time <', $cutoff)->get($table);
		if ($bounds === false) {
			log_message('error', "MGR_Log_prune_lib: could not read the expired id range of '{$table}' — skipping api_log retention.");

			return $result;
		}

		$range = $bounds->row();
		if ($range === null || $range->first_id === null) {
			return $result;
		}

		if ($dry_run) {
			$result['deleted'] = $db->where('time <', $cutoff)->count_all_results($table);

			return $result;
		}

		$last_id = (int) $range->last_id;
		for ($start = (int) $range->first_id; $start <= $last_id; $start += $chunk_rows) {
			// `time` is repeated per chunk: a newer row with a lower id (clock skew) must survive.
			$deleted = $db
				->where('id >=', $start)
				->where('id <', $start + $chunk_rows)
				->where('time <', $cutoff)
				->delete($table);

			if ($deleted === false) {
				log_message('error', "MGR_Log_prune_lib: delete failed on '{$table}' from id {$start} — stopping, the next run resumes.");
				break;
			}

			$result['deleted'] += $db->affected_rows();
		}

		return $result;
	}

	/**
	 * Whole days from a `Y-m-d` filename date to $today — negative for a future date, null if unparsable.
	 */
	private function age_in_days(string $date_string, DateTimeImmutable $today): ?int
	{
		$file_date = DateTimeImmutable::createFromFormat('!Y-m-d', $date_string);
		if ($file_date === false) {
			return null;
		}

		$diff = $today->diff($file_date);
		$days = (int) $diff->format('%a');

		// %a is unsigned: without invert, a future-dated file (clock skew) scores as old as a past one.
		return $diff->invert === 1 ? $days : -$days;
	}

	/**
	 * Stream-gzips $source_path to $dest_path in 1 MB chunks, leaving the source in place.
	 * False on any open/write/close failure, with the partial archive removed.
	 */
	private function gzip_stream(string $source_path, string $dest_path, ?string &$error = null): bool
	{
		// Suppressed so the caller's log line carries the detail; a full disk surfaces here.
		error_clear_last();
		$in = @fopen($source_path, 'rb');
		if ($in === false) {
			$error = error_get_last()['message'] ?? 'fopen failed';

			return false;
		}

		$out = @gzopen($dest_path, 'wb9');
		if ($out === false) {
			$error = error_get_last()['message'] ?? 'gzopen failed';
			fclose($in);

			return false;
		}

		$written = true;
		while ($written && !feof($in)) {
			$chunk   = fread($in, 1048576);
			$written = $chunk !== false && ($chunk === '' || @gzwrite($out, $chunk) === strlen($chunk));
		}
		$closed = @gzclose($out);
		fclose($in);

		if ($written && $closed) {
			return true;
		}

		$error = error_get_last()['message'] ?? 'gzip write failed';
		if (is_file($dest_path)) {
			unlink($dest_path);
		}

		return false;
	}
}
