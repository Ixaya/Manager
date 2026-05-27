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
	/** 1-byte int. MySQL: TINYINT. PostgreSQL: SMALLINT (no native TINYINT). */
	case TinyInt	 = 'TINYINT';
	/** 2-byte int. */
	case SmallInt	= 'SMALLINT';
	/** 4-byte int — most common. */
	case Int		  = 'INT';
	/** 8-byte int. */
	case BigInt	  = 'BIGINT';

	// ── Decimals ─────────────────────────────────────────────────────────────
	/** Exact decimal. Use $precision + $scale params on field(). */
	case Decimal	 = 'DECIMAL';
	/** 4-byte float. */
	case Float		= 'FLOAT';
	/** 8-byte float. MySQL: DOUBLE. PostgreSQL: DOUBLE PRECISION. */
	case Double	  = 'DOUBLE';

	// ── Strings ──────────────────────────────────────────────────────────────
	/** Fixed-length string. Requires $length. */
	case Char		 = 'CHAR';
	/** Variable-length string. Requires $length. Max 65,535 bytes MySQL; 1GB PgSQL. */
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
	/** Timestamp. MySQL: TIMESTAMP. PostgreSQL: TIMESTAMP. */
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
// MgrFieldBuilder — internal. Resolves a CI dbforge-compatible field array.
// Not used directly — instantiated by Mgr_Migration::field().
// ---------------------------------------------------------------------------

final class MgrFieldBuilder
{
	/**
	 * @param string		 $name
	 * @param MgrFieldType $type
	 * @param MgrDriver	 $driver			 Injected from Mgr_Migration — computed once per migration
	 * @param int|null	  $length
	 * @param bool			$unsigned		  Ignored on non-MySQL engines
	 * @param bool|null	 $nullable		  true=NULL, false=NOT NULL, null=CI default
	 * @param bool			$unique
	 * @param bool			$auto_increment
	 * @param mixed		  $default			Scalar|null|'' — '' means no default set
	 * @param string|null  $new_name		  For modify_column renames
	 * @param int|null	  $precision		 DECIMAL total digits
	 * @param int			 $scale			  DECIMAL digits after decimal point
	 * @param mixed[]	  $enum_values	  Required for MgrFieldType::Enum
	 */
	public function __construct(
		private readonly string		 $name,
		private readonly MgrFieldType $type,
		private readonly MgrDriver	 $driver,
		private readonly ?int			$length			= null,
		private readonly bool			$unsigned		 = false,
		private readonly ?bool		  $nullable		 = null,
		private readonly bool			$unique			= false,
		private readonly bool			$auto_increment = false,
		private readonly mixed		  $default		  = '',
		private readonly ?string		$new_name		 = null,
		private readonly ?int			$precision		= null,
		private readonly int			 $scale			 = 0,
		private readonly array		  $enum_values	 = [],
	) {
		$this->_validate();
	}

	// ── Validation ───────────────────────────────────────────────────────────

	private function _validate(): void
	{
		if (empty($this->name)) {
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
		if ($this->type === MgrFieldType::Enum && empty($this->enum_values)) {
			throw new InvalidArgumentException(
				"MgrFieldBuilder: MgrFieldType::Enum requires the enum_values parameter."
			);
		}
		if ($this->type === MgrFieldType::Decimal && $this->precision === null && $this->length === null) {
			throw new InvalidArgumentException(
				"MgrFieldBuilder: MgrFieldType::Decimal requires the precision parameter."
			);
		}
		if ($this->default !== '' && $this->default !== null && !is_scalar($this->default)) {
			throw new InvalidArgumentException(
				"MgrFieldBuilder: default must be scalar, null, or '' (empty string = no default)."
			);
		}
		foreach ($this->enum_values as $v) {
			if (!is_string($v)) {
				throw new InvalidArgumentException(
					"MgrFieldBuilder: all enum_values must be strings."
				);
			}
		}
	}

	// ── Build ────────────────────────────────────────────────────────────────

	/** Produce the CI dbforge-compatible field array. */
	public function build(): array
	{
		['type' => $type, 'length' => $length] = $this->_resolveType();

		$field = ['type' => $type];

		if ($length !== '') {
			$field['length']			= $length;
		}
		if ($this->unsigned) {
			$field['unsigned']		 = $this->unsigned;
		}
		if ($this->nullable !== null) {
			$field['null']			  = $this->nullable;
		}
		if ($this->unique) {
			$field['unique']			= true;
		}
		if ($this->default !== '') {
			$field['default']		  = $this->default;
		}
		if ($this->auto_increment) {
			$field['auto_increment'] = true;
		}
		if ($this->new_name !== null) {
			$field['name']		 = $this->new_name;
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
	 *  DateTime		│ DATETIME				│ TIMESTAMP				│ DATETIME2			  │ TEXT
	 *  Double		  │ DOUBLE				  │ DOUBLE PRECISION	  │ FLOAT					│ DOUBLE
	 *  MediumText	 │ MEDIUMTEXT			 │ TEXT					  │ NVARCHAR(MAX)		 │ TEXT
	 *  LongText		│ LONGTEXT				│ TEXT					  │ NVARCHAR(MAX)		 │ TEXT
	 *  Year			 │ YEAR					 │ SMALLINT				 │ SMALLINT				│ INTEGER
	 *  Uuid			 │ CHAR(36)				│ UUID					  │ UNIQUEIDENTIFIER	 │ TEXT
	 *  Enum			 │ ENUM('a','b',…)	  │ VARCHAR(max_len)	  │ NVARCHAR(max_len)	│ TEXT
	 *  UNSIGNED		│ supported			  │ ignored				  │ ignored				 │ ignored
	 */

	/**
	 * Cross-engine type translation:
	 *
	 *
	 * @return array{type: string, length: string}
	 */
	private function _resolveType(): array
	{
		$type	  = $this->type->value;
		$length	= $this->length !== null ? (string) $this->length : '';

		switch ($this->type) {

			case MgrFieldType::Bool:
				[$type, $length] = match ($this->driver) {
					MgrDriver::Postgres				  => ['BOOLEAN', ''],
					MgrDriver::SQLServer			 => ['BIT',	  ''],
					MgrDriver::SQLite				 => ['INTEGER', ''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB				  => ['TINYINT', '1'],
				};
				break;

			case MgrFieldType::TinyInt:
				[$type, $length] = match ($this->driver) {
					MgrDriver::Postgres				  => ['SMALLINT', ''],
					MgrDriver::SQLite					 => ['INTEGER',  ''],
					default								  => ['TINYINT',  $length],
				};
				break;

			case MgrFieldType::Blob:
			case MgrFieldType::MediumBlob:
			case MgrFieldType::LongBlob:
				[$type, $length] = match ($this->driver) {
					MgrDriver::Postgres				  => ['BYTEA',			 ''],
					MgrDriver::SQLServer				 => ['VARBINARY(MAX)', ''],
					MgrDriver::SQLite					 => ['BLOB',			  ''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB					  => [$this->type->value, ''],
				};
				break;

			case MgrFieldType::Json:
				[$type, $length] = match ($this->driver) {
					MgrDriver::Postgres				  => ['JSONB',			''],
					MgrDriver::SQLServer				 => ['NVARCHAR(MAX)', ''],
					MgrDriver::SQLite					 => ['TEXT',			 ''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB					  => ['JSON',			 ''],
				};
				break;

			case MgrFieldType::DateTime:
				[$type, $length] = match ($this->driver) {
					MgrDriver::Postgres				  => ['TIMESTAMP', ''],
					MgrDriver::SQLServer				 => ['DATETIME2', ''],
					MgrDriver::SQLite					 => ['TEXT',		''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB					  => ['DATETIME',  ''],
				};
				break;

			case MgrFieldType::Double:
				[$type, $length] = match ($this->driver) {
					MgrDriver::Postgres				  => ['DOUBLE PRECISION', ''],
					MgrDriver::SQLServer				 => ['FLOAT',				''],
					default								  => ['DOUBLE',			  ''],
				};
				break;

			case MgrFieldType::MediumText:
			case MgrFieldType::LongText:
				[$type, $length] = match ($this->driver) {
					MgrDriver::Postgres				  => ['TEXT',			 ''],
					MgrDriver::SQLServer				 => ['NVARCHAR(MAX)', ''],
					MgrDriver::SQLite					 => ['TEXT',			 ''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB					  => [$this->type->value, ''],
				};
				break;

			case MgrFieldType::Year:
				[$type, $length] = match ($this->driver) {
					MgrDriver::Postgres, MgrDriver::SQLServer => ['SMALLINT', ''],
					MgrDriver::SQLite								 => ['INTEGER',  ''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB							 => ['YEAR',	  ''],
				};
				break;

			case MgrFieldType::Uuid:
				[$type, $length] = match ($this->driver) {
					MgrDriver::Postgres				  => ['UUID',				  ''],
					MgrDriver::SQLServer				 => ['UNIQUEIDENTIFIER',  ''],
					MgrDriver::SQLite					 => ['TEXT',				  ''],
					MgrDriver::MySQL,
					MgrDriver::MariaDB					  => ['CHAR',			 '36'],
				};
				break;

			case MgrFieldType::Decimal:
				$length = $this->precision !== null
					? "{$this->precision},{$this->scale}"
					: $length;
				break;

			case MgrFieldType::Enum:
				if ($this->driver->isMysqlFamily()) {
					$quoted = array_map(
						static fn(string $v): string => "'" . addslashes($v) . "'",
						$this->enum_values
					);
					$type	= 'ENUM(' . implode(',', $quoted) . ')';
					$length = '';
				} else {
					$max	 = max(array_map('strlen', $this->enum_values));
					$type	= ($this->driver === MgrDriver::SQLServer) ? 'NVARCHAR' : 'VARCHAR';
					$length = (string) max($max, 1);
				}
				break;
		}

		return ['type' => $type, 'length' => $length];
	}
}


// ---------------------------------------------------------------------------
// Mgr_Migration — base class. Extend this instead of CI_Migration.
// ---------------------------------------------------------------------------

class MGR_Migration_builder
{
	/** Detected once per migration instance — all field() calls reuse this. */
	protected MgrDriver $db_driver;

	public function __construct()
	{
		$CI = &get_instance();
		$this->db_driver = MgrDriver::fromCI($CI->db->dbdriver ?? '');
	}

	// ── Field factory ────────────────────────────────────────────────────────

	/**
	 * Build a CI dbforge-compatible field array using named parameters.
	 *
	 * Examples:
	 *
	 *	// Basic string column
	 *	$this->field(name: 'email', type: MgrFieldType::VarChar, length: 191, nullable: false, unique: true)
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
	 *	$this->field(name: 'old_col', type: MgrFieldType::VarChar, length: 100, new_name: 'new_col')
	 *
	 * @param  string		 $name
	 * @param  MgrFieldType $type
	 * @param  int|null	  $length			 For CHAR, VARCHAR, etc.
	 * @param  bool			$unsigned		  Integers only. Silently ignored on non-MySQL.
	 * @param  bool|null	 $nullable		  true=NULL | false=NOT NULL | null=CI default
	 * @param  bool			$unique
	 * @param  bool			$auto_increment
	 * @param  mixed		  $default			Scalar or null. '' = no default set (CI default).
	 * @param  string|null  $new_name		  For modify_column renames only.
	 * @param  int|null	  $precision		 DECIMAL: total significant digits.
	 * @param  int			 $scale			  DECIMAL: digits after decimal point. Default 0.
	 * @param  string[]	  $enum_values	  Required when type is MgrFieldType::Enum.
	 * @return array
	 */
	protected function field(
		string		 $name,
		MgrFieldType $type,
		?int			$length			= null,
		bool			$unsigned		 = false,
		?bool		  $nullable		 = null,
		bool			$unique			= false,
		bool			$auto_increment = false,
		mixed		  $default		  = '',
		?string		$new_name		 = null,
		?int			$precision		= null,
		int			 $scale			 = 0,
		array		  $enum_values	 = [],
	): array {
		return (new MgrFieldBuilder(
			name: $name,
			type: $type,
			driver: $this->db_driver,
			length: $length,
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
	 * Adds an auto-update trigger/modifier for a timestamp column.
	 *
	 * Behaviour varies by engine — the end result is the same: the column
	 * is automatically set to the current timestamp on every UPDATE.	 *
	 *
	 * @param  string $table	Table name
	 * @param  string $column  Column name to auto-update. Default: 'last_update'
	 * @param  bool $on_update	Add on update trigger
	 * @return void
	 */
	protected function modify_field_timestamp(string $table, string $column = 'last_update', bool $on_update = true): void
	{
		$table_ident  = $this->db->escape_identifiers($table);
		$column_ident = $this->db->escape_identifiers($column);

		match ($this->db_driver) {
			MgrDriver::Postgres => (function () use ($table_ident, $column_ident, $table, $column, $on_update) {
				$this->db->query("ALTER TABLE {$table_ident}
                ALTER COLUMN {$column_ident}
                SET DEFAULT CURRENT_TIMESTAMP;");

				if (!$on_update) {
					return;
				}

				$this->db->query("CREATE OR REPLACE FUNCTION set_{$table}_{$column}()
                RETURNS TRIGGER AS $$
                BEGIN
                     NEW.{$column} := NOW();
                     RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;");

				$this->db->query("DROP TRIGGER IF EXISTS trg_{$table}_{$column} ON {$table_ident};
                CREATE TRIGGER trg_{$table}_{$column}
                BEFORE UPDATE ON {$table_ident}
                FOR EACH ROW
                EXECUTE FUNCTION set_{$table}_{$column}();");
			})(),

			MgrDriver::MySQL,
			MgrDriver::MariaDB => (function () use ($table, $column, $on_update) {
				$sql = "ALTER TABLE `{$table}`
                MODIFY COLUMN `{$column}`
                TIMESTAMP DEFAULT CURRENT_TIMESTAMP";

				if ($on_update) {
					$sql .= " ON UPDATE CURRENT_TIMESTAMP";
				}
				$this->db->query($sql);
			})(),

			// SQLite, SQLServer — no-op, silently skip
			default => null,
		};
	}

	/**
	 * Adds an index to an existing table.
	 *
	 * @param  string $table   Table name
	 * @param  array|string $columns  Column(s) to index
	 * @param  bool $unique   Whether the index is unique
	 * @return void
	 */
	protected function add_index(string $table, array|string $columns, bool $unique = false): void
	{
		$columns     = (array)$columns;
		$index_name  = $this->_index_name($table, $columns);
		$unique_sql  = $unique ? 'UNIQUE ' : '';

		match ($this->db_driver) {
			MgrDriver::Postgres => (function () use ($table, $columns, $index_name, $unique_sql) {
				$table_ident   = $this->db->escape_identifiers($table);
				$columns_ident = implode(', ', array_map([$this->db, 'escape_identifiers'], $columns));
				$this->db->query("CREATE {$unique_sql}INDEX IF NOT EXISTS {$index_name} ON {$table_ident} ({$columns_ident});");
			})(),
			MgrDriver::MySQL,
			MgrDriver::MariaDB => (function () use ($table, $columns, $index_name, $unique_sql) {
				$table_ident   = '`' . $table . '`';
				$columns_ident = implode(', ', array_map(fn($c) => '`' . $c . '`', $columns));
				$this->db->query("ALTER TABLE {$table_ident} ADD {$unique_sql}INDEX `{$index_name}` ({$columns_ident});");
			})(),
			default => null,
		};
	}

	/**
	 * Drops an index from an existing table.
	 *
	 * @param  string $table   Table name
	 * @param  array|string $columns  Column(s) the index was created on
	 * @return void
	 */
	protected function drop_index(string $table, array|string $columns): void
	{
		$columns    = (array)$columns;
		$index_name = $this->_index_name($table, $columns);

		match ($this->db_driver) {
			MgrDriver::Postgres => (function () use ($index_name) {
				$this->db->query("DROP INDEX IF EXISTS {$index_name};");
			})(),
			MgrDriver::MySQL,
			MgrDriver::MariaDB => (function () use ($table, $index_name) {
				$table_ident = '`' . $table . '`';
				$this->db->query("DROP INDEX `{$index_name}` ON {$table_ident};");
			})(),
			default => null,
		};
	}

	/**
	 * Generates a consistent index name.
	 * Postgres includes table name to avoid cross-schema collisions.
	 *
	 * @param  string $table
	 * @param  array  $columns
	 * @return string
	 */
	private function _index_name(string $table, array $columns): string
	{
		$suffix = implode('_', $columns);
		return match ($this->db_driver) {
			MgrDriver::Postgres => $this->_truncate_identifier("{$table}_{$suffix}_key", 63),
			MgrDriver::MySQL,
			MgrDriver::MariaDB  => $this->_truncate_identifier($suffix, 64),
			default             => $suffix,
		};
	}
	/**
	 * Ensures the index name never exceeds name limit.
	 */
	private function _truncate_identifier(string $identifier, int $length): string
	{
		if (strlen($identifier) <= $length) {
			return $identifier;
		}

		return substr($identifier, 0, ($length - 11)) . '_' . substr(md5($identifier), 0, 10);
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
