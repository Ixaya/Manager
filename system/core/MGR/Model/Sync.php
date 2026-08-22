<?php

defined('BASEPATH') or exit('No direct script access allowed');

trait MGR_Model_Sync
{
	/** Columns diffed as instants rather than raw strings — set by a model syncing a TIMESTAMPTZ column. */
	protected array $sync_timestamp_columns = [];

	/**
	 * Reconciles a naive vs. offset-suffixed timestamp value onto the same
	 * instant before diffing.
	 *
	 * @see mgr_format_date_time_sql()
	 */
	private function normalize_timestamp_value(?string $value): ?string
	{
		if ($value === null) {
			return null;
		}

		$this->load->helper('manager_time_helper');
		return mgr_format_date_time_sql($value);
	}

	public function sync_update_insert(array $data, array $where, bool $insert = true, bool $add_sync = false, bool $add_import = true, array $extra_data = [], bool &$modified = false): int|string|false
	{
		$this->check_connect();

		$this->cleanup_columns($where, true);
		$row = $this->get_where($where);

		$this->cleanup_columns($data);
		if (!empty($row)) {
			$update_data = [];
			foreach (array_keys($data) as $key) {
				$stored   = $row[$key] ?? null;
				$incoming = $data[$key];
				if (in_array($key, $this->sync_timestamp_columns, true)) {
					$stored   = $this->normalize_timestamp_value($stored);
					$incoming = $this->normalize_timestamp_value($incoming);
				}

				// Loose compare: DB drivers can return strings ("5") that must equal typed values (5); strict here would resync every row.
				if ($stored != $incoming) {
					$update_data[$key] = $data[$key];
				}
			}

			$update_diff = count($update_data) > 0;

			if ($update_diff) {
				$this->set_alter_keys($update_data);

				$update_data = array_merge($extra_data, $update_data);
			} elseif (!$add_sync) {
				return $row[$this->primary_key];
			}

			if ($add_sync) {
				$update_data['sync_enabled'] = 1;
			}

			$this->apply_alter_filters();
			$result = $this->my_db->update($this->table_name, $update_data, [$this->primary_key => $row[$this->primary_key]]);
			if ($result === true) {
				// affected_rows() isn't cross-engine safe here.
				if ($update_diff) {
					$modified = true;
				}
				return $row[$this->primary_key];
			}
		} elseif ($insert) {
			$this->set_alter_keys($data);

			if ($add_import) {
				$data['import_date'] = $data['last_update'];
			}
			if ($add_sync) {
				$data['sync_enabled'] = 1;
			}

			$result = $this->my_db->insert($this->table_name, array_merge($data, $where, $extra_data));
			if ($result === true) {
				$modified = true;
				return $this->insert_id();
			}
		}

		return false;
	}

	public function sync_update(int|string $id, array $data, bool $timestamp = true, ?array $row = null, int $default_count = 0): bool
	{
		$this->check_connect();
		$this->cleanup_columns($data);

		if ($row !== null) {
			$update_data = [];

			foreach (array_keys($data) as $key) {
				$stored   = $row[$key];
				$incoming = $data[$key];
				if (in_array($key, $this->sync_timestamp_columns, true)) {
					$stored   = $this->normalize_timestamp_value($stored);
					$incoming = $this->normalize_timestamp_value($incoming);
				}

				// Loose compare: DB drivers return strings ("5") that must equal typed values (5); strict here would resync every row.
				if ($stored != $incoming) {
					$update_data[$key] = $data[$key];
				}
			}

			$update_count = count($update_data);
			if ($update_count == 0) {
				return false;
			}

			if ($timestamp === true && $update_count <= $default_count) {
				$timestamp = false;
			}

			$id =  $row[$this->primary_key];
			$data = $update_data;
		}

		if ($this->use_last_update && $timestamp === true) {
			$data['last_update'] = mgr_get_now_date_time_sql_format();
		}

		$this->apply_alter_filters();
		$this->my_db->where($this->primary_key, $id);

		return $this->my_db->update($this->table_name, $data);
	}

	public function sync_update_enabled(int|string|null $id, int $status): bool
	{
		$this->check_connect();

		$query = "UPDATE {$this->table_name} SET sync_enabled = ?";
		$args = [$status];
		if ($id !== null) {
			$query .= " WHERE id = ?";
			$args[] = $id;
		}
		return $this->my_db->query($query, $args);
	}

	public function sync_commit_enabled(): bool
	{
		$this->check_connect();

		// CASE, not NOT/!sync_enabled: sync_enabled can hold values above 1
		// and this must binarize any nonzero value to 0.
		$query = "UPDATE {$this->table_name}
			SET enabled = sync_enabled,
				deleted = CASE WHEN sync_enabled = 0 THEN 1 ELSE 0 END,
				last_update = ?
			WHERE enabled != sync_enabled
				AND (enabled = 0 OR enabled = 1)";

		$now = mgr_get_now_date_time_sql_format();
		return $this->my_db->query($query, [$now]);
	}
}
