<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * The rules every MGR forge shares: `add_key()` and the migration builder derive one index name,
 * and a CHANGE honors a field's `unique` with one guarded unique statement. A family trait
 * overrides only what its engine does differently.
 */
trait MGR_DB_forge_common
{
	/**
	 * The name the framework gives an index over $columns on $table, cut to the engine's limit.
	 *
	 * @param string[] $columns
	 */
	public function index_name(string $table, array $columns, bool $unique = false): string
	{
		return $this->truncate_identifier($table . '_' . implode('_', $columns) . ($unique ? '_key' : ''));
	}

	/**
	 * Cuts an identifier longer than $limit (the engine's own by default) to $limit - 11
	 * characters, `_` and the first 10 hex digits of its md5.
	 */
	public function truncate_identifier(string $identifier, ?int $limit = null): string
	{
		$limit ??= $this->_identifier_limit();

		if (strlen($identifier) <= $limit) {
			return $identifier;
		}

		return substr($identifier, 0, ($limit - 11)) . '_' . substr(md5($identifier), 0, 10);
	}

	/** The engine's maximum identifier length. */
	abstract protected function _identifier_limit(): int;

	/** Whether the index named $name on $table is unique, per the engine's catalog; null if absent. */
	abstract public function index_uniqueness(string $table, string $name): ?bool;

	/**
	 * The vendor `DB_forge::_process_indexes()` loop, unchanged except that the index is
	 * named by `index_name()`.
	 *
	 * @param string $table
	 * @return string[]
	 */
	protected function _process_indexes($table): array
	{
		$sqls = [];

		for ($i = 0, $c = count($this->keys); $i < $c; $i++) {
			if (is_array($this->keys[$i])) {
				for ($i2 = 0, $c2 = count($this->keys[$i]); $i2 < $c2; $i2++) {
					if (!isset($this->fields[$this->keys[$i][$i2]])) {
						unset($this->keys[$i][$i2]);
						continue;
					}
				}
			} elseif (!isset($this->fields[$this->keys[$i]])) {
				unset($this->keys[$i]);
				continue;
			}

			if (!is_array($this->keys[$i])) {
				$this->keys[$i] = [$this->keys[$i]];
			}

			$sqls[] = 'CREATE INDEX ' . $this->db->escape_identifiers($this->index_name($table, $this->keys[$i]))
				. ' ON ' . $this->db->escape_identifiers($table)
				. ' (' . implode(', ', $this->db->escape_identifiers($this->keys[$i])) . ');';
		}

		return $sqls;
	}

	/**
	 * On a CHANGE, each `unique` field gets a unique statement after the vendor's SQL, unless the
	 * engine already holds a unique under the derived name.
	 *
	 * @return string|string[]|false
	 * @throws RuntimeException if a plain index already holds the name of a unique, or a column is
	 *         renamed with `unique` while its old name still holds one
	 */
	protected function _alter_table($alter_type, $table, $field)
	{
		if ($alter_type !== 'CHANGE') {
			return parent::_alter_table($alter_type, $table, $field);
		}

		$field = $this->_prepare_change($field);

		// The vendor appends UNIQUE inline: MySQL repeats it on every call, SQL Server rejects it.
		$vendor_field = array_map(
			static fn (array $column): array => empty($column['unique']) ? $column : [...$column, 'unique' => ''],
			$field
		);
		$sqls = parent::_alter_table($alter_type, $table, $vendor_field);

		if (!is_array($sqls)) {
			return $sqls;
		}

		foreach ($field as $column) {
			if (empty($column['unique'])) {
				continue;
			}

			$column_name = $this->_changed_column_name($column);
			$index_name  = $this->index_name($table, [$column_name], unique: true);
			$existing    = $this->index_uniqueness($table, $index_name);

			if ($existing === false) {
				// modify_column() resets only after _alter_table() returns, and the dbforge is shared.
				$this->_reset();

				throw new RuntimeException(
					static::class . "::modify_column(): '{$index_name}' on '{$table}' is a plain index; "
						. 'drop it with drop_index() before making the column unique.'
				);
			}

			if ($existing === null && $column_name !== $column['name']) {
				$old_index_name = $this->index_name($table, [$column['name']], unique: true);

				if ($this->index_uniqueness($table, $old_index_name) === true) {
					$this->_reset();

					throw new RuntimeException(
						static::class . "::modify_column(): '{$column['name']}' on '{$table}' already has the unique "
							. "'{$old_index_name}'; drop it with drop_index() before renaming the column with unique."
					);
				}
			}

			if ($existing === null) {
				$sqls[] = $this->_unique_statement($table, $index_name, $column_name);
			}
		}

		return $sqls;
	}

	/**
	 * Hook run on the processed fields before a CHANGE is built.
	 *
	 * @param array<int, array<string, mixed>> $field Fields as processed by `_process_fields()`
	 * @return array<int, array<string, mixed>>
	 */
	protected function _prepare_change(array $field): array
	{
		return $field;
	}

	/**
	 * The column's name once the CHANGE has run.
	 *
	 * @param array<string, mixed> $column
	 */
	protected function _changed_column_name(array $column): string
	{
		return !empty($column['new_name']) ? $column['new_name'] : $column['name'];
	}

	/** The statement that makes $column_name unique under $index_name. */
	abstract protected function _unique_statement(string $table, string $index_name, string $column_name): string;
}
