<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MGR_DB_forge_common.php';

/**
 * SQL Server (`sqlsrv`, `pdo/sqlsrv`, `pdo/dblib`): the common rule, `{table}_{columns}` and
 * `{table}_{columns}_key`, `unsigned` widening integers, and a CHANGE's `unique` as a separate
 * `CREATE UNIQUE INDEX`.
 */
trait MGR_DB_sqlsrv_forge_family
{
	use MGR_DB_forge_common;

	protected function _identifier_limit(): int
	{
		return 128;
	}

	/**
	 * Widens an `unsigned` integer to the type holding its full unsigned range; other types stay as given.
	 * TINYINT is natively 0-255 and stays.
	 */
	protected function _attr_unsigned(&$attributes, &$field): void
	{
		if (($attributes['UNSIGNED'] ?? false) !== true) {
			return;
		}

		$attributes['UNSIGNED'] = false;

		$widened = match (strtoupper($attributes['TYPE'])) {
			'SMALLINT' => 'INT',
			'INT'      => 'BIGINT',
			'BIGINT'   => 'DECIMAL',
			default    => null,
		};

		if ($widened === null) {
			return;
		}

		$field['type'] = $widened;

		// The Forge builds `length` from CONSTRAINT after this runs.
		if ($widened === 'DECIMAL') {
			$attributes['CONSTRAINT'] = '20,0';
		}
	}

	public function index_uniqueness(string $table, string $name): ?bool
	{
		$row = $this->db->query(
			'SELECT CAST(is_unique AS int) AS is_unique FROM sys.indexes WHERE object_id = OBJECT_ID(?) AND name = ?',
			[$table, $name]
		)->row();

		return $row === null ? null : (int)$row->is_unique === 1;
	}

	/** ALTER COLUMN never renames: the vendor ignores `new_name` here. */
	protected function _changed_column_name(array $column): string
	{
		return $column['name'];
	}

	protected function _unique_statement(string $table, string $index_name, string $column_name): string
	{
		return 'CREATE UNIQUE INDEX ' . $this->db->escape_identifiers($index_name)
			. ' ON ' . $this->db->escape_identifiers($table)
			. ' (' . $this->db->escape_identifiers($column_name) . ')';
	}
}
