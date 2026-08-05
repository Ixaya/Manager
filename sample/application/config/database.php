<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| DATABASE CONNECTIVITY SETTINGS
| -------------------------------------------------------------------
|
| One entry per connection group. $active_group names the default group;
| $query_builder loads the Query Builder class. Values are read from the
| environment through mgr_env(), or mgr_env_required() where a wrong value
| would be worse than a missing one.
|
|	['dsn'] (string) Full connection string. Empty unless you connect by
|	    dsn directly; mgr_apply_pdo_dsn() below fills it in for a compound
|	    'pdo/<engine>' driver.
|	['hostname'] (string) Database host.
|	['port'] (int|null) Null leaves the driver's own default.
|	['username'] (string) Required, no fallback: a silent 'root' either
|	    connects as the wrong identity or leaves conn_id false with db_debug
|	    off, surfacing as an unexplained 500 on every request.
|	['password'] (string)
|	['database'] (string) Required, no fallback — as username.
|	['dbdriver'] (string) mysqli (also MariaDB) or postgre. A compound
|	    'pdo/<engine>' value — pdo/mysql, pdo/pgsql — goes through PDO.
|	['dbprefix'] (string) Prepended to table names by the Query Builder.
|	['pconnect'] (bool) TRUE reuses a persistent connection.
|	['db_debug'] (bool) What a query that fails to execute does:
|	    TRUE  aborts the request — development renders the failing SQL,
|	          production a generic 500; either way the detail is logged.
|	    FALSE returns null/false and carries on, so every call must check.
|	['cache_on'] (bool) TRUE caches query results into cachedir.
|	['cachedir'] (string) Writable path for that cache.
|	['char_set'] (string) Client character set.
|	['dbcollat'] (string) Client collation, engine-specific; empty for
|	    PostgreSQL, a matching utf8mb4_* value for MySQL or MariaDB.
|	['swap_pre'] (string) Prefix in your own queries to swap for dbprefix.
|	['encrypt'] (bool|array) TRUE/FALSE for sqlsrv and pdo/sqlsrv; mysqli
|	    and pdo/mysql take an array of ssl_key, ssl_cert, ssl_ca,
|	    ssl_capath, ssl_cipher, ssl_verify.
|	['compress'] (bool) TRUE enables client compression (MySQL only).
|	['stricton'] (bool) TRUE forces Strict Mode — stricter SQL while
|	    developing.
|	['failover'] (array) Zero or more full configs tried if this one fails.
|	['save_queries'] (bool) TRUE keeps every executed query for
|	    last_query() and DB profiling, at a memory cost proportional to
|	    query volume.
*/
$active_group = 'default';
$query_builder = true;

if (!function_exists('mgr_apply_pdo_dsn')) {
	/**
	 * Rewrites a CI DB config's dbdriver/dsn when dbdriver is a compound
	 * 'pdo/<engine>' value (e.g. 'pdo/pgsql'), building the DSN CI's pdo
	 * driver requires from the config's own hostname/port/database. Any
	 * other dbdriver passes through unchanged.
	 *
	 * @param  array<string, mixed> $config
	 * @return array<string, mixed>
	 */
	function mgr_apply_pdo_dsn(array $config): array
	{
		if (!str_contains($config['dbdriver'], '/')) {
			return $config;
		}

		[$dbdriver, $subdriver] = explode('/', $config['dbdriver'], 2);

		$dsn = "{$subdriver}:host={$config['hostname']}";
		if (!empty($config['port'])) {
			$dsn .= ";port={$config['port']}";
		}
		$dsn .= ";dbname={$config['database']}";

		$overrides = ['dbdriver' => $dbdriver, 'dsn' => $dsn];

		// Without this pgsql prepares every statement server-side, adding a round
		// trip that guards nothing since CI binds no parameters. pdo_sqlite rejects
		// the attribute, hence the subdriver check.
		if ($subdriver === 'pgsql') {
			$overrides['options'] = ($config['options'] ?? []) + [PDO::ATTR_EMULATE_PREPARES => true];
		}

		return array_merge($config, $overrides);
	}
}

$db['default'] = mgr_apply_pdo_dsn([
	'dsn'	=> '',
	'hostname' => mgr_env('DB_HOST', 'localhost'),
	'port' => mgr_env('DB_PORT', null),
	'username' => mgr_env_required('DB_USER'),
	'password' => mgr_env('DB_PASS', ''),
	'database' => mgr_env_required('DB_NAME'),
	'dbdriver' => mgr_env('DB_DRIVER', 'mysqli'),
	'dbprefix' => '',
	'pconnect' => false,
	'db_debug' => (ENVIRONMENT !== 'production'),
	'cache_on' => false,
	'cachedir' => '',
	'char_set' => mgr_env('DB_CHAR_SET', 'utf8mb4'),
	'dbcollat' => mgr_env('DB_COLLATION', ''),
	'swap_pre' => '',
	'encrypt' => false,
	'compress' => false,
	'stricton' => false,
	'failover' => [],
	'save_queries' => true
]);

// $db['secondary'] = mgr_apply_pdo_dsn([
// 	'dsn'	=> '',
// 	'hostname' => mgr_env('DB_SEC_HOST', 'localhost'),
//  'port' => mgr_env('DB_SEC_PORT', null),
// 	'username' => mgr_env('DB_SEC_USER', 'root'),
// 	'password' => mgr_env('DB_SEC_PASS', ''),
// 	'database' => mgr_env('DB_SEC_NAME', ''),
// 	'dbdriver' => mgr_env('DB_SEC_DRIVER', 'mysqli'),
// 	'dbprefix' => '',
// 	'pconnect' => false,
// 	'db_debug' => (ENVIRONMENT !== 'production'),
// 	'cache_on' => false,
// 	'cachedir' => '',
// 	'char_set' => mgr_env('DB_SEC_CHAR_SET', 'utf8mb4'),
// 	'dbcollat' => mgr_env('DB_SEC_COLLATION', ''),
// 	'swap_pre' => '',
// 	'encrypt' => false,
// 	'compress' => false,
// 	'stricton' => false,
// 	'failover' => [],
// 	'save_queries' => true
// ]);
