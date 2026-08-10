<?php

defined('BASEPATH') or exit('No direct script access allowed');

trait MGR_Model_Sync
{
	public function sync_update_insert(array $data, array $where, bool $insert = true, bool $add_sync = false, bool $add_import = true, array $extra_data = [], bool &$modified = false): int|string|false
	{
		$this->check_connect();

		$this->cleanup_columns($where, true);
		$row = $this->get_where($where);

		$this->cleanup_columns($data);
		if (!empty($row)) {
			$update_data = [];
			foreach (array_keys($data) as $key) {
				// Loose compare: DB drivers can return strings ("5") that must equal typed values (5); strict here would resync every row.
				if (($row[$key] ?? null) != $data[$key]) {
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
				// Loose compare: DB drivers return strings ("5") that must equal typed values (5); strict here would resync every row.
				if ($row[$key] != $data[$key]) {
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
			$data['last_update'] = date('Y-m-d H:i:s');
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

		$now = date('Y-m-d H:i:s');
		return $this->my_db->query($query, [$now]);
	}
}
