<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Drives per-module migrations with independent version sequences.
 *
 * Does NOT reimplement migration logic. For each target (the application plus
 * every HMVC module) it points the existing migration library at one absolute
 * path, sets a storage key, and calls the library's own latest()/version().
 * The library tracks each key independently:
 *   key = null  -> main `migrations` table  (application, legacy behaviour)
 *   key = <uri> -> `migrations_path` table  (one row per module)
 *
 * Library contract (implemented next, in MGR_Migration):
 *   set_path(string $absolute): void
 *   set_migration_key(?string $key): void
 *   _get_version()/_update_version() scoped by key, UPSERT on write
 */
class MGR_Migration_module_lib
{
	/** @var CI_Controller */
	protected $CI;

	/** @var array<string, object> */
	protected array $_libs = [];

	public function __construct()
	{
		$this->CI = & get_instance();
	}

	// ---- Read-only: what WOULD run. No writes, nothing executed. ----------

	/**
	 * @return array<int,array{key:?string,label:string,path:string,current:int,latest:int,pending:array<int,string>}>
	 */
	public function plan(string $conn = 'default'): array
	{
		$versions = $this->_read_versions($conn);
		$plan     = [];

		foreach ($this->_discover_targets($conn) as $t) {
			$current = ($t['key'] === null)
				? $versions['app']
				: ($versions['modules'][$t['key']] ?? 0);

			$numbers = array_keys($t['files']);
			$pending = array_filter($t['files'], fn ($n) => (int) $n > $current, ARRAY_FILTER_USE_KEY);

			$plan[] = [
				'key'     => $t['key'],
				'label'   => $t['label'],
				'path'    => $t['path'],
				'current' => $current,
				'latest'  => $numbers ? (int) end($numbers) : 0,
				'pending' => $pending,
			];
		}

		return $plan;
	}

	// ---- Seeding: record a version without running migrations. Use at your own risk. ----

	public function version_set(string $conn, ?string $key, string $version): string
	{
		$lib = $this->_lib($conn);
		$lib->set_migration_key($key);
		$lib->version_set($version);
		$label = ($key ?? 'application') . ':' . $conn;
		return "[ ok ] {$label} -> {$version}";
	}

	// ---- Mutating: apply. latest() only moves FORWARD (never down()). -----

	/** @return array<int,string> one result line per target */
	public function run(string $conn = 'default'): array
	{
		$lib = $this->_lib($conn);
		$out = [];

		foreach ($this->_discover_targets($conn) as $t) {
			if (empty($t['files'])) {
				continue;
			}
			$lib->set_path($t['path']);
			$lib->set_migration_key($t['key']);

			if ($lib->latest() === false) {
				$out[] = "[FAIL] {$t['label']} -> " . $lib->error_string();
				continue;
			}
			$numbers = array_keys($t['files']);
			$out[]   = "[ ok ] {$t['label']} -> " . (int) end($numbers);
		}

		return $out;
	}

	/**
	 * Migrate a SINGLE target to an explicit version.
	 *
	 * WARNING: a $version below the target's current version runs that target's
	 * down() migrations — destructive and usually irreversible. Inspect plan() first.
	 *
	 * @param ?string $key null = application, or a module key from plan()
	 */
	public function migrate_target(string $conn, ?string $key, string $version): string
	{
		$lib  = $this->_lib($conn);
		$path = null;
		$label = ($key ?? 'application') . ':' . $conn;

		foreach ($this->_discover_targets($conn) as $t) {
			if ($t['key'] === $key) {
				$path = $t['path'];
				break;
			}
		}
		if ($path === null) {
			return "[WARN] {$label} -> not found";
		}

		$lib->set_path($path);
		$lib->set_migration_key($key);

		return ($lib->version($version) === false)
			? "[FAIL] {$label}:{$conn} -> " . $lib->error_string()
			: "[ ok ] {$label}:{$conn} -> {$version}";
	}

	// ---- Internals --------------------------------------------------------

	/**
	 * Application first, then every module with a migrations/<conn> dir.
	 * @return array<int,array{key:?string,label:string,path:string,files:array<int,string>}>
	 */
	protected function _discover_targets(string $conn): array
	{
		// Application. CONFIRM this matches your layout — mirrors MX_Migration's
		// "APPPATH/database/{migration_path}" convention.
		$app_path = APPPATH . 'database/migrations/' . $conn . '/';
		$targets  = [[
			'key'   => null,
			'label' => 'application'. ':' . $conn,
			'path'  => $app_path,
			'files' => is_dir($app_path) ? $this->_scan($app_path) : [],
		]];

		if (class_exists('Modules') && ! empty(Modules::$locations)) {
			$modules = [];
			foreach (Modules::$locations as $location => $offset) {
				foreach (glob($location . '*', GLOB_ONLYDIR) ?: [] as $dir) {
					$dir = rtrim($dir, '/');
					$mig = $dir . '/migrations/' . $conn . '/';
					if (! is_dir($mig)) {
						continue;
					}
					$files = $this->_scan($mig);
					if (! $files) {
						continue;
					}
					$key = $this->_derive_module_key($dir, $location, $offset);
					$label = basename($dir). ':' . $conn;
					$modules[$key] = ['key' => $key, 'label' => $label, 'path' => $mig, 'files' => $files];
				}
			}
			ksort($modules);
			$targets = array_merge($targets, array_values($modules));
		}

		return $targets;
	}

	/** (offset + module name), with ../ removed. */
	protected function _derive_module_key(string $module_directory, string $module_location, string $offset): string
	{
		$name = ltrim(substr($module_directory, strlen(rtrim($module_location, '/'))), '/');
		return str_replace('../', '', rtrim($offset, '/') . '/' . $name);
	}

	/** @return array<int,string> [number => absolute path], sorted */
	protected function _scan(string $path): array
	{
		$out = [];
		foreach (glob(rtrim($path, '/') . '/*_*.php') ?: [] as $file) {
			if (preg_match('/^(\d+)_/', basename($file, '.php'), $m)) {
				$out[(int) $m[1]] = $file; // 64-bit assumed for 14-digit timestamps
			}
		}
		ksort($out);
		return $out;
	}

	/** Read current versions. Read-only; tolerant of missing tables. */
	protected function _read_versions(string $conn): array
	{
		$db = $this->_setup_database_connection($conn);

		$this->CI->config->load('migration', false, true);
		$main   = $this->CI->config->item('migration_table') ?: 'migrations';
		$modtbl = $main . '_path';

		$app = 0;
		if ($db->table_exists($main)) {
			$row = $db->get($main)->row();
			$app = $row ? (int) $row->version : 0;
		}

		$mods = [];
		if ($db->table_exists($modtbl)) {
			foreach ($db->get($modtbl)->result() as $r) {
				$mods[$r->module] = (int) $r->version;
			}
		}

		return ['app' => $app, 'modules' => $mods];
	}

	/** Load the library once; reconfigured per target via setters. */
	protected function _lib(string $conn): object
	{
		if (isset($this->_libs[$conn])) {
			return $this->_libs[$conn];
		}

		$name = 'migration_' . $conn;
		$this->CI->load->library('migration', [
			'migration_path' => APPPATH . 'database/migrations/' . $conn,
			'db_group'       => $conn,
		], $name);

		return $this->_libs[$conn] = $this->CI->{$name};
	}

	protected function _setup_database_connection(string $conn)
	{
		// Load the specified database connection
		$CI = &get_instance();
		$CI->db = $CI->load->database($conn, true);


		// Override the default db and dbforge with our connection
		// $CI->load->dbforge($CI->db);

		return $CI->db;
	}
}
