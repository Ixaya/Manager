<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once MGRPATH . 'libraries/MGR_Migration_builder.php';

/** Public doors onto MGR_Migration_builder's protected surface, for tests. */
final class MigrationBuilderHarness extends MGR_Migration_builder
{
	public function build_field(mixed ...$named): array
	{
		return $this->field(...$named);
	}

	public function cast_column(string $table, array $field): void
	{
		$this->modify_column_cast($table, $field);
	}

	public function check_no_nulls(string $table, array $columns): void
	{
		$this->assert_no_nulls($table, $columns);
	}

	public function remove_index(string $table, string $column, bool $unique = false, ?string $name = null): bool
	{
		return $this->drop_index(table: $table, columns: $column, name: $name, unique: $unique);
	}

	public function begin_ddl(): void
	{
		$this->start_ddl_transaction();
	}

	public function end_ddl(): void
	{
		$this->end_ddl_transaction();
	}
}
