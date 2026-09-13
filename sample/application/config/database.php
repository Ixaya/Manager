<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Pull in the Global Framework Configuration
|--------------------------------------------------------------------------
| Required base: mgr_apply_pdo_dsn() lives there, guarded by function_exists
| so a legacy project defining it locally still doesn't fatal.
*/
include MGRPATH . 'config/database.php';

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
|	    dsn directly; mgr_apply_pdo_dsn(), included above, fills it in for
|	    a compound 'pdo/<engine>' driver.
|	['hostname'] (string) Database host.
|	['port'] (int|null) Null leaves the driver's own default.
|	['username'] (string) Required, no fallback: a silent 'root' either
|	    connects as the wrong identity or leaves conn_id false with db_debug
|	    off, surfacing as an unexplained 500 on every request.
|	['password'] (string)
|	['database'] (string) Required, no fallback — as username.
|	['dbdriver'] (string) pdo/mysql or pdo/pgsql (default, typed fetches),
|	    or mysqli (also MariaDB) / postgre (native, stringified fetches).
|	['dbprefix'] (string) Prepended to table names by the Query Builder.
|	['pconnect'] (bool) TRUE reuses a persistent connection.
|	['db_debug'] (bool) What a query that fails to execute does:
|	    TRUE  aborts the request — development renders the failing SQL,
|	          production a generic 500; either way the detail is logged.
|	    FALSE returns null/false and carries on, so every call must check.
|	['cache_on'] (bool) TRUE caches query results into cachedir.
|	['cachedir'] (string) Writable path for that cache.
|	['char_set'] (string) Client character set. No default — the right
|	    value is engine-specific (see docs/development/database.md's
|	    engine table).
|	['dbcollat'] (string) Client collation, engine-specific; empty for
|	    PostgreSQL, a matching utf8mb4_* value for MySQL or MariaDB.
|	['swap_pre'] (string) Prefix in your own queries to swap for dbprefix.
|	['encrypt'] (bool|array) TRUE/FALSE for sqlsrv and pdo/sqlsrv; mysqli
|	    and pdo/mysql take an array of ssl_key, ssl_cert, ssl_ca,
|	    ssl_capath, ssl_cipher, ssl_verify.
|	['options'] (array) Passed straight to the PDO constructor on a
|	    'pdo/<engine>' driver; ignored otherwise.
|	['compress'] (bool) TRUE enables client compression (MySQL only).
|	['stricton'] (bool) TRUE forces MySQL/MariaDB Strict Mode
|	    (STRICT_ALL_TABLES); no effect on other engines.
|	['failover'] (array) Zero or more full configs tried if this one fails.
|	['save_queries'] (bool) TRUE keeps every executed query for
|	    last_query() and DB profiling, at a memory cost proportional to
|	    query volume.
*/
$active_group = 'default';
$query_builder = true;

$db['default'] = mgr_apply_pdo_dsn([
	'dsn'	=> '',
	'hostname' => mgr_env('DB_HOST', 'localhost'),
	'port' => mgr_env('DB_PORT', null),
	'username' => mgr_env_required('DB_USER'),
	'password' => mgr_env('DB_PASS', ''),
	'database' => mgr_env_required('DB_NAME'),
	'dbdriver' => mgr_env('DB_DRIVER', 'pdo/mysql'),
	// bridges native's stringify-everything contract on pdo driver
	// 'options' => [PDO::ATTR_STRINGIFY_FETCHES => true],
	'dbprefix' => '',
	'pconnect' => false,
	'db_debug' => (ENVIRONMENT !== 'production'),
	'cache_on' => false,
	'cachedir' => '',
	'char_set' => mgr_env('DB_CHAR_SET', ''),
	'dbcollat' => mgr_env('DB_COLLATION', ''),
	'swap_pre' => '',
	'encrypt' => false,
	'compress' => false,
	'stricton' => true,
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
// 	'dbdriver' => mgr_env('DB_SEC_DRIVER', 'pdo/mysql'),
// 	'dbprefix' => '',
// 	'pconnect' => false,
// 	'db_debug' => (ENVIRONMENT !== 'production'),
// 	'cache_on' => false,
// 	'cachedir' => '',
// 	'char_set' => mgr_env('DB_SEC_CHAR_SET'),
// 	'dbcollat' => mgr_env('DB_SEC_COLLATION', ''),
// 	'swap_pre' => '',
// 	'encrypt' => false,
// 	'compress' => false,
// 	'stricton' => true,
// 	'failover' => [],
// 	'save_queries' => true
// ]);
