<?php

defined('BASEPATH') or exit('No direct script access allowed');

// ---------------------------------------------------------------------------
// MgrDriver — normalized DB driver enum.
//
// Loaded by:
//   - Mgr_Migration (base migration class)
//   - Mgr_Function_helper (SQL function builder)
//   - Any model/helper that needs driver-aware SQL
//
// Usage:
//   $driver = MgrDriver::fromCI($this->db->dbdriver);
// ---------------------------------------------------------------------------

enum MgrDriver: string
{
	case MySQL     = 'mysqli';
	case MariaDB   = 'mariadb';
	case Postgres  = 'postgre';
	case SQLServer = 'sqlsrv';
	case SQLite    = 'sqlite3';

	// ── Factory ──────────────────────────────────────────────────────────────

	/**
	 * Resolve from CI's raw dbdriver string into a normalized MgrDriver.
	 *
	 * @param  string $raw         CI's $this->db->dbdriver value.
	 * @param  bool   $unifyMysql  When true (default), MariaDB is folded into MySQL
	 *                             since they share virtually identical SQL syntax.
	 *                             Pass false only when you need to distinguish them.
	 * @return self
	 */
	public static function fromCI(string $raw, bool $unifyMysql = true): self
	{
		if ($unifyMysql) {
			return match (strtolower(trim($raw))) {
				'mysqli', 'mysql', 'mariadb' => self::MySQL,
				'postgre', 'pgsql'           => self::Postgres,
				'sqlsrv', 'mssql'            => self::SQLServer,
				'sqlite3', 'sqlite'          => self::SQLite,
				default                      => self::MySQL,
			};
		}

		return match (strtolower(trim($raw))) {
			'mysqli', 'mysql'    => self::MySQL,
			'mariadb'            => self::MariaDB,
			'postgre', 'pgsql'   => self::Postgres,
			'sqlsrv', 'mssql'    => self::SQLServer,
			'sqlite3', 'sqlite'  => self::SQLite,
			default              => self::MySQL,
		};
	}

	// ── Predicates ───────────────────────────────────────────────────────────

	/** True for MySQL and MariaDB — share most SQL syntax. */
	public function isMysqlFamily(): bool
	{
		return match ($this) {
			self::MySQL, self::MariaDB => true,
			default                    => false,
		};
	}

	/** True for engines that support UNSIGNED numerics. */
	public function supportsUnsigned(): bool
	{
		return $this->isMysqlFamily();
	}
}
