<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Mgr_Driver_helper must be loaded before this file.
// In CI: $this->load->helper(['Mgr_Driver', 'Mgr_Function']);

// ---------------------------------------------------------------------------
// MgrFunctionType — every cross-engine SQL function this system knows about.
//
// Cases are grouped by category. Stubs (not yet implemented) are marked with
// @stub — they throw a LogicException if actually called, so you get a loud
// failure at dev time rather than silent wrong SQL.
//
// Each case is responsible for:
//   1. Declaring how many arguments it expects   → argCount()
//   2. Resolving itself to a SQL string fragment → resolve(MgrDriver, array)
// ---------------------------------------------------------------------------

enum MgrFunctionType: string
{
	// ── Date / Time ───────────────────────────────────────────────────────────

	/**
	 * Convert a Unix timestamp (integer) to a datetime string.
	 *
	 * Args: 1 — the column name or integer expression.
	 *
	 * | Engine      | Output SQL                              |
	 * |-------------|-----------------------------------------|
	 * | MySQL       | FROM_UNIXTIME(`col`)                    |
	 * | PostgreSQL  | TO_TIMESTAMP(col)                       |
	 * | SQL Server  | DATEADD(s, col, '19700101')             |
	 * | SQLite      | DATETIME(col, 'unixepoch')              |
	 */
	case FromUnixtime = 'FROM_UNIXTIME';

	/**
	 * Convert a datetime column to a Unix timestamp integer.
	 *
	 * Args: 1 — the column name or datetime expression.
	 *
	 * | Engine      | Output SQL                                      |
	 * |-------------|-------------------------------------------------|
	 * | MySQL       | UNIX_TIMESTAMP(`col`)                           |
	 * | PostgreSQL  | EXTRACT(EPOCH FROM col)::BIGINT                 |
	 * | SQL Server  | DATEDIFF(s, '19700101', col)                    |
	 * | SQLite      | STRFTIME('%s', col)                             |
	 */
	case ToUnixtime = 'TO_UNIXTIME';

	/**
	 * Current date+time (no args).
	 *
	 * | Engine      | Output SQL          |
	 * |-------------|---------------------|
	 * | MySQL       | NOW()               |
	 * | PostgreSQL  | NOW()               |
	 * | SQL Server  | GETDATE()           |
	 * | SQLite      | DATETIME('now')     |
	 */
	case Now = 'NOW';

	/**
	 * Format a date/datetime column into a string.
	 *
	 * Args: 2 — column, format string.
	 * Format uses MySQL-style tokens (%Y, %m, %d …).
	 * On non-MySQL engines, the format string is passed through as-is —
	 * callers must use the native engine format tokens when targeting
	 * non-MySQL engines, or pre-process the format themselves.
	 *
	 * | Engine      | Output SQL                         |
	 * |-------------|------------------------------------|
	 * | MySQL       | DATE_FORMAT(col, '%Y-%m-%d')       |
	 * | PostgreSQL  | TO_CHAR(col, '%Y-%m-%d')           |
	 * | SQL Server  | FORMAT(col, '%Y-%m-%d')            |
	 * | SQLite      | STRFTIME('%Y-%m-%d', col)          |
	 */
	case DateFormat = 'DATE_FORMAT';

	/**
	 * Difference between two dates in a given unit.
	 *
	 * Args: 3 — unit ('DAY'|'MONTH'|'YEAR'|'HOUR'|'MINUTE'|'SECOND'), date_start, date_end.
	 *
	 * @stub — not yet implemented, reserved for future use.
	 */
	case DateDiff = 'DATE_DIFF';

	// ── Math / Aggregate ─────────────────────────────────────────────────────

	/**
	 * Round a numeric value to N decimal places.
	 *
	 * Args: 2 — column, decimal places (integer, default 0).
	 *
	 * | Engine      | Output SQL        |
	 * |-------------|-------------------|
	 * | All         | ROUND(col, 2)     |
	 */
	case Round = 'ROUND';

	/**
	 * Floor (round down to integer).
	 *
	 * Args: 1 — column.
	 *
	 * | Engine      | Output SQL        |
	 * |-------------|-------------------|
	 * | MySQL/PgSQL | FLOOR(col)        |
	 * | SQL Server  | FLOOR(col)        |
	 * | SQLite      | CAST(col AS INT)  |
	 */
	case Floor = 'FLOOR';

	/**
	 * Ceiling (round up to integer).
	 *
	 * Args: 1 — column.
	 *
	 * | Engine      | Output SQL        |
	 * |-------------|-------------------|
	 * | MySQL/PgSQL | CEIL(col)         |
	 * | SQL Server  | CEILING(col)      |
	 * | SQLite      | CAST(col+0.9 …)   | (approximation — see note in resolver)
	 */
	case Ceil = 'CEIL';

	/**
	 * Absolute value.
	 *
	 * Args: 1 — column.
	 * All engines: ABS(col) — truly universal.
	 */
	case Abs = 'ABS';

	/**
	 * Cast a value to a target SQL type.
	 *
	 * Args: 2 — column, type-string (e.g. 'UNSIGNED', 'CHAR', 'DATE').
	 * The type string is passed through verbatim — the caller must supply
	 * the engine-appropriate type token.
	 *
	 * @stub — not yet implemented, reserved for future use.
	 */
	case Cast = 'CAST';

	/**
	 * Coalesce — return the first non-NULL value.
	 *
	 * Args: 2+ — any number of column/literal expressions.
	 * All engines: COALESCE(a, b, …) — truly universal.
	 */
	case Coalesce = 'COALESCE';

	// ── Introspection ─────────────────────────────────────────────────────────

	/**
	 * Expected argument count for each function.
	 * -1 = variadic (any number ≥ 1).
	 */
	public function argCount(): int
	{
		return match ($this) {
			self::Now                        => 0,
			self::FromUnixtime,
			self::ToUnixtime,
			self::Floor,
			self::Ceil,
			self::Abs                        => 1,
			self::Round,
			self::DateFormat,
			self::Cast                       => 2,
			self::DateDiff                   => 3,
			self::Coalesce                   => -1,   // variadic
		};
	}

	/**
	 * Returns true for cases that are declared but not yet implemented.
	 * MgrFunctionBuilder checks this before attempting to resolve.
	 */
	public function isStub(): bool
	{
		return match ($this) {
			self::DateDiff, self::Cast => true,
			default                    => false,
		};
	}

	// ── Resolution ────────────────────────────────────────────────────────────

	/**
	 * Resolve this function to a driver-specific SQL fragment.
	 * Called by MgrFunctionBuilder — do not call directly.
	 *
	 * @param  MgrDriver $driver
	 * @param  array     $args   Already-validated, raw arg strings.
	 * @return string
	 */
	public function resolve(MgrDriver $driver, array $args): string
	{
		return match ($this) {
			self::FromUnixtime => $this->_resolveFromUnixtime($driver, $args[0]),
			self::ToUnixtime   => $this->_resolveToUnixtime($driver, $args[0]),
			self::Now          => $this->_resolveNow($driver),
			self::DateFormat   => $this->_resolveDateFormat($driver, $args[0], $args[1]),
			self::Round        => "ROUND({$args[0]}, {$args[1]})",
			self::Floor        => $this->_resolveFloor($driver, $args[0]),
			self::Ceil         => $this->_resolveCeil($driver, $args[0]),
			self::Abs          => "ABS({$args[0]})",
			self::Coalesce     => 'COALESCE(' . implode(', ', $args) . ')',

			// Stubs — guarded earlier by MgrFunctionBuilder, but kept explicit.
			self::DateDiff,
			self::Cast => throw new LogicException(
				"MgrFunctionType::{$this->name} is not yet implemented."
			),
		};
	}

	// ── Private resolvers ─────────────────────────────────────────────────────

	private function _resolveFromUnixtime(MgrDriver $driver, string $col): string
	{
		return match ($driver) {
			MgrDriver::MySQL,
			MgrDriver::MariaDB  => "FROM_UNIXTIME({$col})",
			MgrDriver::Postgres => "TO_TIMESTAMP({$col})",
			MgrDriver::SQLServer => "DATEADD(s, {$col}, '19700101')",
			MgrDriver::SQLite   => "DATETIME({$col}, 'unixepoch')",
		};
	}

	private function _resolveToUnixtime(MgrDriver $driver, string $col): string
	{
		return match ($driver) {
			MgrDriver::MySQL,
			MgrDriver::MariaDB  => "UNIX_TIMESTAMP({$col})",
			MgrDriver::Postgres => "EXTRACT(EPOCH FROM {$col})::BIGINT",
			MgrDriver::SQLServer => "DATEDIFF(s, '19700101', {$col})",
			MgrDriver::SQLite   => "STRFTIME('%s', {$col})",
		};
	}

	private function _resolveNow(MgrDriver $driver): string
	{
		return match ($driver) {
			MgrDriver::MySQL,
			MgrDriver::MariaDB,
			MgrDriver::Postgres => 'NOW()',
			MgrDriver::SQLServer => 'GETDATE()',
			MgrDriver::SQLite   => "DATETIME('now')",
		};
	}

	private function _resolveDateFormat(MgrDriver $driver, string $col, string $format): string
	{
		return match ($driver) {
			MgrDriver::MySQL,
			MgrDriver::MariaDB  => "DATE_FORMAT({$col}, {$format})",
			MgrDriver::Postgres => "TO_CHAR({$col}, {$format})",
			MgrDriver::SQLServer => "FORMAT({$col}, {$format})",
			MgrDriver::SQLite   => "STRFTIME({$format}, {$col})",   // SQLite swaps arg order
		};
	}

	private function _resolveFloor(MgrDriver $driver, string $col): string
	{
		return match ($driver) {
			MgrDriver::SQLite => "CAST({$col} AS INTEGER)",
			default           => "FLOOR({$col})",
		};
	}

	private function _resolveCeil(MgrDriver $driver, string $col): string
	{
		return match ($driver) {
			// SQLite has no CEIL — smallest integer >= col
			// CAST truncates toward zero, so for positive: CAST(col + 0.999… AS INT) works,
			// but the standard idiom is: -CAST(-(col) AS INTEGER) which handles negatives too.
			MgrDriver::SQLite   => "-CAST(-({$col}) AS INTEGER)",
			MgrDriver::SQLServer => "CEILING({$col})",
			default             => "CEIL({$col})",
		};
	}
}


// ---------------------------------------------------------------------------
// MgrFunctionBuilder — validates args and delegates to MgrFunctionType::resolve().
//
// Separating this from the enum keeps validation logic in one place and makes
// it easy to extend (e.g. add raw-expression wrapping, alias support, etc.)
// without touching the enum cases.
// ---------------------------------------------------------------------------

final class MgrFunctionBuilder
{
	/**
	 * @param MgrFunctionType $function
	 * @param MgrDriver       $driver
	 * @param array           $args     Raw SQL expressions/column names as strings.
	 */
	public function __construct(
		private readonly MgrFunctionType $function,
		private readonly MgrDriver       $driver,
		private readonly array           $args = [],
	) {
		$this->_validate();
	}

	// ── Validate ─────────────────────────────────────────────────────────────

	private function _validate(): void
	{
		if ($this->function->isStub()) {
			throw new LogicException(
				"MgrFunctionType::{$this->function->name} is a stub and has not been implemented yet."
			);
		}

		$expected = $this->function->argCount();
		$actual   = count($this->args);

		if ($expected === -1 && $actual < 1) {
			throw new InvalidArgumentException(
				"MgrFunctionBuilder: {$this->function->name} requires at least 1 argument, 0 given."
			);
		}

		if ($expected >= 0 && $actual !== $expected) {
			throw new InvalidArgumentException(
				"MgrFunctionBuilder: {$this->function->name} expects {$expected} argument(s), {$actual} given."
			);
		}

		foreach ($this->args as $i => $arg) {
			if (!is_string($arg) || trim($arg) === '') {
				throw new InvalidArgumentException(
					"MgrFunctionBuilder: argument {$i} must be a non-empty string (SQL expression or column name)."
				);
			}
		}
	}

	// ── Build ─────────────────────────────────────────────────────────────────

	/**
	 * Returns the driver-specific SQL fragment.
	 *
	 * @return string  e.g. "FROM_UNIXTIME(`created_at`)"
	 */
	public function build(): string
	{
		return $this->function->resolve($this->driver, $this->args);
	}
}


// ---------------------------------------------------------------------------
// mgr_build_function() — procedural entry point.
//
// Convenience wrapper so the base model only needs one call.
//
// Usage (inside a CI model that extends Mgr_Model):
//
//   $sql = mgr_build_function(
//       function : MgrFunctionType::FromUnixtime,
//       driver   : MgrDriver::fromCI($this->db->dbdriver),
//       args     : ['created_at'],
//   );
//   // → "FROM_UNIXTIME(created_at)"  on MySQL
//   // → "TO_TIMESTAMP(created_at)"   on PostgreSQL
//
// ---------------------------------------------------------------------------

function mgr_build_function(
	MgrFunctionType $function,
	MgrDriver       $driver,
	array           $args = []
): string {
	return (new MgrFunctionBuilder($function, $driver, $args))->build();
}

function mgr_build_field_select(
	string          $name,
	MgrFunctionType $function,
	MgrDriver       $driver,
	array           $args = [],
): string {
	$sql = (new MgrFunctionBuilder($function, $driver, $args))->build();
	return "{$sql} AS {$name}";
}
