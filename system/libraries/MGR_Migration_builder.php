<?php

defined('BASEPATH') or exit('No direct script access allowed');

// ---------------------------------------------------------------------------
// MgrFieldType — all valid cross-engine column types.
// Backed enum (string) so IDEs autocomplete MgrFieldType::VarChar etc.
// and invalid types are impossible to construct.
// ---------------------------------------------------------------------------

enum MgrFieldType: string
{
	// ── Integers ─────────────────────────────────────────────────────────────
	/**
	 * 1-byte int. MySQL: TINYINT. PostgreSQL: SMALLINT (no native TINYINT).
	 * SQL Server: TINYINT is unsigned-only (0-255) — a negative value throws a range error on
	 * write, so avoid negative values if the column needs to stay portable to SQL Server.
	 */
	case TinyInt	 = 'TINYINT';
	/** 2-byte int. */
	case SmallInt	= 'SMALLINT';
	/** 4-byte int — most common. */
	case Int		  = 'INT';
	/**
	 * 8-byte int. SQL Server has no wider integer type — `unsigned: true` has no effect
	 * there (stays a signed BIGINT). Postgres widens to DECIMAL (arbitrary precision).
	 */
	case BigInt	  = 'BIGINT';

	// ── Decimals ─────────────────────────────────────────────────────────────
	/**
	 * Exact decimal. Use $precision + $scale params on field().
	 * No wider type exists on Postgres or SQL Server — `unsigned: true` has no effect on
	 * either engine (stays a signed-range DECIMAL).
	 */
	case Decimal	 = 'DECIMAL';
	/**
	 * 4-byte float. MySQL/MariaDB: FLOAT. PostgreSQL: REAL. SQL Server: FLOAT(24).
	 * SQLite has no 4-byte type — stores at its usual 8-byte REAL affinity regardless.
	 */
	case Float		= 'FLOAT';
	/** 8-byte float. MySQL: DOUBLE. PostgreSQL: DOUBLE PRECISION. */
	case Double	  = 'DOUBLE';

	// ── Strings ──────────────────────────────────────────────────────────────
	/** Fixed-length string. Requires $constraint. */
	case Char		 = 'CHAR';
	/** Variable-length string. Requires $constraint. Max 65,535 bytes MySQL; 1GB PgSQL. */
	case VarChar	 = 'VARCHAR';
	/** Unlimited text. */
	case Text		 = 'TEXT';
	/** MySQL: MEDIUMTEXT (~16MB). PostgreSQL/others: TEXT. */
	case MediumText = 'MEDIUMTEXT';
	/** MySQL: LONGTEXT (~4GB). PostgreSQL/others: TEXT. */
	case LongText	= 'LONGTEXT';

	// ── Binary ───────────────────────────────────────────────────────────────
	/**
	 * Binary blob.
	 * MySQL: BLOB. PostgreSQL: BYTEA. SQLite: BLOB. SQL Server: VARBINARY(MAX).
	 */
	case Blob		 = 'BLOB';
	/** MySQL: MEDIUMBLOB. PostgreSQL: BYTEA. Others: largest binary equivalent. */
	case MediumBlob = 'MEDIUMBLOB';
	/** MySQL: LONGBLOB. PostgreSQL: BYTEA. Others: largest binary equivalent. */
	case LongBlob	= 'LONGBLOB';

	// ── Boolean ───────────────────────────────────────────────────────────────
	/**
	 * Boolean.
	 * MySQL/MariaDB: TINYINT(1). PostgreSQL: BOOLEAN. SQLite: INTEGER. SQL Server: BIT.
	 */
	case Bool		 = 'BOOL';

	// ── Date / Time ───────────────────────────────────────────────────────────
	/** Date only (YYYY-MM-DD). */
	case Date		 = 'DATE';
	/** Time only (HH:MM:SS). */
	case Time		 = 'TIME';
	/** Date + time, no timezone. MySQL: DATETIME. PostgreSQL: TIMESTAMP. */
	case DateTime	= 'DATETIME';
	/** Timestamp. MySQL: TIMESTAMP. PostgreSQL: TIMESTAMPTZ. SQL Server: DATETIMEOFFSET. */
	case Timestamp  = 'TIMESTAMP';
	/** Year only. MySQL: YEAR. PostgreSQL/SQL Server: SMALLINT. SQLite: INTEGER. */
	case Year		 = 'YEAR';

	// ── JSON ──────────────────────────────────────────────────────────────────
	/**
	 * JSON document.
	 * MySQL 8+/MariaDB: JSON. PostgreSQL: JSONB (binary, indexed). Others: text fallback.
	 */
	case Json		 = 'JSON';

	// ── UUID ──────────────────────────────────────────────────────────────────
	/**
	 * UUID/GUID.
	 * MySQL/MariaDB: CHAR(36). PostgreSQL: UUID (native). SQL Server: UNIQUEIDENTIFIER. SQLite: TEXT.
	 * PostgreSQL lowercases on read (MySQL/MariaDB round-trips case); SQL Server sorts by
	 * mixed-endian byte order, not lexicographically. Neither has a schema-level fix — normalize
	 * at the caller if cross-engine-identical comparison or ordering is required.
	 */
	case Uuid		 = 'UUID';

	// ── Enum ──────────────────────────────────────────────────────────────────
	/**
	 * Enumerated values.
	 * MySQL/MariaDB: native ENUM('a','b',...). PostgreSQL/SQLite: VARCHAR(max_len). SQL Server: NVARCHAR(max_len).
	 * Requires $enum_values param on field().
	 */
	case Enum		 = 'ENUM';

	// ── Helpers ───────────────────────────────────────────────────────────────

	/** Types that support UNSIGNED (integers + decimals only). */
	public function supportsUnsigned(): bool
	{
		return match ($this) {
			self::TinyInt, self::SmallInt, self::Int, self::BigInt,
			self::Decimal, self::Float, self::Double => true,
			default => false,
		};
	}

	/** Types that support AUTO_INCREMENT. */
	public function supportsAutoIncrement(): bool
	{
		return match ($this) {
			self::TinyInt, self::SmallInt, self::Int, self::BigInt => true,
			default => false,
		};
	}
}

// ---------------------------------------------------------------------------
// MgrFieldDefault — sentinel values for the field() default parameter.
// NotSet means "no DEFAULT clause" — '' and null are both valid column defaults.
// ---------------------------------------------------------------------------

enum MgrFieldDefault
{
	case NotSet;
}


// ---------------------------------------------------------------------------
// MgrFieldBuilder — internal. Resolves a CI dbforge-compatible field array.
// Not used directly — instantiated by Mgr_Migration::field().
// ---------------------------------------------------------------------------

final class MgrFieldBuilder
{
	/**
	 * @param string		$name
	 * @param MgrFieldType  $type
	 * @param MgrDriver		$driver			 Injected from MGR_Migration_builder — computed once per migration
	 * @param int|null	 	$constraint
	 * @param bool			$unsigned		  Passed to dbforge; PostgreSQL upsizes the type instead of UNSIGNED
	 * @param bool|null		$nullable		  true=NULL, false=NOT NULL, null=CI default
	 * @param bool			$unique
	 * @param bool			$auto_increment
	 * @param mixed		  	$default			Scalar or null (DEFAULT NULL). Omit for no default.
	 * @param string|null  	$new_name		  For modify_column renames
	 * @param int|null	  	$precision		 DECIMAL total digits
	 * @param int			$scale			  DECIMAL digits after decimal point
	 * @param mixed[]	  	$enum_values	  Required for MgrFieldType::Enum
	 */
	public function __construct(
		protected readonly string		$name,
		protected readonly MgrFieldType $type,
		protected readonly MgrDriver	$driver,
		protected readonly ?int			$constraint		= null,
		protected readonly bool			$unsigned		= false,
		protected readonly ?bool		$nullable		= null,
		protected readonly bool			$unique			= false,
		protected readonly bool			$auto_increment = false,
		protected readonly mixed		$default		= MgrFieldDefault::NotSet,
		protected readonly ?string		$new_name		= null,
		protected readonly ?int			$precision		= null,
		protected readonly int			$scale			= 0,
		protected readonly array		$enum_values	= [],
	) {
		$this->_validate();
	}

	// ── Validation ───────────────────────────────────────────────────────────

	protected function _validate(): void
	{
		if (trim($this->name) === '') {
			throw new InvalidArgumentException("MgrFieldBuilder: field name cannot be empty.");
		}
		if ($this->unsigned && !$this->type->supportsUnsigned()) {
			throw new InvalidArgumentException(
				"MgrFieldBuilder: unsigned is not valid for type {$this->type->value}."
			);
		}
		if ($this->auto_increment && !$this->type->supportsAutoIncrement()) {
			throw new InvalidArgumentException(
				"MgrFieldBuilder: auto_increment is only valid on integer types, got {$this->type->value}."
			);
		}

		$valid_default = $this->default === MgrFieldDefault::NotSet
			|| $this->default === null
			|| is_scalar($this->default);

		if (!$valid_default) {
			throw new InvalidArgumentException("MgrFieldBuilder: default must be scalar or null.");
		}

		switch ($this->type) {

			case MgrFieldType::Enum:
				if (empty($this->enum_values)) {
					throw new InvalidArgumentException(
						"MgrFieldBuilder: MgrFieldType::Enum requires the enum_values parameter."
					);
				}
				foreach ($this->enum_values as $v) {
					if (!is_string($v)) {
						throw new InvalidArgumentException(
							"MgrFieldBuilder: all enum_values must be strings."
						);
					}
				}
				break;

			case MgrFieldType::Decimal:
				if ($this->precision === null && $this->constraint === null) {
					throw new InvalidArgumentException(
						"MgrFieldBuilder: MgrFieldType::Decimal requires the precision parameter."
					);
				}
				break;

			case MgrFieldType::Bool:
				if (
					$this->default !== MgrFieldDefault::NotSet
					&& $this->default !== null
					&& filter_var($this->default, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null
				) {
					throw new InvalidArgumentException(
						"MgrFieldBuilder: Bool default must be a boolean-like value (true/false/1/0), got '{$this->default}'."
					);
				}
				break;
		}
	}

	// ── Build ────────────────────────────────────────────────────────────────

	/** Produce the CI dbforge-compatible field array. */
	public function build(): array
	{
		['type' => $type, 'constraint' => $constraint, 'default' => $default, 'unsigned' => $unsigned] = $this->_resolveColumn();

		$field = ['type' => $type];

		if ($constraint !== '') {
			$field['constraint'] = $constraint;
		}
		if ($unsigned) {
			$field['unsigned'] = $unsigned;
		}
		if ($this->nullable !== null) {
			$field['null'] = $this->nullable;
		}
		if ($this->unique) {
			$field['unique'] = true;
		}
		if ($default !== MgrFieldDefault::NotSet) {
			$field['default'] = $default;
		}
		if ($this->auto_increment) {
			$field['auto_increment'] = true;
		}
		if ($this->new_name !== null) {
			$field['name'] = $this->new_name;
		}

		return [$this->name => $field];
	}

	// ── Type resolution & cross-engine translation ───────────────────────────

	/**
	 *  Type			 │ MySQL/MariaDB		 │ PostgreSQL			  │ SQL Server			 │ SQLite
	 * ───────────────┼─────────────────────┼──────────────────────┼─────────────────────┼──────────
	 *  Bool			 │ TINYINT(1)			 │ BOOLEAN				  │ BIT					  │ INTEGER
	 *  TinyInt		 │ TINYINT				 │ SMALLINT				 │ TINYINT				 │ INTEGER
	 *  Blob*			│ BLOB/MED/LONG		 │ BYTEA					 │ VARBINARY(MAX)		│ BLOB
	 *  Json			 │ JSON					 │ JSONB					 │ NVARCHAR(MAX)		 │ TEXT
	 *  Date			 │ DATE					 │ DATE					  │ DATE					  │ TEXT
	 *  Time			 │ TIME					 │ TIME					  │ TIME					  │ TEXT
	 *  DateTime		│ DATETIME				│ TIMESTAMP				│ DATETIME2			  │ TEXT
	 *  Timestamp		│ TIMESTAMP		│ TIMESTAMPTZ		│ DATETIMEOFFSET	│ TEXT
	 *  Float			 │ FLOAT					 │ REAL					  │ FLOAT(24)			  │ FLOAT
	 *  Double		  │ DOUBLE				  │ DOUBLE PRECISION	  │ FLOAT					│ DOUBLE
	 *  Text			 │ TEXT					 │ TEXT					  │ NVARCHAR(MAX)		 │ TEXT
	 *  MediumText	 │ MEDIUMTEXT			 │ TEXT					  │ NVARCHAR(MAX)		 │ TEXT
	 *  LongText		│ LONGTEXT				│ TEXT					  │ NVARCHAR(MAX)		 │ TEXT
	 *  Year			 │ YEAR					 │ SMALLINT				 │ SMALLINT				│ INTEGER
	 *  Uuid			 │ CHAR(36)				│ UUID					  │ UNIQUEIDENTIFIER	 │ TEXT
	 *  Enum			 │ ENUM('a','b',…)	  │ VARCHAR(max_len)	  │ NVARCHAR(max_len)	│ TEXT
	 *  UNSIGNED		│ supported			  │ widens (SmallInt/Int/BigInt/Float; Decimal capped) │ widens (SmallInt/Int/Float; BigInt/Decimal capped) │ ignored
	 */

	/**
	 * Cross-engine column translation: type, constraint, and default.
	 *
	 * Defaults pass through untouched (including MgrFieldDefault::NotSet)
	 * unless the type needs per-driver translation — Bool defaults become
	 * real PHP bools so each CI driver escapes them natively:
	 * MySQL/SQLite → 1/0, PostgreSQL → TRUE/FALSE, SQL Server BIT → 1/0.
	 *
	 * @return array{type: string, constraint: string, default: mixed, unsigned: bool}
	 */
	protected function _resolveColumn(): array
	{
		$type	  = $this->type;
		$unsigned = $this->unsigned;

		// Postgres/SQL Server have no UNSIGNED keyword, and CI3's own vendored widening for
		// both is a no-op (upstream _attr_unsigned() bug) — honored here instead by re-dispatching
		// to the next-widest MgrFieldType. Read $type below, never $this->type — a case added
		// later must see the widened value.
		if ($unsigned && ($this->driver === MgrDriver::Postgres || $this->driver === MgrDriver::SQLServer)) {
			$widened = match ($type) {
				MgrFieldType::SmallInt => MgrFieldType::Int,
				MgrFieldType::Int	   => MgrFieldType::BigInt,
				MgrFieldType::Float	  => MgrFieldType::Double,
				MgrFieldType::BigInt	 => $this->driver === MgrDriver::Postgres ? MgrFieldType::Decimal : null,
				default					  => null,
			};
			if ($widened !== null) {
				$type	  = $widened;
				$unsigned = false;
			}
		}

		$type_value = $type->value;
		$constraint	= $this->constraint !== null ? (string) $this->constraint : '';
		$default  = $this->default;

		switch ($type) {

			case MgrFieldType::Bool:
				if ($default !== MgrFieldDefault::NotSet && $default !== null) {
					$default = filter_var($default, FILTER_VALIDATE_BOOLEAN);
				}
				[$type_value, $constraint] = match ($this->driver) {
					MgrDriver::Postgres				  => ['BOOLEAN', ''],
					MgrDriver::SQLServer			 => ['BIT',	  ''],
					MgrDriver::SQLite				 => ['INTEGER', ''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB				  => ['TINYINT', '1'],
				};
				break;

			case MgrFieldType::TinyInt:
				[$type_value, $constraint] = match ($this->driver) {
					MgrDriver::Postgres				  => ['SMALLINT', ''],
					MgrDriver::SQLite					 => ['INTEGER',  ''],
					default								  => ['TINYINT',  $constraint],
				};
				break;

			case MgrFieldType::Blob:
			case MgrFieldType::MediumBlob:
			case MgrFieldType::LongBlob:
				[$type_value, $constraint] = match ($this->driver) {
					MgrDriver::Postgres				  => ['BYTEA',			 ''],
					MgrDriver::SQLServer				 => ['VARBINARY(MAX)', ''],
					MgrDriver::SQLite					 => ['BLOB',			  ''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB					  => [$type_value, ''],
				};
				break;

			case MgrFieldType::Json:
				[$type_value, $constraint] = match ($this->driver) {
					MgrDriver::Postgres				  => ['JSONB',			''],
					MgrDriver::SQLServer				 => ['NVARCHAR(MAX)', ''],
					MgrDriver::SQLite					 => ['TEXT',			 ''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB					  => ['JSON',			 ''],
				};
				break;

			case MgrFieldType::Date:
			case MgrFieldType::Time:
				[$type_value, $constraint] = match ($this->driver) {
					MgrDriver::SQLite					 => ['TEXT',			 ''],
					default								  => [$type_value, ''],
				};
				break;

			case MgrFieldType::DateTime:
				[$type_value, $constraint] = match ($this->driver) {
					MgrDriver::Postgres				  => ['TIMESTAMP', ''],
					MgrDriver::SQLServer				 => ['DATETIME2', ''],
					MgrDriver::SQLite					 => ['TEXT',		''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB					  => ['DATETIME',  ''],
				};
				break;

			case MgrFieldType::Float:
				[$type_value, $constraint] = match ($this->driver) {
					MgrDriver::Postgres				  => ['REAL',	 ''],
					MgrDriver::SQLServer				 => ['FLOAT',	 '24'],
					default								  => ['FLOAT',	 ''],
				};
				break;

			case MgrFieldType::Double:
				[$type_value, $constraint] = match ($this->driver) {
					MgrDriver::Postgres				  => ['DOUBLE PRECISION', ''],
					MgrDriver::SQLServer				 => ['FLOAT',				''],
					default								  => ['DOUBLE',			  ''],
				};
				break;

			case MgrFieldType::Text:
			case MgrFieldType::MediumText:
			case MgrFieldType::LongText:
				[$type_value, $constraint] = match ($this->driver) {
					MgrDriver::Postgres				  => ['TEXT',			 ''],
					MgrDriver::SQLServer				 => ['NVARCHAR(MAX)', ''],
					MgrDriver::SQLite					 => ['TEXT',			 ''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB					  => [$type_value, ''],
				};
				break;

			case MgrFieldType::Year:
				[$type_value, $constraint] = match ($this->driver) {
					MgrDriver::Postgres, MgrDriver::SQLServer => ['SMALLINT', ''],
					MgrDriver::SQLite								 => ['INTEGER',  ''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB							 => ['YEAR',	  ''],
				};
				break;

			case MgrFieldType::Uuid:
				[$type_value, $constraint] = match ($this->driver) {
					MgrDriver::Postgres				  => ['UUID',				  ''],
					MgrDriver::SQLServer				 => ['UNIQUEIDENTIFIER',  ''],
					MgrDriver::SQLite					 => ['TEXT',				  ''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB					  => ['CHAR',			 '36'],
				};
				break;

			case MgrFieldType::Decimal:
				$constraint = $this->precision !== null
					? "{$this->precision},{$this->scale}"
					: $constraint;
				break;

			case MgrFieldType::Timestamp:
				[$type_value, $constraint] = match ($this->driver) {
					MgrDriver::Postgres				  => ['TIMESTAMPTZ', ''],
					MgrDriver::SQLServer				 => ['DATETIMEOFFSET', ''],
					MgrDriver::SQLite					 => ['TEXT',		 ''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB					  => [$type_value, ''],
				};
				break;

			case MgrFieldType::Enum:
				if ($this->driver->isMysqlFamily()) {
					$quoted = array_map(
						static fn(string $v): string => "'" . str_replace(['\\', "'"], ['\\\\', "''"], $v) . "'",
						$this->enum_values
					);
					$type_value = 'ENUM(' . implode(',', $quoted) . ')';
					$constraint = '';
				} else {
					$max	 = max(array_map('strlen', $this->enum_values));
					$type_value = ($this->driver === MgrDriver::SQLServer) ? 'NVARCHAR' : 'VARCHAR';
					$constraint = (string) max($max, 1);
				}
				break;
		}

		return ['type' => $type_value, 'constraint' => $constraint, 'default' => $default, 'unsigned' => $unsigned];
	}
}


// ---------------------------------------------------------------------------
// MGR_Migration_builder — base class. Extend this instead of CI_Migration.
// ---------------------------------------------------------------------------

class MGR_Migration_builder
{
	/** Detected once per migration instance — all field() calls reuse this. */
	protected MgrDriver $db_driver;

	/** Allowed FK referential actions — kept to the vocabulary every engine understands. */
	protected const FK_ACTIONS = ['RESTRICT', 'CASCADE', 'SET NULL', 'SET DEFAULT', 'NO ACTION'];

	public function __construct()
	{
		$CI = &get_instance();
		$this->db_driver = MgrDriver::fromCI($CI->db->dbdriver ?? '', subdriver: $CI->db->subdriver ?? null);
	}

	// ── Field factory ────────────────────────────────────────────────────────

	/**
	 * Build a CI dbforge-compatible field array using named parameters.
	 *
	 * Examples:
	 *
	 *	// Basic string column
	 *	$this->field(name: 'email', type: MgrFieldType::VarChar, constraint: 191, nullable: false, unique: true)
	 *
	 *	// Unsigned integer with default
	 *	$this->field(name: 'score', type: MgrFieldType::Int, unsigned: true, default: 0)
	 *
	 *	// Exact decimal
	 *	$this->field(name: 'price', type: MgrFieldType::Decimal, precision: 10, scale: 2, nullable: false)
	 *
	 *	// Enum (MySQL: native ENUM, others: VARCHAR)
	 *	$this->field(name: 'status', type: MgrFieldType::Enum, enum_values: ['active', 'inactive'], default: 'active')
	 *
	 *	// JSON (PostgreSQL gets JSONB automatically)
	 *	$this->field(name: 'meta', type: MgrFieldType::Json, nullable: true)
	 *
	 *	// Rename column (use in modify_column)
	 *	$this->field(name: 'old_col', type: MgrFieldType::VarChar, constraint: 100, new_name: 'new_col')
	 *
	 * @param  string		 $name
	 * @param  MgrFieldType $type
	 * @param  int|null	  $constraint			 For CHAR, VARCHAR, etc.
	 * @param  bool			$unsigned		  Integers only. PostgreSQL upsizes the type instead of UNSIGNED.
	 * @param  bool|null	 $nullable		  true=NULL | false=NOT NULL | null=CI default
	 * @param  bool			$unique
	 * @param  bool			$auto_increment
	 * @param  mixed		  $default			Scalar or null (DEFAULT NULL). Omit for no default.
	 * @param  string|null  $new_name		  For modify_column renames only.
	 * @param  int|null	  $precision		 DECIMAL: total significant digits.
	 * @param  int			 $scale			  DECIMAL: digits after decimal point. Default 0.
	 * @param  string[]	  $enum_values	  Required when type is MgrFieldType::Enum.
	 * @return array
	 */
	protected function field(
		string		 $name,
		MgrFieldType $type,
		?int			$constraint			= null,
		bool			$unsigned		 = false,
		?bool		  $nullable		 = null,
		bool			$unique			= false,
		bool			$auto_increment = false,
		mixed		  $default		  = MgrFieldDefault::NotSet,
		?string		$new_name		 = null,
		?int			$precision		= null,
		int			 $scale			 = 0,
		array		  $enum_values	 = [],
	): array {
		return (new MgrFieldBuilder(
			name: $name,
			type: $type,
			driver: $this->db_driver,
			constraint: $constraint,
			unsigned: $unsigned,
			nullable: $nullable,
			unique: $unique,
			auto_increment: $auto_increment,
			default: $default,
			new_name: $new_name,
			precision: $precision,
			scale: $scale,
			enum_values: $enum_values,
		))->build();
	}

	// ── Shorthands ───────────────────────────────────────────────────────────

	/**
	 * Standard integer primary key with auto-increment.
	 * CI/dbforge translates AUTO_INCREMENT → SERIAL/IDENTITY per driver.
	 *
	 * @param string $name Default 'id'
	 */
	protected function field_id(string $name = 'id'): array
	{
		return $this->field(
			name: $name,
			type: MgrFieldType::Int,
			unsigned: true,
			nullable: false,
			auto_increment: true,
		);
	}

	/**
	 * Standard create_date + last_update timestamp columns.
	 *
	 * Frozen — every call site is an already-applied migration, so changing these
	 * specs would silently change what it produces on a fresh install. Declare both
	 * fields explicitly in new migrations instead of calling this.
	 *
	 * @return array[]  Two field arrays — spread into add_field() calls or loop them.
	 */
	protected function field_timestamps(): array
	{
		return [
			...$this->field(
				name: 'create_date',
				type: MgrFieldType::Timestamp,
				nullable: false
			),
			...$this->field(
				name: 'last_update',
				type: MgrFieldType::Timestamp,
				nullable: true
			),
		];
	}

	/**
	 * Declaratively sets a timestamp column's default and on-update trigger/modifier —
	 * each flag independently reflects the column's end state, not a delta from
	 * whatever an earlier call left behind.
	 *
	 * @param  string $table	 Table name
	 * @param  string $column	 Column name. Default: 'last_update'
	 * @param  bool $on_update	 Auto-set the column to the current timestamp on every UPDATE
	 * @param  bool $default	 Default the column to the current timestamp on INSERT
	 * @return void
	 * @throws RuntimeException if $default is true on SQLite — it has no ALTER COLUMN;
	 *   setting a default on an existing column needs the recreate-table procedure,
	 *   which this builder does not implement.
	 */
	protected function modify_field_timestamp(string $table, string $column = 'last_update', bool $on_update = true, bool $default = true): void
	{
		$table_ident  = $this->db->escape_identifiers($table);
		$column_ident = $this->db->escape_identifiers($column);

		match ($this->db_driver) {
			MgrDriver::Postgres => (function () use ($table_ident, $column_ident, $table, $column, $on_update, $default) {
				$this->db->query($default
					? "ALTER TABLE {$table_ident} ALTER COLUMN {$column_ident} SET DEFAULT CURRENT_TIMESTAMP;"
					: "ALTER TABLE {$table_ident} ALTER COLUMN {$column_ident} DROP DEFAULT;");

				// One statement per query(): a PDO connection in extended mode rejects multi-command SQL.
				$this->db->query("DROP TRIGGER IF EXISTS trg_{$table}_{$column} ON {$table_ident};");

				if (!$on_update) {
					$this->db->query("DROP FUNCTION IF EXISTS set_{$table}_{$column}();");
					return;
				}

				$this->db->query("CREATE OR REPLACE FUNCTION set_{$table}_{$column}()
                RETURNS TRIGGER AS $$
                BEGIN
                     NEW.{$column} := NOW();
                     RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;");

				$this->db->query("CREATE TRIGGER trg_{$table}_{$column}
                BEFORE UPDATE ON {$table_ident}
                FOR EACH ROW
                EXECUTE FUNCTION set_{$table}_{$column}();");
			})(),

			MgrDriver::MySQL,
			MgrDriver::MariaDB => (function () use ($table, $column, $on_update, $default) {
				$clause = ($default ? ' DEFAULT CURRENT_TIMESTAMP' : '') . ($on_update ? ' ON UPDATE CURRENT_TIMESTAMP' : '');
				$this->db->query("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` TIMESTAMP{$clause}");
			})(),

			MgrDriver::SQLServer => (function () use ($table_ident, $column_ident, $table, $column, $default) {
				$constraint_ident = $this->db->escape_identifiers("df_{$table}_{$column}");

				$this->db->query("IF EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = 'df_{$table}_{$column}')
                ALTER TABLE {$table_ident} DROP CONSTRAINT {$constraint_ident};");

				if ($default) {
					$this->db->query("ALTER TABLE {$table_ident} ADD CONSTRAINT {$constraint_ident} DEFAULT (SYSDATETIMEOFFSET()) FOR {$column_ident};");
				}
			})(),

			// SQLite has no ALTER COLUMN. The on-update trigger stays unimplemented;
			MgrDriver::SQLite => (function () use ($default) {
				if ($default) {
					throw new RuntimeException(
						'MGR_Migration_builder::modify_field_timestamp(): SQLite has no ALTER COLUMN — '
							. 'setting a default on an existing column needs the recreate-table procedure, '
							. 'which this builder does not implement.'
					);
				}
			})(),
		};
	}

	/**
	 * Changes one column's type, the same as `$this->dbforge->modify_column()`,
	 * with the `USING` cast CI3's Postgres forge omits. Every other attribute on
	 * the column (null, default, rename) is delegated to `$this->dbforge->modify_column()`.
	 *
	 * @param  array<string, array<string, mixed>> $field  One `field()` call's return value, passed directly (no spread)
	 * @return void
	 * @throws InvalidArgumentException if $field isn't exactly one column
	 */
	protected function modify_column_cast(string $table, array $field): void
	{
		if (count($field) !== 1) {
			throw new InvalidArgumentException(
				'MGR_Migration_builder::modify_column_cast(): expects exactly one column — call this once per '
					. 'casted column and $this->dbforge->modify_column() separately for the ones that do not need one.'
			);
		}

		if ($this->db_driver !== MgrDriver::Postgres) {
			$this->dbforge->modify_column($table, $field);
			return;
		}

		$column       = array_key_first($field);
		$attributes   = $field[$column];
		$table_ident  = $this->db->escape_identifiers($table);
		$column_ident = $this->db->escape_identifiers($column);

		if (isset($attributes['type'])) {
			$type = $attributes['type'] . (isset($attributes['constraint']) ? "({$attributes['constraint']})" : '');
			// Cast to the unconstrained type and let the TYPE clause apply the length: an explicit
			// cast to VARCHAR(n)/CHAR(n) truncates an overlong value silently, where the column type
			// alone rejects it. Bare CHAR is CHAR(1) in Postgres, so TEXT stands in as its base.
			$cast_type = $attributes['type'] === 'CHAR' ? 'TEXT' : $attributes['type'];
			$this->db->query(
				"ALTER TABLE {$table_ident} ALTER COLUMN {$column_ident} TYPE {$type} "
					. "USING {$column_ident}::{$cast_type}"
			);
		}

		// 'unsigned' never reaches Postgres DDL — nothing to delegate.
		$rest = array_diff_key($attributes, array_flip(['type', 'constraint', 'unsigned']));
		if (!empty($rest)) {
			$this->dbforge->modify_column($table, [$column => $rest]);
		}
	}

	/**
	 * Adds an index to an existing table.
	 *
	 * @param  string $table   Table name
	 * @param  array|string $columns  Column(s) to index
	 * @param  bool $unique   Whether the index is unique
	 * @param  array<string,mixed> $prefix_lengths  Column => key-prefix length (MySQL/MariaDB's InnoDB
	 *         key-size limit). PostgreSQL: `left()` expression index. SQLite: ignored. SQL Server: throws.
	 * @param  string|null $name  Exact index name to use, bypassing the derived name — needed to
	 *         line up with an index created outside this method (`dbforge->add_key()`, hand-run DDL)
	 * @return void
	 * @throws InvalidArgumentException if a $prefix_lengths key isn't in $columns, or a value isn't a positive int
	 * @throws RuntimeException if $prefix_lengths is non-empty on SQL Server
	 */
	protected function add_index(
		string $table,
		array|string $columns,
		bool $unique = false,
		array $prefix_lengths = [],
		?string $name = null,
	): void {
		$columns     = (array)$columns;
		$index_name  = $name ?? $this->_index_name($table, $columns);
		$unique_sql  = $unique ? 'UNIQUE ' : '';

		foreach ($prefix_lengths as $column => $length) {
			if (!in_array($column, $columns, true)) {
				throw new InvalidArgumentException(
					"MGR_Migration_builder::add_index(): prefix_lengths key '{$column}' is not one of the indexed columns."
				);
			}
			if (!is_int($length) || $length < 1) {
				throw new InvalidArgumentException(
					"MGR_Migration_builder::add_index(): prefix_lengths['{$column}'] must be a positive int, got "
						. var_export($length, true) . '.'
				);
			}
		}

		if (!empty($prefix_lengths) && $this->db_driver === MgrDriver::SQLServer) {
			throw new RuntimeException(
				"MGR_Migration_builder::add_index(): SQL Server cannot index by key prefix — "
					. "index '{$index_name}' on '{$table}'."
			);
		}

		match ($this->db_driver) {
			MgrDriver::Postgres => (function () use ($table, $columns, $index_name, $unique_sql, $prefix_lengths) {
				$table_ident   = $this->db->escape_identifiers($table);
				$columns_ident = implode(', ', array_map(
					fn($c) => isset($prefix_lengths[$c])
						? "left({$this->db->escape_identifiers($c)}, {$prefix_lengths[$c]})"
						: $this->db->escape_identifiers($c),
					$columns
				));
				$this->db->query("CREATE {$unique_sql}INDEX IF NOT EXISTS {$index_name} ON {$table_ident} ({$columns_ident});");
			})(),
			MgrDriver::SQLite => (function () use ($table, $columns, $index_name, $unique_sql) {
				$table_ident   = $this->db->escape_identifiers($table);
				$columns_ident = implode(', ', array_map([$this->db, 'escape_identifiers'], $columns));
				$this->db->query("CREATE {$unique_sql}INDEX IF NOT EXISTS {$index_name} ON {$table_ident} ({$columns_ident});");
			})(),
			MgrDriver::SQLServer => (function () use ($table, $columns, $index_name, $unique_sql) {
				$table_ident   = $this->db->escape_identifiers($table);
				$columns_ident = implode(', ', array_map([$this->db, 'escape_identifiers'], $columns));
				$this->db->query("CREATE {$unique_sql}INDEX {$index_name} ON {$table_ident} ({$columns_ident});");
			})(),
			MgrDriver::MySQL,
			MgrDriver::MariaDB => (function () use ($table, $columns, $index_name, $unique_sql, $prefix_lengths) {
				$table_ident   = '`' . $table . '`';
				$columns_ident = implode(', ', array_map(
					fn($c) => isset($prefix_lengths[$c]) ? "`{$c}`({$prefix_lengths[$c]})" : "`{$c}`",
					$columns
				));
				$this->db->query("ALTER TABLE {$table_ident} ADD {$unique_sql}INDEX `{$index_name}` ({$columns_ident});");
			})()
		};
	}

	/**
	 * Drops an index from an existing table, if a matching one exists.
	 *
	 * @param  string $table   Table name
	 * @param  array|string $columns  Column(s) the index was created on — ignored when $name is
	 *         given, still required so a call site can name the intent either way
	 * @param  string|null $name  Exact index name to look for, bypassing the derived name — needed
	 *         for an index created outside this builder (`dbforge->add_key()`, hand-run DDL, a
	 *         name that differs per environment)
	 * @return bool  true if a matching index existed and was dropped, false if none did
	 */
	protected function drop_index(string $table, array|string $columns, ?string $name = null): bool
	{
		$index_name = $name ?? $this->_index_name($table, (array)$columns);

		if (!$this->_index_exists($table, $index_name)) {
			return false;
		}

		match ($this->db_driver) {
			MgrDriver::Postgres,
			MgrDriver::SQLite => $this->db->query("DROP INDEX {$index_name};"),
			MgrDriver::SQLServer => (function () use ($table, $index_name) {
				$table_ident = $this->db->escape_identifiers($table);
				$this->db->query("DROP INDEX {$index_name} ON {$table_ident};");
			})(),
			MgrDriver::MySQL,
			MgrDriver::MariaDB => (function () use ($table, $index_name) {
				$table_ident = '`' . $table . '`';
				$this->db->query("DROP INDEX `{$index_name}` ON {$table_ident};");
			})(),
		};

		return true;
	}

	/**
	 * Adds a foreign key to an existing table.
	 *
	 * @param  string $table        Table getting the FK column
	 * @param  string $column       Column on $table holding the reference
	 * @param  string $ref_table    Referenced table
	 * @param  string $ref_column   Referenced column. Default 'id'
	 * @param  string $on_delete    One of self::FK_ACTIONS. Default 'RESTRICT'
	 * @param  string $on_update    One of self::FK_ACTIONS. Default 'RESTRICT'
	 * @param  string|null $name    Exact constraint name to use, bypassing the derived name
	 * @return void
	 * @throws RuntimeException on SQLite
	 * @throws InvalidArgumentException if $on_delete/$on_update isn't in self::FK_ACTIONS
	 */
	protected function add_foreign_key(
		string $table,
		string $column,
		string $ref_table,
		string $ref_column = 'id',
		string $on_delete = 'RESTRICT',
		string $on_update = 'RESTRICT',
		?string $name = null,
	): void {
		if ($this->db_driver === MgrDriver::SQLite) {
			throw new RuntimeException(
				"MGR_Migration_builder::add_foreign_key(): SQLite cannot add a foreign key to the "
					. "existing table '{$table}'."
			);
		}
		if (!in_array($on_delete, self::FK_ACTIONS, true)) {
			throw new InvalidArgumentException("add_foreign_key(): invalid on_delete '{$on_delete}'.");
		}
		if (!in_array($on_update, self::FK_ACTIONS, true)) {
			throw new InvalidArgumentException("add_foreign_key(): invalid on_update '{$on_update}'.");
		}

		$fk_ident = $this->db->escape_identifiers($name ?? $this->_fk_name($table, $column));
		// SQL Server has no RESTRICT keyword — same behavior as NO ACTION there.
		$on_delete_sql = ($this->db_driver === MgrDriver::SQLServer && $on_delete === 'RESTRICT') ? 'NO ACTION' : $on_delete;
		$on_update_sql = ($this->db_driver === MgrDriver::SQLServer && $on_update === 'RESTRICT') ? 'NO ACTION' : $on_update;

		$table_ident     = $this->db->escape_identifiers($table);
		$column_ident    = $this->db->escape_identifiers($column);
		$ref_table_ident = $this->db->escape_identifiers($ref_table);
		$ref_column_ident = $this->db->escape_identifiers($ref_column);

		$this->db->query(
			"ALTER TABLE {$table_ident} ADD CONSTRAINT {$fk_ident} FOREIGN KEY ({$column_ident}) "
				. "REFERENCES {$ref_table_ident} ({$ref_column_ident}) "
				. "ON DELETE {$on_delete_sql} ON UPDATE {$on_update_sql};"
		);
	}

	/**
	 * Drops a foreign key from an existing table, if a matching one exists.
	 *
	 * @param  string $table   Table the FK lives on
	 * @param  string $column  Column the FK was created on — ignored when $name is given, still
	 *         required so a call site can name the intent either way
	 * @param  string|null $name  Exact constraint name to look for, bypassing the derived name —
	 *         needed for a constraint created outside this builder or named differently per environment
	 * @return bool  true if a matching foreign key existed and was dropped, false if none did
	 * @throws RuntimeException on SQLite
	 */
	protected function drop_foreign_key(string $table, string $column, ?string $name = null): bool
	{
		if ($this->db_driver === MgrDriver::SQLite) {
			throw new RuntimeException(
				"MGR_Migration_builder::drop_foreign_key(): SQLite cannot drop the foreign key on "
					. "'{$table}.{$column}'."
			);
		}

		$fk_name = $name ?? $this->_fk_name($table, $column);

		if (!$this->_fk_exists($table, $fk_name)) {
			return false;
		}

		$fk_ident    = $this->db->escape_identifiers($fk_name);
		$table_ident = $this->db->escape_identifiers($table);

		match ($this->db_driver) {
			MgrDriver::MySQL,
			MgrDriver::MariaDB => $this->db->query("ALTER TABLE {$table_ident} DROP FOREIGN KEY {$fk_ident};"),
			MgrDriver::Postgres,
			MgrDriver::SQLServer => $this->db->query("ALTER TABLE {$table_ident} DROP CONSTRAINT {$fk_ident};"),
		};

		return true;
	}

	/**
	 * Adds a primary key to an existing table.
	 *
	 * @throws RuntimeException on SQLite
	 */
	protected function add_primary_key(string $table, array|string $columns): void
	{
		if ($this->db_driver === MgrDriver::SQLite) {
			throw new RuntimeException(
				"MGR_Migration_builder::add_primary_key(): SQLite cannot add a primary key to the existing table '{$table}'."
			);
		}

		$table_ident   = $this->db->escape_identifiers($table);
		$columns_ident = implode(', ', array_map([$this->db, 'escape_identifiers'], (array)$columns));

		match ($this->db_driver) {
			// MySQL/MariaDB rename every primary key to the literal PRIMARY — naming it there is pointless.
			MgrDriver::MySQL,
			MgrDriver::MariaDB => $this->db->query("ALTER TABLE {$table_ident} ADD PRIMARY KEY ({$columns_ident});"),
			MgrDriver::Postgres,
			MgrDriver::SQLServer => $this->db->query(
				"ALTER TABLE {$table_ident} ADD CONSTRAINT {$this->db->escape_identifiers($this->_pk_name($table))} "
					. "PRIMARY KEY ({$columns_ident});"
			),
		};
	}

	/**
	 * Drops the primary key from an existing table.
	 *
	 * MySQL/MariaDB reject this while it would leave an AUTO_INCREMENT column
	 * unkeyed — index that column first, and drop the index once the new key
	 * is in place.
	 *
	 * @throws RuntimeException on SQLite, or when the table has no primary key
	 */
	protected function drop_primary_key(string $table): void
	{
		if ($this->db_driver === MgrDriver::SQLite) {
			throw new RuntimeException(
				"MGR_Migration_builder::drop_primary_key(): SQLite cannot drop the primary key of the existing table '{$table}'."
			);
		}

		$table_ident = $this->db->escape_identifiers($table);

		match ($this->db_driver) {
			MgrDriver::MySQL,
			MgrDriver::MariaDB => $this->db->query("ALTER TABLE {$table_ident} DROP PRIMARY KEY;"),
			MgrDriver::Postgres,
			MgrDriver::SQLServer => (function () use ($table, $table_ident) {
				// Read the live name rather than _pk_name(): a table the framework did not
				// create carries the engine's own ({table}_pkey, PK__table__<hash>).
				$schema = $this->db_driver === MgrDriver::Postgres ? 'current_schema()' : 'SCHEMA_NAME()';
				$row    = $this->db->query(
					'SELECT constraint_name AS pk_name FROM information_schema.table_constraints WHERE table_name = '
						. $this->db->escape($table) . " AND table_schema = {$schema} AND constraint_type = 'PRIMARY KEY';"
				)->row();

				if ($row === null) {
					throw new RuntimeException(
						"MGR_Migration_builder::drop_primary_key(): table '{$table}' has no primary key."
					);
				}

				$this->db->query(
					"ALTER TABLE {$table_ident} DROP CONSTRAINT {$this->db->escape_identifiers($row->pk_name)};"
				);
			})(),
		};
	}

	/**
	 * Adds AUTO_INCREMENT to an existing column, numbering the rows that hold
	 * no value yet (0 or NULL) and continuing past the highest existing one.
	 * The column must already be keyed — MySQL/MariaDB reject an unkeyed
	 * AUTO_INCREMENT column. Any COMMENT on the column is lost.
	 *
	 * @throws RuntimeException on SQL Server/SQLite, or when the column is not found
	 */
	protected function add_auto_increment(string $table, string $column): void
	{
		if ($this->db_driver === MgrDriver::SQLServer || $this->db_driver === MgrDriver::SQLite) {
			throw new RuntimeException(
				"MGR_Migration_builder::add_auto_increment(): {$this->db_driver->name} cannot add an "
					. "auto-increment attribute to the existing column '{$table}.{$column}'."
			);
		}

		$table_ident  = $this->db->escape_identifiers($table);
		$column_ident = $this->db->escape_identifiers($column);

		match ($this->db_driver) {
			MgrDriver::MySQL,
			MgrDriver::MariaDB => (function () use ($table, $column, $table_ident, $column_ident) {
				// MODIFY COLUMN redeclares the whole column, so the current type has to be
				// read back rather than assumed.
				$row = $this->db->query(
					"SHOW COLUMNS FROM {$table_ident} WHERE Field = " . $this->db->escape($column) . ';'
				)->row();

				if ($row === null) {
					throw new RuntimeException(
						"MGR_Migration_builder::add_auto_increment(): column '{$table}.{$column}' not found."
					);
				}

				$this->db->query(
					"ALTER TABLE {$table_ident} MODIFY COLUMN {$column_ident} {$row->Type} NOT NULL AUTO_INCREMENT;"
				);
			})(),
			MgrDriver::Postgres => (function () use ($table, $column, $table_ident, $column_ident) {
				$sequence       = $this->_pg_sequence_name($table, $column);
				$sequence_ident = $this->db->escape_identifiers($sequence);

				$this->db->query("CREATE SEQUENCE {$sequence_ident};");
				$this->db->query(
					"ALTER TABLE {$table_ident} ALTER COLUMN {$column_ident} "
						. "SET DEFAULT nextval('{$sequence}'::regclass);"
				);
				$this->db->query("ALTER SEQUENCE {$sequence_ident} OWNED BY {$table_ident}.{$column_ident};");
				// Postgres neither numbers existing rows nor positions the sequence itself.
				// MySQL does both and reads 0/NULL as "assign one" — homologated here.
				$this->db->query(
					"UPDATE {$table_ident} SET {$column_ident} = nextval('{$sequence}'::regclass) "
						. "WHERE {$column_ident} = 0 OR {$column_ident} IS NULL;"
				);
				$this->db->query(
					"SELECT setval('{$sequence}'::regclass, "
						. "COALESCE((SELECT MAX({$column_ident}) FROM {$table_ident}), 1), "
						. "(SELECT MAX({$column_ident}) FROM {$table_ident}) IS NOT NULL);"
				);
			})(),
		};
	}

	/**
	 * Generates a consistent index name.
	 * Postgres includes table name to avoid cross-schema collisions.
	 */
	protected function _index_name(string $table, array $columns): string
	{
		$suffix = implode('_', $columns);
		$name   = match ($this->db_driver) {
			MgrDriver::Postgres,
			MgrDriver::SQLite    => "{$table}_{$suffix}_key",
			MgrDriver::SQLServer,
			MgrDriver::MySQL,
			MgrDriver::MariaDB   => $suffix,
		};

		return $this->_truncate_identifier($name);
	}

	/**
	 * Whether an index by this exact name exists on the table, per the engine's own catalog —
	 * not a lookup by column, so it never matches a different index over the same columns.
	 */
	protected function _index_exists(string $table, string $index_name): bool
	{
		$table_escaped = $this->db->escape($table);
		$name_escaped  = $this->db->escape($index_name);

		$row = match ($this->db_driver) {
			MgrDriver::Postgres => $this->db->query(
				"SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() "
					. "AND tablename = {$table_escaped} AND indexname = {$name_escaped};"
			)->row(),
			MgrDriver::SQLite => $this->db->query(
				"SELECT 1 FROM sqlite_master WHERE type = 'index' "
					. "AND tbl_name = {$table_escaped} AND name = {$name_escaped};"
			)->row(),
			MgrDriver::SQLServer => $this->db->query(
				"SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID({$table_escaped}) "
					. "AND name = {$name_escaped};"
			)->row(),
			MgrDriver::MySQL,
			MgrDriver::MariaDB => $this->db->query(
				"SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() "
					. "AND table_name = {$table_escaped} AND index_name = {$name_escaped};"
			)->row(),
		};

		return $row !== null;
	}

	/**
	 * Generates a consistent foreign key constraint name.
	 */
	protected function _fk_name(string $table, string $column): string
	{
		return $this->_truncate_identifier("fk_{$table}_{$column}");
	}

	/**
	 * Whether a foreign key by this exact name exists on the table, per
	 * `information_schema.table_constraints` — not reachable on SQLite, which has no such catalog
	 * and where `drop_foreign_key()` already throws before calling this.
	 */
	protected function _fk_exists(string $table, string $fk_name): bool
	{
		$schema = match ($this->db_driver) {
			MgrDriver::Postgres  => 'current_schema()',
			MgrDriver::SQLServer => 'SCHEMA_NAME()',
			MgrDriver::MySQL,
			MgrDriver::MariaDB   => 'DATABASE()',
			MgrDriver::SQLite    => throw new RuntimeException(
				'MGR_Migration_builder::_fk_exists(): SQLite has no foreign-key catalog to query.'
			),
		};

		$row = $this->db->query(
			'SELECT 1 FROM information_schema.table_constraints WHERE table_name = '
				. $this->db->escape($table) . " AND table_schema = {$schema} "
				. 'AND constraint_name = ' . $this->db->escape($fk_name)
				. " AND constraint_type = 'FOREIGN KEY';"
		)->row();

		return $row !== null;
	}

	/**
	 * Generates a consistent primary key constraint name.
	 */
	protected function _pk_name(string $table): string
	{
		return $this->_truncate_identifier("pk_{$table}");
	}

	/**
	 * Generates the sequence name Postgres itself assigns a SERIAL column, so
	 * a restored one is indistinguishable from a natively declared one.
	 */
	protected function _pg_sequence_name(string $table, string $column): string
	{
		return $this->_truncate_identifier("{$table}_{$column}_seq");
	}

	/**
	 * Ensures an identifier never exceeds the engine's name limit.
	 * Pass $constraint only to override that limit.
	 */
	protected function _truncate_identifier(string $identifier, ?int $constraint = null): string
	{
		$constraint ??= match ($this->db_driver) {
			MgrDriver::Postgres,
			MgrDriver::SQLite    => 63,
			MgrDriver::SQLServer => 128,
			MgrDriver::MySQL,
			MgrDriver::MariaDB   => 64,
		};

		if (strlen($identifier) <= $constraint) {
			return $identifier;
		}

		return substr($identifier, 0, ($constraint - 11)) . '_' . substr(md5($identifier), 0, 10);
	}

	/**
	 * Enable the use of CI super-global
	 *
	 * @param	string	$var
	 * @return	mixed
	 */
	public function __get($var)
	{
		return get_instance()->$var;
	}
}
