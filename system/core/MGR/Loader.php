<?php

defined('BASEPATH') or exit('No direct script access allowed');

/* load the MX_Loader class */
require dirname(__FILE__) . "/../../third_party/MX/Loader.php";

class MGR_Loader extends MX_Loader
{
	/**
	 * Register a package as a FALLBACK location rather than an overlay.
	 *
	 * CI3 gives a package higher priority than the application, which is
	 * right for a drop-in add-on and inverted for a framework distribution:
	 * it makes every project file the package also ships unreachable. Here
	 * the application keeps priority and the package sits between it and
	 * BASEPATH.
	 *
	 * @param string $path         Package root path
	 * @param bool   $view_cascade Whether views under this package cascade
	 */
	public function add_package_path($path, $view_cascade = true): static
	{
		$path = rtrim($path, '/') . '/';

		$this->_ci_library_paths = $this->_insert_before_basepath($this->_ci_library_paths, $path);
		$this->_ci_helper_paths = $this->_insert_before_basepath($this->_ci_helper_paths, $path);
		$this->_ci_model_paths[] = $path;
		$this->_ci_view_paths[$path . 'views/'] = $view_cascade;

		// _config_paths order is left untouched — see
		// MGR_Config::_cascade_paths() for why.
		/** @var CI_Config $config */
		$config = &$this->_ci_get_component('config');
		$config->_config_paths[] = $path;

		return $this;
	}

	/**
	 * Remove a package path, resolving the no-argument form by value.
	 *
	 * @param string $path Package root path, or '' for the most recently added
	 */
	public function remove_package_path($path = ''): static
	{
		if ($path === '') {
			/** @var CI_Config $config */
			$config = &$this->_ci_get_component('config');

			// CI3's no-argument branch shifts index 0 off each path array,
			// which is the application path under this class's ordering.
			// _config_paths still appends, so its last entry names the package
			// to remove, and the explicit-path branch removes it by value.
			$path = end($config->_config_paths);

			// Only APPPATH left means no package is registered; CI3 would
			// still shift, leaving the defaults reordered.
			if ($path === false || $path === APPPATH) {
				return $this;
			}
		}

		parent::remove_package_path($path);

		return $this;
	}

	/**
	 * Insert a path immediately before BASEPATH, or append it when the array
	 * carries no BASEPATH entry.
	 *
	 * @param array<int, string> $paths
	 * @return array<int, string>
	 */
	protected function _insert_before_basepath(array $paths, string $path): array
	{
		$position = array_search(BASEPATH, $paths, true);

		if ($position === false) {
			$paths[] = $path;

			return $paths;
		}

		array_splice($paths, $position, 0, [$path]);

		return $paths;
	}

	/**
	 * Load every application/package/base layer that defines a helper,
	 * instead of CI3's first-match-wins search. function_exists() guards
	 * make this first-wins by declaration order, so _ci_helper_paths (now
	 * [APPPATH, package, BASEPATH]) is walked forward, application first.
	 */
	protected function helper_fallback(string $helper): static
	{
		$filename = basename($helper);
		$filepath = ($filename === $helper) ? '' : substr($helper, 0, strlen($helper) - strlen($filename));
		$filename = strtolower(preg_replace('#(_helper)?(\.php)?$#i', '', $filename)) . '_helper';
		$helper = $filepath . $filename;

		if (isset($this->_ci_helpers[$helper])) {
			return $this;
		}

		// Is this a helper extension request?
		$ext_helper = config_item('subclass_prefix') . $filename;
		$ext_loaded = false;

		foreach ($this->_ci_helper_paths as $path) {
			if (file_exists($path . 'helpers/' . $ext_helper . '.php')) {
				include_once $path . 'helpers/' . $ext_helper . '.php';
				$ext_loaded = true;
			}
		}

		// If we have loaded extensions - check if the base one is here
		if ($ext_loaded === true) {
			$base_helper = BASEPATH . 'helpers/' . $helper . '.php';

			if (! file_exists($base_helper)) {
				show_error('Unable to load the requested file: helpers/' . $helper . '.php');
			}

			include_once $base_helper;
			$this->_ci_helpers[$helper] = true;
			log_message('info', 'Helper loaded: ' . $helper);

			return $this;
		}

		foreach ($this->_ci_helper_paths as $path) {
			if (file_exists($path . 'helpers/' . $helper . '.php')) {
				include_once $path . 'helpers/' . $helper . '.php';

				$this->_ci_helpers[$helper] = true;
				log_message('info', 'Helper loaded: ' . $helper);
			}
		}

		if (! isset($this->_ci_helpers[$helper])) {
			show_error('Unable to load the requested file: helpers/' . $helper . '.php');
		}

		return $this;
	}

	/**
	 * Load the database forge, preferring `core/MGR/Forge/MGR_DB_<rest>.php` named after the vendor
	 * forge's class (`CI_DB_pdo_pgsql_forge` -> `MGR_DB_pdo_pgsql_forge`); the vendor forge otherwise.
	 *
	 * @param object|null $db     Database object; the active connection when omitted
	 * @param bool        $return Return the forge instead of assigning `$CI->dbforge`
	 */
	public function dbforge($db = null, $return = false)
	{
		$CI = &get_instance();
		$forge = parent::dbforge($db, true);

		$forge_class = 'MGR_DB_' . substr(get_class($forge), strlen('CI_DB_'));
		$forge_file = MGRPATH . 'core/MGR/Forge/' . $forge_class . '.php';

		if (is_file($forge_file)) {
			require_once $forge_file;
			$connection = $db instanceof CI_DB ? $db : $CI->db;
			$forge = new $forge_class($connection);
		}

		if ($return === true) {
			return $forge;
		}

		$CI->dbforge = $forge;

		return $this;
	}

	/** Load a module view **/
	public $header_vars = [];
	public function view($view, $vars = [], $return = false)
	{
		if ($this->header_vars !== []) {
			$vars = array_merge($vars, $this->header_vars);
		}

		list($path, $_view) = Modules::find($view, $this->_module, 'views/');

		if ($path != false) {
			$this->_ci_view_paths = [$path => true] + $this->_ci_view_paths;
			$view = $_view;
		}

		return $this->_ci_load(['_ci_view' => $view, '_ci_vars' => ((method_exists($this, '_ci_object_to_array')) ? $this->_ci_object_to_array($vars) : $this->_ci_prepare_view_vars($vars)), '_ci_return' => $return]);
	}

	protected $_db_cache = [];

	/** @var array<string, array{offset: string, link: ?WeakReference, backend_pid: ?int}> what each $_db_cache entry's session was last sent */
	protected array $_db_cache_sessions = [];

	/**
	 * Returns a shared handle per group name; '' is the main handle. A DSN string or array is never cached.
	 *
	 * @throws RuntimeException when the server rejects PHP's timezone offset as the session timezone
	 */
	public function &database_cache($params = '', $query_builder = null)
	{
		if (empty($params)) {
			// Adopted or loaded once; a later replacement of $CI->db (the migration runner) must not move cache users.
			if (!isset($this->_db_cache[''])) {
				$this->database();
				$this->_db_cache[''] = CI::$APP->db;
			}

			// On every hit too, to catch a reconnect or a raw date_default_timezone_set(); a synced hit sends nothing.
			$this->_apply_session_time_zone('');
			return $this->_db_cache[''];
		}

		// Return from array if a DSN string wasn't passed
		if (is_string($params) && strpos($params, '://') === false) {
			if (!(isset($this->_db_cache[$params]) && is_object($this->_db_cache[$params]) && ! empty($this->_db_cache[$params]))) {
				$this->_db_cache[$params] = $this->database($params, true, $query_builder);
			}

			$this->_apply_session_time_zone($params);
			return $this->_db_cache[$params];
		}

		// Untracked: sync_session_time_zone() never reaches it.
		$db     = $this->database($params, true, $query_builder);
		$offset = mgr_get_time_zone_offset(date_default_timezone_get());
		if (is_string($offset)) {
			// A DSN string carries the password: never let it reach a log line.
			$this->_send_session_time_zone(db: $db, offset: $offset, label: 'dsn-generic');
		}

		return $db;
	}

	/**
	 * Re-sends PHP's current timezone offset to every cached handle whose session holds another one.
	 *
	 * @throws RuntimeException when a server rejects the offset
	 */
	public function sync_session_time_zone(): void
	{
		foreach (array_keys($this->_db_cache) as $key) {
			$this->_apply_session_time_zone((string) $key);
		}
	}

	/**
	 * Sends PHP's current timezone offset to a cache entry's session unless already sent on its current link.
	 */
	protected function _apply_session_time_zone(string $key): void
	{
		$offset = mgr_get_time_zone_offset(date_default_timezone_get());
		if (!is_string($offset)) {
			return;
		}

		$db      = $this->_db_cache[$key];
		$session = $this->_db_cache_sessions[$key] ?? null;
		// A WeakReference, not spl_object_id(): a freed link's id is reused by the next one. The pid
		// catches native postgre's reconnect(), whose pg_ping() can reset the same link to a new backend.
		if ($session !== null && $session['offset'] === $offset && $session['link']?->get() === $db->conn_id
			&& $session['backend_pid'] === $this->_postgre_backend_pid($db)) {
			return;
		}

		if (!$this->_send_session_time_zone(db: $db, offset: $offset, label: $key)) {
			return;
		}

		// PostgreSQL undoes a SET on rollback: unrecorded, every access re-sends until one lands outside the
		// transaction. Only CI's trans_begin()/trans_start() are seen, not a raw BEGIN.
		if ($db->trans_active() && MgrDriver::fromCI($db->dbdriver ?? '', subdriver: $db->subdriver ?? null) === MgrDriver::Postgres) {
			unset($this->_db_cache_sessions[$key]);
			return;
		}

		$this->_db_cache_sessions[$key] = [
			'offset'      => $offset,
			'link'        => is_object($db->conn_id) ? WeakReference::create($db->conn_id) : null,
			'backend_pid' => $this->_postgre_backend_pid($db),
		];
	}

	/** Native postgre's backend pid, read client-side (no round trip); null on every other driver. */
	protected function _postgre_backend_pid(object $db): ?int
	{
		return $db->conn_id instanceof PgSql\Connection ? pg_get_pid($db->conn_id) : null;
	}

	/**
	 * Sets $offset as $db's session timezone; true when sent, or on SQLite/SQL Server, which have none.
	 * False (logged) when the link is unusable, so the caller retries on the next access.
	 *
	 * @throws RuntimeException when the server is reachable but rejects the offset
	 */
	protected function _send_session_time_zone(object $db, string $offset, string $label): bool
	{
		$driver = MgrDriver::fromCI($db->dbdriver ?? '', subdriver: $db->subdriver ?? null);
		// $offset is ±HH:MM by construction; escape_str() would fatal on an unconnected PDO handle.
		$sql = match ($driver) {
			MgrDriver::MySQL,
			MgrDriver::MariaDB  => "SET SESSION time_zone = '{$offset}'",
			// Without INTERVAL, Postgres parses a bare offset string as a
			// POSIX-style spec, which inverts the sign vs. our ISO offset.
			MgrDriver::Postgres => "SET TIME ZONE INTERVAL '{$offset}' HOUR TO MINUTE",
			default             => null,
		};

		if ($sql === null || $db->query($sql) !== false) {
			return true;
		}

		// An unusable link is transient (retried on the next access); a working one means the server rejected the offset.
		if (!$db->conn_id || $db->query('SELECT 1') === false) {
			log_message('error', "MGR_Loader: unable to set the session time zone to {$offset} on database connection '{$label}'.");
			return false;
		}

		throw new RuntimeException("MGR_Loader: the database server rejected session time zone {$offset} on database connection '{$label}'; PHP's time zone cannot be mirrored on this engine.");
	}

	/**
	 * Get the full file path of a config file
	 * Wrapper for CI::$APP->config->path() for consistency with load->config() pattern
	 *
	 * @param string $file Config filename (without .php)
	 * @return string|null Full file path or null if not found
	 */
	public function config_path($file): ?string
	{
		return CI::$APP->config->path($file, $this->_module);
	}

	/**
	 * Read config file and return array without loading into config system
	 * Perfect for sensitive configs - read once, use immediately, let it go out of scope
	 *
	 * @param string $file Config filename (without .php)
	 * @return array|null Config array or null if not found
	 */
	public function config_read($file): ?array
	{
		return CI::$APP->config->read($file, $this->_module);
	}

	/** Load a module library **/
	public function library($library, $params = null, $object_name = null)
	{
		if (is_array($library)) {
			return $this->libraries($library);
		}

		if ($params == null) {
			$params = $this->config_read($library);
		}

		return parent::library($library, $params, $object_name);
	}
}
