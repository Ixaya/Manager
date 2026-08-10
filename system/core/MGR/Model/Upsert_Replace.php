<?php

defined('BASEPATH') or exit('No direct script access allowed');

trait MGR_Model_Upsert_Replace
{
	/**
	 * REPLACE INTO semantics: delete any row occupying $data's primary key,
	 * then insert $data fresh.
	 *
	 * @param array<string, mixed> $data
	 * @return bool True if the row was replaced, false on failure.
	 * @throws InvalidArgumentException If $data is missing the primary key.
	 */
	public function replace_pk(array $data): bool
	{
		if (!isset($data[$this->primary_key])) {
			throw new InvalidArgumentException(
				"MGR_Model::replace_pk(): {$this->table_name} needs its primary key ('{$this->primary_key}') in \$data."
			);
		}

		$this->my_db->trans_start();

		$id = $data[$this->primary_key];

		$this->my_db->where($this->primary_key, $id);
		$this->my_db->delete($this->table_name);

		$success = $this->insert($data) !== false;

		$this->my_db->trans_complete();

		return $this->my_db->trans_status() && $success;
	}

	/**
	 * Atomic upsert: insert $data, or merge it into the row already
	 * occupying $conflict_target if one conflicts — a single statement,
	 * safe under concurrent callers targeting the same key.
	 *
	 * @param array<string, mixed> $data
	 * @param array<int, string>|string $conflict_target Column(s) forming the one
	 *   unique/primary key constraint to resolve against — always explicit; a
	 *   table with more than one candidate constraint has no safe default to guess.
	 * @return int|string|bool The row's id, or false on failure.
	 * @throws InvalidArgumentException If $conflict_target's column(s) are missing from $data.
	 * @throws RuntimeException If the current driver has no verified implementation.
	 */
	public function upsert_atomic(array $data, array|string $conflict_target): int|string|bool
	{
		$this->set_alter_keys($data);

		return match ($this->my_db_driver) {
			MgrDriver::Postgres => $this->upsert_atomic_postgres($data, $conflict_target),
			default => throw new RuntimeException(
				"MGR_Model::upsert_atomic(): no verified implementation for driver '{$this->my_db_driver->value}'."
			),
		};
	}

	/**
	 * upsert_atomic()'s implementation for drivers with ON CONFLICT/RETURNING —
	 * atomic, and the id comes back from the same statement that wrote it.
	 *
	 * @throws InvalidArgumentException If $conflict_target's column(s) are missing from $data.
	 */
	private function upsert_atomic_postgres(array $data, array|string $conflict_target): int|string|bool
	{
		$target = (array) $conflict_target;
		$missing = array_diff($target, array_keys($data));

		if ($missing !== []) {
			throw new InvalidArgumentException(
				"MGR_Model::upsert_atomic(): {$this->table_name} needs conflict column(s): " . implode(', ', $missing)
			);
		}

		$columns = array_keys($data);
		$escape_identifier = fn (string $column): string => $this->my_db->escape_identifiers($column);

		$set = [];
		// The primary key is never reassigned by a conflict resolved on a
		// different column — a caller echoing back a known id must not have
		// it silently moved onto whatever row the conflict target matched.
		foreach (array_diff($columns, $target, [$this->primary_key]) as $column) {
			$set[] = $escape_identifier($column) . ' = EXCLUDED.' . $escape_identifier($column);
		}

		// Every remaining column is either the conflict target or the
		// primary key, so there's nothing left to SET — self-assign the
		// target's first column so a row still comes back via RETURNING.
		if ($set === []) {
			$set[] = $escape_identifier($target[0]) . ' = EXCLUDED.' . $escape_identifier($target[0]);
		}

		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO UPDATE SET %s RETURNING %s',
			$this->my_db->protect_identifiers($this->table_name, true, null, false),
			implode(', ', array_map($escape_identifier, $columns)),
			implode(', ', array_fill(0, count($columns), '?')),
			implode(', ', array_map($escape_identifier, $target)),
			implode(', ', $set),
			$escape_identifier($this->primary_key)
		);

		// Force a result object: is_write_type() only special-cases
		// RETURNING for pdo_pgsql, not pdo_sqlite, so auto-detection would
		// otherwise hand back a bare true here on SQLite.
		$row = $this->my_db->query($sql, array_values($data), true)->row_array();

		return $row[$this->primary_key] ?? false;
	}
}
