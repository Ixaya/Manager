<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once dirname(__FILE__) . '/../MGR_Migration_builder.php';

class MGR_Migration extends CI_Migration
{
	/**
	 * Database connection group
	 *
	 * @var string
	 */
	protected string $_db_group = 'default';

	/**
	 * Storage key for version tracking.
	 *   null      -> legacy single-row behaviour in the main migrations table
	 *   <string>  -> one row per module in the *_path table, scoped by `module`
	 *
	 * @var string|null
	 */
	protected $_migration_key = null;

	/**
	 * Secondary table holding per-module versions. Derived from the main
	 * migration table name in the constructor (e.g. 'migrations' -> 'migrations_path').
	 *
	 * @var string|null
	 */
	protected ?string $_migration_table_module = null;

	/**
	 * Initialize Migration Class with database connection support
	 *
	 * @param array $config
	 * @return void
	 */
	public function __construct($config = [])
	{
		// Merge in this order: config file -> custom config
		$base_config = $this->config->read('migration');
		$config = array_merge($base_config, $config);

		// Extract database connection info before parent constructor
		if (isset($config['db_group'])) {
			$this->_db_group = $config['db_group'];
			unset($config['db_group']); // Remove so parent doesn't try to set it

			$this->_setup_database_connection();
		}

		// Call parent constructor
		parent::__construct($config);

		$this->_setup_database_table();
	}

	/**
	 * Set up database connection
	 *
	 * @return void
	 */
	protected function _setup_database_connection()
	{
		// Load the specified database connection
		$CI = &get_instance();
		$CI->db = $this->load->database($this->_db_group, true);


		// Override the default db and dbforge with our connection
		$this->load->dbforge($this->db);
	}
	protected function _setup_database_table(bool $check_parent_table = false)
	{

		if (!empty($this->_migration_table)) {
			$this->_migration_table_module = "{$this->_migration_table}_path";
		}

		if (! $this->db->table_exists($this->_migration_table_module)) {
			$this->dbforge->add_field([
				'version' => ['type' => 'BIGINT', 'constraint' => 20],
				'module' => ['type' => 'VARCHAR', 'constraint' => 200, 'unique' => true],
			]);

			$this->dbforge->create_table($this->_migration_table_module, true);
		}

		if ($check_parent_table && ! $this->db->table_exists($this->_migration_table)) {
			$this->dbforge->add_field([
				'version' => ['type' => 'BIGINT', 'constraint' => 20],
			]);

			$this->dbforge->create_table($this->_migration_table, true);

			$this->db->insert($this->_migration_table, ['version' => 0]);
		}
	}

	/**
	 * Get current database group
	 *
	 * @return string
	 */
	public function get_db_group()
	{
		return $this->_db_group;
	}

	public function set_db_group(string $db_group): void
	{
		$this->_db_group = $db_group;

		$this->_setup_database_connection();
		$this->_setup_database_table(check_parent_table:true);
	}

	/**
	 * Point the library at a single absolute migration directory.
	 *
	 * @param  string $absolute Absolute path to a migrations/<conn> directory
	 * @return void
	 */
	public function set_path(string $absolute): void
	{
		$this->_migration_path = rtrim($absolute, '/') . '/';
	}

	/**
	 * Scope subsequent version reads/writes to a module key.
	 * Pass null to restore legacy single-row tracking (application migrations).
	 *
	 * @param  string|null $key Module key from the module, or null
	 * @return void
	 */
	public function set_migration_key(?string $key): void
	{
		$this->_migration_key = $key;
	}

	public function version_set(string $version): void
	{
		$this->_update_version($version);
	}

	// -------------------------------------------------------------------------
	// Storage overrides — key-aware version tracking
	// -------------------------------------------------------------------------

	/**
	 * Retrieves current schema version for the active key.
	 *
	 * key null -> main table, single row (legacy CI behaviour, untouched).
	 * key set  -> *_path table, the row WHERE module = key (0 if absent).
	 *
	 * @return string Current migration version
	 */
	protected function _get_version()
	{
		if ($this->_migration_key === null) {
			$row = $this->db->select('version')->get($this->_migration_table)->row();
			return $row ? $row->version : '0';
		}

		$row = $this->db
			->select('version')
			->where('module', $this->_migration_key)
			->get($this->_migration_table_module)
			->row();

		return $row ? $row->version : '0';
	}

	/**
	 * Stores the current schema version for the active key.
	 *
	 * key null -> UPDATE the single main-table row (legacy behaviour).
	 * key set  -> UPSERT into the *_path table: a module's first run has no row,
	 *             so a bare UPDATE would store nothing and the module would
	 *             re-run on every invocation. INSERT-or-UPDATE on the unique
	 *             `module` column fixes that.
	 *
	 * @param  string $migration Migration version reached
	 * @return void
	 */
	protected function _update_version($migration)
	{
		if ($this->_migration_key === null) {
			$this->db->update($this->_migration_table, ['version' => $migration]);
			return;
		}

		$exists = (bool) $this->db
			->where('module', $this->_migration_key)
			->count_all_results($this->_migration_table_module);

		if ($exists) {
			$this->db
				->where('module', $this->_migration_key)
				->update($this->_migration_table_module, ['version' => $migration]);
		} else {
			$this->db->insert($this->_migration_table_module, [
				'module'  => $this->_migration_key,
				'version' => $migration,
			]);
		}
	}
}
