<?php

defined('BASEPATH') or exit('No direct script access allowed');

class MGR_Model_Dyn_clause
{
	public const EQUAL = 'equal';                  // column = value    (value escaped via $db->escape() — safe default)
	public const OR_EQUAL = 'or_equal';
	public const LIKE = 'like';
	public const OR_LIKE = 'or_like';
	public const WHERE_IN = 'where_in';
	public const OR_WHERE_IN = 'or_where_in';
	public const GROUP = 'group';
	public const OR_GROUP = 'or_group';
	public const EQUAL_COL = 'equal_col';          // column = column   (both sides validated as identifiers, interpolated raw)
	public const OR_EQUAL_COL = 'or_equal_col';

	/**
	 * Validate a SQL identifier (column or table.column).
	 * Throws instead of escaping: identifiers must never need escaping.
	 *
	 * @throws InvalidArgumentException
	 */
	public static function assert_identifier(string $identifier): void
	{
		if (!mgr_is_sql_identifier($identifier)) {
			throw new InvalidArgumentException(
				"MGR_Model_Dyn: invalid SQL identifier '{$identifier}'."
			);
		}
	}
}

final class MGR_Model_Dyn_join
{
	public function __construct(
		private readonly string                  $table,
		private readonly MGR_Model_Dyn_join_type $type,
		private readonly array                   $on,
	) {
		$this->_validate();
	}

	// ── Validation ────────────────────────────────────────────────────────────

	private function _validate(): void
	{
		if (empty($this->table)) {
			throw new InvalidArgumentException(
				'MGR_Model_Dyn_join: table name cannot be empty.'
			);
		}
		if (empty($this->on)) {
			throw new InvalidArgumentException(
				'MGR_Model_Dyn_join: on[] cannot be empty — at least one condition is required.'
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
	public function apply(object $db): void
	{
		$condition = $this->_buildCondition($db);

		if ($condition === '') {
			return;
		}

		$db->join($this->table, $condition, $this->type->toCi3Type(), true);
	}

	// ── Condition builder ─────────────────────────────────────────────────────

	/**
	 * Build the ON condition string.
	 *
	 * NOTE on precedence: mixed AND/OR fragments are emitted flat, without
	 * parentheses — SQL's native precedence applies (AND binds tighter than OR).
	 * If you need grouping inside an ON clause, split into separate joins or
	 * use EQUAL with a view.
	 *
	 * @throws InvalidArgumentException on unknown clause kinds, invalid
	 *         identifiers, or empty IN() value lists.
	 */
	private function _buildCondition(object $db): string
	{
		$parts = [];

		foreach ($this->_normalized_on() as [$kind, $fields]) {
			// OR_* clause keys get OR glue; everything else gets AND
			$glue = str_starts_with($kind, 'or_') ? 'OR' : 'AND';

			switch ($kind) {
				case MGR_Model_Dyn_clause::EQUAL_COL:
				case MGR_Model_Dyn_clause::OR_EQUAL_COL:
					// column = column — both sides are identifiers, never literals.
					foreach ($fields as $col => $ref) {
						MGR_Model_Dyn_clause::assert_identifier($col);
						MGR_Model_Dyn_clause::assert_identifier($ref);

						// $col = $db->escape_identifiers($col);
						// $ref = $db->escape_identifiers($ref);

						$parts[] = [$glue, "{$col} = {$ref}"];
					}
					break;

				case MGR_Model_Dyn_clause::EQUAL:
				case MGR_Model_Dyn_clause::OR_EQUAL:
					// column = literal — value is escaped and quoted by the driver.
					foreach ($fields as $col => $val) {
						MGR_Model_Dyn_clause::assert_identifier($col);
						$escaped = $db->escape($val);
						$parts[] = [$glue, "{$col} = {$escaped}"];
					}
					break;

				case MGR_Model_Dyn_clause::LIKE:
				case MGR_Model_Dyn_clause::OR_LIKE:
					// Matches CI3 like() default: wildcard on both sides.
					// escape_like_str() prefixes %, _ and ! with '!', hence ESCAPE '!'.
					foreach ($fields as $col => $val) {
						MGR_Model_Dyn_clause::assert_identifier($col);
						$escaped = $db->escape_like_str($val);
						$parts[] = [$glue, "{$col} LIKE '%{$escaped}%' ESCAPE '!'"];
					}
					break;

				case MGR_Model_Dyn_clause::WHERE_IN:
				case MGR_Model_Dyn_clause::OR_WHERE_IN:
					foreach ($fields as $col => $values) {
						MGR_Model_Dyn_clause::assert_identifier($col);
						if (empty($values)) {
							throw new InvalidArgumentException(
								"MGR_Model_Dyn_join: empty IN() list for column '{$col}'."
							);
						}
						$list    = implode(', ', array_map([$db, 'escape'], $values));
						$parts[] = [$glue, "{$col} IN ({$list})"];
					}
					break;

				default:
					throw new InvalidArgumentException(
						"MGR_Model_Dyn_join: unknown clause kind '{$kind}'."
					);
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

	/**
	 * Normalize on[] so the same clause kind may appear more than once.
	 *
	 * Accepted formats (can be mixed):
	 *   ['equal' => ['a.id' => 'b.a_id']]                       — assoc, one entry per kind
	 *   [['equal' => [...]], ['equal_val' => [...]]]            — list entries allow repeats
	 *
	 * @return iterable<array{0: string, 1: array}>
	 */
	private function _normalized_on(): iterable
	{
		foreach ($this->on as $key => $value) {
			if (is_int($key)) {
				if (!is_array($value)) {
					throw new InvalidArgumentException(
						'MGR_Model_Dyn_join: list-style on[] entries must be arrays of [kind => fields].'
					);
				}
				foreach ($value as $kind => $fields) {
					yield [$kind, $fields];
				}
			} else {
				yield [$key, $value];
			}
		}
	}
}


// ---------------------------------------------------------------------------
// MGR_Model_Dyn_join_type — backed enum replacing the class-with-constants pattern.
//
// FULL and CROSS were removed deliberately: CI3's join() whitelists the type
// against LEFT/RIGHT/OUTER/INNER/LEFT OUTER/RIGHT OUTER and silently degrades
// anything else to a plain (inner) JOIN. MySQL has no FULL JOIN at all.
// ---------------------------------------------------------------------------

enum MGR_Model_Dyn_join_type: string
{
	case Inner = 'INNER JOIN';
	case Left  = 'LEFT JOIN';
	case Right = 'RIGHT JOIN';
	case Join  = 'JOIN';

	/** Returns the lowercase type string CI3's join() accepts as its 3rd param. */
	public function toCi3Type(): string
	{
		return match ($this) {
			self::Inner => 'inner',
			self::Left  => 'left',
			self::Right => 'right',
			self::Join  => '',
		};
	}
}

class MGR_Model_Dyn extends MY_Model
{
	/**
	 * Dynamic query with optional where clauses, joins, ordering, grouping, and limit.
	 *
	 * $where accepts two formats (mixable):
	 *   [MGR_Model_Dyn_clause::EQUAL => [...], MGR_Model_Dyn_clause::GROUP => [...]]
	 *   [[MGR_Model_Dyn_clause::GROUP => [...]], [MGR_Model_Dyn_clause::GROUP => [...]]]
	 * The list format allows the same clause kind to appear more than once
	 * (assoc keys are unique, so the first format is limited to one entry per kind).
	 *
	 * @param  string                $fields    Comma-separated select list. '' = SELECT *.
	 * @param  array                 $where     MGR_Model_Dyn_clause-keyed condition array.
	 * @param  MGR_Model_Dyn_join[]  $join      Array of MGR_Model_Dyn_join instances.
	 * @param  string|array          $limit     Row limit as string, e.g. '25'.
	 * @param  string                $order_by  ORDER BY expression. Never pass raw request input.
	 * @param  string                $group_by  GROUP BY expression. Never pass raw request input.
	 * @return array
	 * @throws InvalidArgumentException on unknown clause kinds or empty IN() lists.
	 */
	public function get_all_dynamic(string|array|null $fields = null, array $where = [], array $join = [], int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null): array
	{
		$this->apply_list_filters($fields, [], $limit, $order_by, $group_by);

		if (!empty($join)) {
			foreach ($join as $data) {
				$data->apply($this->my_db);
			}
		}

		foreach ($this->normalized_where($where) as [$kind, $data]) {
			switch ($kind) {
				case MGR_Model_Dyn_clause::GROUP:
					$this->my_db->group_start(); // Opens (
					foreach ($data as $inner_kind => $inner_fields) {
						$this->apply_where_condition($inner_kind, $inner_fields);
					}
					$this->my_db->group_end(); // Closes )
					break;

				case MGR_Model_Dyn_clause::OR_GROUP:
					$this->my_db->or_group_start(); // Opens OR (
					foreach ($data as $inner_kind => $inner_fields) {
						$this->apply_where_condition($inner_kind, $inner_fields);
					}
					$this->my_db->group_end();
					break;

				default:
					// Regular conditions without grouping
					$this->apply_where_condition($kind, $data);
					break;
			}
		}

		return $this->execute_list();
	}

	/**
	 * Build a join definition to pass into get_all_dynamic()'s $join parameter.
	 *
	 * @param  string                                              $table
	 * @param  MGR_Model_Dyn_join_type                             $type
	 * @param  array<MGR_Model_Dyn_clause::*, array<string, mixed>> $on
	 * @return MGR_Model_Dyn_join
	 */
	public function build_join(
		string $table,
		MGR_Model_Dyn_join_type $type,
		array $on
	): MGR_Model_Dyn_join {
		return new MGR_Model_Dyn_join(
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
	 * Normalize $where so the same clause kind may appear more than once.
	 * Mirrors MGR_Model_Dyn_join::_normalized_on() — see get_all_dynamic() docblock.
	 *
	 * @return iterable<array{0: string, 1: mixed}>
	 */
	protected function normalized_where(array $where): iterable
	{
		foreach ($where as $key => $value) {
			if (is_int($key)) {
				if (!is_array($value)) {
					throw new InvalidArgumentException(
						'MGR_Model_Dyn: list-style $where entries must be arrays of [kind => fields].'
					);
				}
				foreach ($value as $kind => $fields) {
					yield [$kind, $fields];
				}
			} else {
				yield [$key, $value];
			}
		}
	}

	/**
	 * Apply a where condition based on MGR_Model_Dyn_clause type.
	 *
	 * Values are escaped by CI3's query builder. EQUAL_COL maps to the same
	 * behavior as EQUAL here (where() escapes values); it exists for symmetry
	 * with the join builder, where the distinction is security-relevant.
	 *
	 * @throws InvalidArgumentException on unknown clause kinds or empty IN() lists.
	 */
	protected function apply_where_condition(string $kind, mixed $fields): void
	{
		switch ($kind) {
			case MGR_Model_Dyn_clause::EQUAL:
			case MGR_Model_Dyn_clause::EQUAL_COL:
				$this->my_db->where($fields);
				break;

			case MGR_Model_Dyn_clause::OR_EQUAL:
			case MGR_Model_Dyn_clause::OR_EQUAL_COL:
				$this->my_db->or_where($fields);
				break;

			case MGR_Model_Dyn_clause::LIKE:
				$this->my_db->like($fields);
				break;

			case MGR_Model_Dyn_clause::OR_LIKE:
				$this->my_db->or_like($fields);
				break;

			case MGR_Model_Dyn_clause::WHERE_IN:
				foreach ($fields as $field => $values) {
					if (empty($values)) {
						throw new InvalidArgumentException(
							"MGR_Model_Dyn: empty IN() list for column '{$field}'."
						);
					}
					$this->my_db->where_in($field, $values);
				}
				break;

			case MGR_Model_Dyn_clause::OR_WHERE_IN:
				foreach ($fields as $field => $values) {
					if (empty($values)) {
						throw new InvalidArgumentException(
							"MGR_Model_Dyn: empty IN() list for column '{$field}'."
						);
					}
					$this->my_db->or_where_in($field, $values);
				}
				break;

			default:
				throw new InvalidArgumentException(
					"MGR_Model_Dyn: unknown clause kind '{$kind}' — condition would be silently dropped."
				);
		}
	}
}
