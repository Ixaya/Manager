<?php

defined('BASEPATH') or exit('No direct script access allowed');

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

		$dsn_body = match ($subdriver) {
			'sqlite' => $config['database'],
			'dblib' => "host={$config['hostname']}"
				. (empty($config['port']) ? '' : ":{$config['port']}")
				. ";dbname={$config['database']}",
			'sqlsrv' => "Server={$config['hostname']}"
				. (empty($config['port']) ? '' : ",{$config['port']}")
				. ";database={$config['database']}",
			default => "host={$config['hostname']}"
				. (empty($config['port']) ? '' : ";port={$config['port']}")
				. ";dbname={$config['database']}",
		};
		$dsn = "{$subdriver}:{$dsn_body}";

		$overrides = ['dbdriver' => $dbdriver, 'dsn' => $dsn];
		$options = $config['options'] ?? [];

		// Defaults for one statement per query() call; a project's own `options` entry wins (+=).
		// The Pdo\* constants exist from 8.4; the PDO:: spellings are deprecated in 8.5.
		if ($subdriver === 'mysql') {
			$options += PHP_VERSION_ID >= 80400
				? [Pdo\Mysql::ATTR_MULTI_STATEMENTS => false]
				: [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false];
		}

		if ($subdriver === 'pgsql') {
			// 8.3 keeps emulation: its extended-protocol placeholder scanner rewrites `?` and `:name` inside `$$` text.
			$options += PHP_VERSION_ID >= 80400
				? [PDO::ATTR_EMULATE_PREPARES => false, Pdo\Pgsql::ATTR_DISABLE_PREPARES => true]
				: [PDO::ATTR_EMULATE_PREPARES => true];

			// pdo_pgsql has no _db_set_charset(); the DSN's `options` keyword is
			// the only way libpq accepts a client_encoding.
			if (!empty($config['char_set'])) {
				$overrides['dsn'] = $dsn . ";options='-c client_encoding={$config['char_set']}'";
			}
		}

		if ($options !== []) {
			$overrides['options'] = $options;
		}

		return array_merge($config, $overrides);
	}
}
