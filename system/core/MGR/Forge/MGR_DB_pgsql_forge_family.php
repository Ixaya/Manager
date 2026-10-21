<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MGR_DB_forge_common.php';

/**
 * PostgreSQL (`postgre`, `pdo/pgsql`): names derived the way the engine derives its own,
 * `unsigned` widening integers, `auto_increment` as `SERIAL`/`BIGSERIAL`, `cast` adding `USING`
 * to a type change, and `modify_column()` making a `unique` field a constraint, which the vendor
 * forges never emit on CHANGE.
 */
trait MGR_DB_pgsql_forge_family
{
	use MGR_DB_forge_common;

	/**
	 * The name PostgreSQL itself gives an unnamed index (`{table}_{columns}_idx`) or a
	 * column's unique constraint (`{table}_{columns}_key`), in the exact case given.
	 *
	 * @param string[] $columns
	 */
	public function index_name(string $table, array $columns, bool $unique = false): string
	{
		return $this->pg_object_name(
			table_part: $table,
			column_part: $this->_pg_index_name_addition($columns),
			label: $unique ? 'key' : 'idx',
		);
	}

	/**
	 * `{table_part}_{column_part}_{label}` cut the way PostgreSQL cuts its own generated names
	 * (`makeObjectName()`): the longer part shortens first, at a UTF-8 character boundary.
	 */
	public function pg_object_name(string $table_part, string $column_part, string $label): string
	{
		$available    = $this->_identifier_limit() - (1 + strlen($label) + 1);
		$table_bytes  = strlen($table_part);
		$column_bytes = strlen($column_part);

		// The engine's own loop: on a tie the column part gives way.
		while ($table_bytes + $column_bytes > $available) {
			if ($table_bytes > $column_bytes) {
				$table_bytes--;
			} else {
				$column_bytes--;
			}
		}

		return mb_strcut($table_part, 0, $table_bytes, 'UTF-8') . '_'
			. mb_strcut($column_part, 0, $column_bytes, 'UTF-8') . '_' . $label;
	}

	protected function _identifier_limit(): int
	{
		return 63;
	}

	public function index_uniqueness(string $table, string $name): ?bool
	{
		// Constraint-backed indexes are listed too; the int cast keeps native 't'/'f' and PDO bools alike.
		$row = $this->db->query(
			'SELECT CASE WHEN i.indisunique THEN 1 ELSE 0 END AS is_unique FROM pg_index i '
				. 'JOIN pg_class ic ON ic.oid = i.indexrelid '
				. 'JOIN pg_class tc ON tc.oid = i.indrelid '
				. 'JOIN pg_namespace n ON n.oid = tc.relnamespace '
				. 'WHERE n.nspname = current_schema() AND tc.relname = ? AND ic.relname = ?',
			[$table, $name]
		)->row();

		return $row === null ? null : (int)$row->is_unique === 1;
	}

	/**
	 * The column part PostgreSQL builds for a generated index name (`ChooseIndexNameAddition()`):
	 * names joined with `_`, stopping once it reaches 64 bytes.
	 *
	 * @param string[] $columns
	 */
	protected function _pg_index_name_addition(array $columns): string
	{
		$addition = '';

		foreach ($columns as $column) {
			if ($addition !== '') {
				$addition .= '_';
			}

			$addition .= $column;

			if (strlen($addition) >= 64) {
				break;
			}
		}

		return $addition;
	}

	/**
	 * Widens an `unsigned` integer to the type holding its full unsigned range; other types stay as given.
	 */
	protected function _attr_unsigned(&$attributes, &$field): void
	{
		if (($attributes['UNSIGNED'] ?? false) !== true) {
			return;
		}

		$attributes['UNSIGNED'] = false;

		$widened = match (strtoupper($attributes['TYPE'])) {
			'INT2', 'SMALLINT'       => 'INTEGER',
			'INT', 'INT4', 'INTEGER' => 'BIGINT',
			'INT8', 'BIGINT'         => 'NUMERIC',
			default                  => null,
		};

		if ($widened === null) {
			return;
		}

		$field['type'] = $widened;

		// The Forge builds `length` from CONSTRAINT after this runs.
		if ($widened === 'NUMERIC') {
			$attributes['CONSTRAINT'] = '20,0';
		}
	}

	/**
	 * Turns `auto_increment` into `SERIAL`, or `BIGSERIAL` for `BIGINT`, `INT8` and `NUMERIC`.
	 */
	protected function _attr_auto_increment(&$attributes, &$field): void
	{
		if (($attributes['AUTO_INCREMENT'] ?? false) !== true) {
			return;
		}

		if (in_array(strtoupper((string) $field['type']), ['BIGINT', 'INT8', 'NUMERIC'], true)) {
			$field['type'] = 'BIGSERIAL';

			// The Forge appends the length after this runs; BIGSERIAL takes none.
			unset($attributes['CONSTRAINT']);

			return;
		}

		$field['type'] = 'SERIAL';
	}

	/**
	 * Refuses a CHANGE whose type is SERIAL or BIGSERIAL, which PostgreSQL accepts only on CREATE and ADD.
	 *
	 * @param array<int, array<string, mixed>> $field Fields as processed by `_process_fields()`
	 * @throws RuntimeException if a field's type is SERIAL or BIGSERIAL
	 */
	protected function _reject_serial_change(array $field): void
	{
		foreach ($field as $column) {
			if (!in_array(strtoupper((string) ($column['type'] ?? '')), ['SERIAL', 'BIGSERIAL'], true)) {
				continue;
			}

			// modify_column() resets only after _alter_table() returns, and the dbforge is shared.
			$this->_reset();

			throw new RuntimeException(
				static::class . "::modify_column(): PostgreSQL cannot add auto_increment to the existing column "
					. "'{$column['name']}'; use add_auto_increment() in the migration builder."
			);
		}
	}

	/**
	 * Appends `USING "col"::BASE` to the type change of each field that sets `cast`, so a change
	 * across type families succeeds. BASE is the type without COLLATE or length: the TYPE clause
	 * applies the length and rejects an overlong value instead of truncating it.
	 *
	 * @param array<int, array<string, mixed>> $field Fields as processed by `_process_fields()`
	 * @return array<int, array<string, mixed>>
	 */
	protected function _add_using_casts(array $field): array
	{
		foreach ($field as $index => $column) {
			if ($column['_literal'] !== false || !isset($column['type']) || empty($this->fields[$column['name']]['cast'])) {
				continue;
			}

			$base_type = trim((string) preg_replace(['/\s+COLLATE\s.*$/is', '/\s*\([^)]*\)/'], '', $column['type']));

			// A bare CHAR is CHAR(1).
			if (in_array(strtoupper($base_type), ['CHAR', 'CHARACTER'], true)) {
				$base_type = 'TEXT';
			}

			$field[$index]['length'] .= ' USING ' . $this->db->escape_identifiers($column['name']) . '::' . $base_type;
		}

		return $field;
	}

	/**
	 * On a CHANGE: refuses `auto_increment` and adds `USING` to the type change of a field that
	 * sets `cast`.
	 *
	 * @param array<int, array<string, mixed>> $field Fields as processed by `_process_fields()`
	 * @return array<int, array<string, mixed>>
	 * @throws RuntimeException if a field's type is SERIAL or BIGSERIAL
	 */
	protected function _prepare_change(array $field): array
	{
		$this->_reject_serial_change($field);

		return $this->_add_using_casts($field);
	}

	protected function _unique_statement(string $table, string $index_name, string $column_name): string
	{
		return 'ALTER TABLE ' . $this->db->escape_identifiers($table)
			. ' ADD CONSTRAINT ' . $this->db->escape_identifiers($index_name)
			. ' UNIQUE (' . $this->db->escape_identifiers($column_name) . ')';
	}
}
