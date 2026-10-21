<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MGR_DB_forge_common.php';

/**
 * MySQL/MariaDB (`mysqli`, `pdo/mysql`): an index is named after its columns, plain and
 * unique alike, the name the engine gives an inline `UNIQUE`; a CHANGE's `unique` becomes an
 * `ADD UNIQUE INDEX`.
 */
trait MGR_DB_mysql_forge_family
{
	use MGR_DB_forge_common;

	/**
	 * `{columns}`, cut to 64 characters.
	 *
	 * @param string[] $columns
	 */
	public function index_name(string $table, array $columns, bool $unique = false): string
	{
		return $this->truncate_identifier(implode('_', $columns));
	}

	protected function _identifier_limit(): int
	{
		return 64;
	}

	public function index_uniqueness(string $table, string $name): ?bool
	{
		// One row per indexed column; MIN() over none is NULL, so an absent index reads as null.
		$row = $this->db->query(
			'SELECT MIN(NON_UNIQUE) AS non_unique FROM information_schema.statistics '
				. 'WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
			[$table, $name]
		)->row();

		return $row->non_unique === null ? null : (int)$row->non_unique === 0;
	}

	/**
	 * The vendor's inline `KEY` loop (`CI_DB_mysqli_forge`, `CI_DB_pdo_mysql_forge`), unchanged
	 * except that the key is named by `index_name()`; the vendor's uncut name fails past 64.
	 *
	 * @param string $table
	 */
	protected function _process_indexes($table): string
	{
		$sql = '';

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

			$sql .= ",\n\tKEY " . $this->db->escape_identifiers($this->index_name($table, $this->keys[$i]))
				. ' (' . implode(', ', $this->db->escape_identifiers($this->keys[$i])) . ')';
		}

		$this->keys = [];

		return $sql;
	}

	protected function _unique_statement(string $table, string $index_name, string $column_name): string
	{
		return 'ALTER TABLE ' . $this->db->escape_identifiers($table)
			. ' ADD UNIQUE INDEX ' . $this->db->escape_identifiers($index_name)
			. ' (' . $this->db->escape_identifiers($column_name) . ')';
	}
}
