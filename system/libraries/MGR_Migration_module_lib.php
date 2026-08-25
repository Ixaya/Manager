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

	protected bool $_force_db_debug = false;

	public function __construct()
	{
		$this->CI = & get_instance();
	}

	/**
	 * Forces db_debug on for every connection this instance opens from now on,
	 * regardless of the app's own setting.
	 */
	public function force_db_debug(): void
	{
		$this->_force_db_debug = true;
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

	// ---- Read-only: version inspection per connection ----
	/** @return array<int, string> Formatted as "key conn" */
	public function version_list(string $conn): array
	{
		$out = [];
		foreach ($this->_discover_targets($conn) as $t) {
			if (empty($t['files'])) {
				continue;
			}
			$key_cli = $t['key'] !== null ? str_replace('/', ':', $t['key']) : 'app';
			$out[]   = "tools version_list {$key_cli} {$conn}";
		}
		return $out;
	}

	/** @return array<int, string> Filenames, with "(current)" on the active version */
	public function version_list_files(string $conn, ?string $key): array
	{
		$target = null;
		foreach ($this->_discover_targets($conn) as $t) {
			if ($t['key'] === $key) {
				$target = $t;
				break;
			}
		}

		if ($target === null) {
			$label = ($key ?? 'app') . ':' . $conn;
			return ["[WARN] {$label} -> not found"];
		}

		$versions = $this->_read_versions($conn);
		$current  = $key === null
			? $versions['app']
			: ($versions['modules'][$key] ?? 0);

		$out = [];
		foreach ($target['files'] as $number => $file) {
			$name  = basename($file);
			$out[] = $number === (int) $current ? "{$name} (current)" : $name;
		}

		if ((int) $current === 0 && !empty($target['files'])) {
			$key_cli  = $key !== null ? str_replace('/', ':', $key) : 'app';
			$latest   = array_key_last($target['files']);
			$out[]    = '';
			$out[]    = "  hint: if already applied -> tools version_set {$latest} {$key_cli} {$conn}";
		}

		return $out;
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
	 * Migrate a SINGLE target.
	 *
	 * WARNING: an explicit $version below the target's current version runs that
	 * target's down() migrations — destructive and usually irreversible. Inspect
	 * plan() first. $version === null sidesteps this entirely: it resolves to the
	 * target's own latest file and only ever calls latest() (same as run(), just
	 * scoped to one target), so it can never trigger a downgrade.
	 *
	 * @param ?string $key null = application, or a module key from plan()
	 * @param ?string $version Exact target version, or null for "this target's
	 *   own latest" — $confirm_downgrade is irrelevant and ignored on that path.
	 * @param bool $confirm_downgrade Required true when $version is below the
	 *   target's current recorded version — otherwise refuses without touching
	 *   the database, per the WARNING above.
	 */
	public function migrate_target(string $conn, ?string $key, ?string $version = null, bool $confirm_downgrade = false): string
	{
		$lib    = $this->_lib($conn);
		$label  = ($key ?? 'application') . ':' . $conn;
		$target = null;

		foreach ($this->_discover_targets($conn) as $t) {
			if ($t['key'] === $key) {
				$target = $t;
				break;
			}
		}
		if ($target === null) {
			return "[WARN] {$label} -> not found";
		}

		$lib->set_path($target['path']);
		$lib->set_migration_key($key);

		if ($version === null) {
			if ($lib->latest() === false) {
				return "[FAIL] {$label} -> " . $lib->error_string();
			}
			$numbers = array_keys($target['files']);
			return "[ ok ] {$label} -> " . (int) end($numbers);
		}

		$versions = $this->_read_versions($conn);
		$current  = $key === null ? $versions['app'] : ($versions['modules'][$key] ?? 0);

		if ((int) $version < $current && !$confirm_downgrade) {
			return "[FAIL] {$label} -> {$version} is below the current version ({$current}) and would run down() — pass confirm_downgrade to proceed.";
		}

		return ($lib->version($version) === false)
			? "[FAIL] {$label} -> " . $lib->error_string()
			: "[ ok ] {$label} -> {$version}";
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
					// basename($dir) alone collides when an app-level module shadows a
					// vendor/package one of the same name — use the full key (':'-joined,
					// matching the CLI's module_key form) so the label stays unambiguous.
					$label = str_replace('/', ':', $key) . ':' . $conn;
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
			// Anchored to exactly 14 digits to match CI_Migration's own
			// find_migrations() regex ('/^\d{14}_(\w+)$/') — a looser count here
			// let plan()/version_list() show a file as pending that latest()
			// then silently excludes, surfacing only as "No migrations were found."
			if (preg_match('/^(\d{14})_/', basename($file, '.php'), $m)) {
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

		$lib = $this->_libs[$conn] = $this->CI->{$name};

		if ($this->_force_db_debug) {
			$lib->db->db_debug = true;
		}

		return $lib;
	}

	protected function _setup_database_connection(string $conn)
	{
		// Load the specified database connection
		$CI = &get_instance();
		$CI->db = $CI->load->database($conn, true);

		if (!is_object($CI->db) || !$CI->db->conn_id) {
			throw new RuntimeException("MGR_Migration_module_lib: unable to connect to database connection '{$conn}'.");
		}

		if ($this->_force_db_debug) {
			$CI->db->db_debug = true;
		}

		// Override the default db and dbforge with our connection
		// $CI->load->dbforge($CI->db);

		return $CI->db;
	}
}
