<?php

(defined('BASEPATH')) or exit('No direct script access allowed');

class IX_Model_Dyn_clause
{
	public const EQUAL = 'equal';
	public const OR_EQUAL = 'or_equal';
	public const LIKE = 'like';
	public const OR_LIKE = 'or_like';
	public const WHERE_IN = 'where_in';
	public const OR_WHERE_IN = 'or_where_in';
	public const GROUP = 'group';
	public const OR_GROUP = 'or_group';
}

final class IX_Model_Dyn_join
{
	public function __construct(
		private readonly string        $table,
		private readonly IX_Model_Dyn_join_type $type,
		private readonly array         $on,
	) {
		$this->_validate();
	}

	// ── Validation ────────────────────────────────────────────────────────────

	private function _validate(): void
	{
		if (empty($this->table)) {
			throw new InvalidArgumentException(
				"IX_Model_Dyn_join: table name cannot be empty."
			);
		}
		if (empty($this->on)) {
			throw new InvalidArgumentException(
				"IX_Model_Dyn_join: on[] cannot be empty — at least one condition is required."
			);
		}
	}

	// ── Apply ─────────────────────────────────────────────────────────────────

	/**
	 * Execute the join against CI3's database instance.
	 *
	 * @param  object $db  e.g. $this->my_db
	 * @return void
	 */
	public function apply(object &$db): void
	{
		$condition = $this->_buildCondition($db);

		if ($condition === '') {
			return;
		}


		$db->join($this->table, $condition, $this->type->toCi3Type());
	}

	// ── Condition builder ─────────────────────────────────────────────────────

	private function _buildCondition(object $db): string
	{
		$parts = [];

		foreach ($this->on as $kind => $fields) {
			// OR_* clause keys get OR glue; everything else gets AND
			$glue = str_starts_with($kind, 'or_') ? 'OR' : 'AND';

			switch ($kind) {
				case IX_Model_Dyn_clause::EQUAL:
				case IX_Model_Dyn_clause::OR_EQUAL:
					foreach ($fields as $col => $val) {
						$parts[] = [$glue, "{$col} = {$val}"];
					}
					break;

				case IX_Model_Dyn_clause::LIKE:
				case IX_Model_Dyn_clause::OR_LIKE:
					foreach ($fields as $col => $val) {
						$escaped = $db->escape_like_str($val);
						$parts[] = [$glue, "{$col} LIKE '{$escaped}'"];
					}
					break;

				case IX_Model_Dyn_clause::WHERE_IN:
				case IX_Model_Dyn_clause::OR_WHERE_IN:
					foreach ($fields as $col => $values) {
						$list    = implode(', ', array_map([$db, 'escape'], $values));
						$parts[] = [$glue, "{$col} IN ({$list})"];
					}
					break;
			}
		}

		if (empty($parts)) {
			return '';
		}

		// First fragment never gets a leading AND/OR
		$sql = $parts[0][1];
		for ($i = 1, $n = count($parts); $i < $n; $i++) {
			$sql .= " {$parts[$i][0]} {$parts[$i][1]}";
		}

		return $sql;
	}
}


// ---------------------------------------------------------------------------
// IX_Model_Dyn_join_type — backed enum replacing the class-with-constants pattern.
// ---------------------------------------------------------------------------

enum IX_Model_Dyn_join_type: string
{
	case Inner = 'INNER JOIN';
	case Left  = 'LEFT JOIN';
	case Right = 'RIGHT JOIN';
	case Full  = 'FULL JOIN';
	case Cross = 'CROSS JOIN';
	case Join  = 'JOIN';

	/** Returns the lowercase type string CI3's join() accepts as its 3rd param. */
	public function toCi3Type(): string
	{
		return match ($this) {
			self::Inner => 'inner',
			self::Left  => 'left',
			self::Right => 'right',
			self::Full  => 'full outer',
			self::Cross => 'cross',
			self::Join  => '',
		};
	}
}

class IX_Model_Dyn extends MY_Model
{
	/**
	 * Dynamic query with optional where clauses, joins, ordering, and limit.
	 *
	 * @param  string            $fields    Comma-separated select list. '' = SELECT *.
	 * @param  array             $where     IX_Model_Dyn_clause-keyed condition array.
	 * @param  IX_Model_Dyn_join[]  $join      Array of IX_Model_Dyn_join instances.
	 * @param  string            $limit     Row limit as string, e.g. '25' or '0,25'.
	 * @param  string            $order_by  ORDER BY expression.
	 * @return array
	 */
	public function get_all_dynamic(array|string $fields = [], array $where = [], ?array $join = null, string $limit = '', string $order_by = ''): array
	{
		$this->apply_list_filters($fields, [], $limit, $order_by);


		if (!empty($join)) {
			foreach ($join as $data) {
				$data->apply($this->my_db);
			}
		}

		if (!empty($where)) {
			foreach ($where as $kind => $data) {
				switch ($kind) {
					case IX_Model_Dyn_clause::GROUP:
						$this->my_db->group_start(); // Opens (
						foreach ($data as $kind => $fields) {
							$this->apply_where_condition($kind, $fields);
						}
						$this->my_db->group_end(); // Closes )
						break;

					case IX_Model_Dyn_clause::OR_GROUP:
						$this->my_db->or_group_start(); // Opens OR (
						foreach ($data as $kind => $fields) {
							$this->apply_where_condition($kind, $fields);
						}
						$this->my_db->group_end();
						break;

					default:
						// Regular conditions without grouping
						$this->apply_where_condition($kind, $data);
						break;
				}
			}
		}

		return $this->excecute_list();
	}

	/**
	 * Dynamic query with optional where clauses, joins, ordering, and limit.
	 *
	 * @param  string                      $table
	 * @param  IX_Model_Dyn_join_type  $type
	 * @param array<IX_Model_Dyn_clause::*, array<string, string>> $on
	 * @return IX_Model_Dyn_join
	 */
	public function build_join(
		string $table,
		IX_Model_Dyn_join_type $type,
		array $on
	) {
		return  new IX_Model_Dyn_join(
			table: $table,
			type: $type,
			on: $on,
		);
	}
	protected function build_function(MgrFunctionType $function, array $args = []): string
	{
		return mgr_build_function(
			function: $function,
			driver: $this->my_db_driver,
			args: $args,
		);
	}

	protected function build_field_select(string $name, MgrFunctionType $function, array $args = []): string
	{
		return mgr_build_field_select(
			name: $name,
			function: $function,
			driver: $this->my_db_driver,
			args: $args ?: [$name],   // default: use the alias name as the sole arg
		);
	}

	/**
	 * Apply a where condition based on IX_Model_Dyn_clause type
	 */
	private function apply_where_condition(string $kind, $fields): void
	{
		switch ($kind) {
			case IX_Model_Dyn_clause::EQUAL:
				$this->my_db->where($fields);
				break;

			case IX_Model_Dyn_clause::OR_EQUAL:
				$this->my_db->or_where($fields);
				break;

			case IX_Model_Dyn_clause::LIKE:
				$this->my_db->like($fields);
				break;

			case IX_Model_Dyn_clause::OR_LIKE:
				$this->my_db->or_like($fields);
				break;

			case IX_Model_Dyn_clause::WHERE_IN:
				foreach ($fields as $field => $values) {
					$this->my_db->where_in($field, $values);
				}
				break;

			case IX_Model_Dyn_clause::OR_WHERE_IN:
				foreach ($fields as $field => $values) {
					$this->my_db->or_where_in($field, $values);
				}
				break;
		}
	}
}
