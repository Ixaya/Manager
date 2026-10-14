<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Makes `modify_column()` honor `unique` on PostgreSQL: the vendor forges never emit it on CHANGE.
 */
trait MGR_DB_pgsql_forge_unique
{
	/**
	 * Appends one unique index per `unique` field to a CHANGE.
	 *
	 * @return string|string[]|false
	 */
	protected function _alter_table($alter_type, $table, $field)
	{
		$sqls = parent::_alter_table($alter_type, $table, $field);

		if ($alter_type !== 'CHANGE' || !is_array($sqls)) {
			return $sqls;
		}

		foreach ($field as $column) {
			if (empty($column['unique'])) {
				continue;
			}

			// A rename statement precedes this one, so the index goes on the new name.
			$column_name = !empty($column['new_name']) ? $column['new_name'] : $column['name'];
			$sqls[] = 'CREATE UNIQUE INDEX IF NOT EXISTS ' . $this->_unique_index_name($table, $column_name)
				. ' ON ' . $this->db->escape_identifiers($table)
				. ' (' . $this->db->escape_identifiers($column_name) . ')';
		}

		return $sqls;
	}

	/**
	 * Same name MGR_Migration_builder::_index_name() derives on PostgreSQL, so drop_index()
	 * finds it. Keep both in sync: `{table}_{column}_key`, cut to 63 characters.
	 */
	protected function _unique_index_name(string $table, string $column): string
	{
		$name = "{$table}_{$column}_key";

		if (strlen($name) <= 63) {
			return $name;
		}

		return substr($name, 0, 52) . '_' . substr(md5($name), 0, 10);
	}
}
