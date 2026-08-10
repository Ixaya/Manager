<?php

defined('BASEPATH') or exit('No direct script access allowed');

require MGRPATH . 'core/MGR/Model/Sync.php';
require MGRPATH . 'core/MGR/Model/Upsert_Replace.php';

class MGR_Model extends CI_Model
{
	use MGR_Model_Sync;
	use MGR_Model_Upsert_Replace;

	protected ?object $my_db = null;
	protected MgrDriver $my_db_driver;

	protected string $table_name = '';
	protected string $primary_key = 'id';
	protected string $database_name = '';

	protected string $connection_name = '';

	//example: $where_override = array('client_id' => $this->override_id);
	//example: $override_column = 'client_id';
	//example: $override_id = 1;
	protected ?array $where_override = null;
	protected ?string $override_column = null;
	protected ?string $override_id = null;

	protected bool $save_history = false;
	protected bool $soft_delete = false;
	protected bool $use_last_update = true;

	protected bool $lazy_connect = false;
	protected bool $connected = false;

	protected bool $legacy_mode = false;

	/** Connects immediately unless $lazy_connect is set. */
	public function __construct()
	{
		// $this->load->helper('inflector');

		parent::__construct();

		if (!$this->lazy_connect) {
			$this->connect();
		}
	}

	/**
	 * Opens the DB connection (by name, $this->connection_name, or the
	 * default group), resolves $my_db_driver, and applies the session
	 * timezone and override filter.
	 */
	public function connect(?string $connection_name = null): void
	{
		if ($connection_name) {
			$this->connection_name = $connection_name;
		}

		if (mgr_provided($this->connection_name)) {
			$this->my_db = $this->load->database_cache($this->connection_name);
			$this->my_db_driver = MgrDriver::fromCI($this->my_db->dbdriver ?? '', subdriver: $this->my_db->subdriver ?? null);
		} else {
			$this->my_db = $this->load->database_cache();
			$this->my_db_driver = MgrDriver::fromCI($this->my_db->dbdriver ?? '', subdriver: $this->my_db->subdriver ?? null);
		}

		if (strlen($this->database_name)) {
			$this->my_db->db_select($this->database_name);
			//log_message('info', 'Connecting to: '.$this->database_name);
		}

		if (!$this->table_name) {
			$this->generate_table_name();
		}

		$time_zone = date_default_timezone_get();
		$this->set_database_time_zone($time_zone);

		$this->set_override();
		$this->connected = true;
	}

	/**
	 * Injects an already-open connection instead of opening one via
	 * connect() — still resolves $my_db_driver and the override filter.
	 */
	public function set_connection(object $db_connection): void
	{
		$this->my_db = $db_connection;
		$this->my_db_driver = MgrDriver::fromCI($db_connection->dbdriver ?? '', subdriver: $db_connection->subdriver ?? null);

		if (!$this->table_name) {
			$this->generate_table_name();
		}

		$this->set_override();
		$this->connected = true;
	}

	/**
	 * Re-runs connect() only if $connection_name or $database_name
	 * actually changed from what's already active.
	 */
	public function reconnect_database(string $connection_name, string $database_name, bool $generate_table_name = false): void
	{
		$needs_reload = false;
		if ($database_name !== '' && $this->database_name != $database_name) {
			$this->database_name = $database_name;

			$needs_reload = true;
		}

		if ($connection_name !== '' && $this->connection_name != $connection_name) {
			$this->connection_name = $connection_name;

			$needs_reload = true;
		}

		if ($needs_reload) {
			$this->connect($connection_name);
		}

		if ($generate_table_name) {
			$this->generate_table_name();
		}
	}

	/** Connects if not already connected. */
	public function check_connect(): void
	{
		if (!$this->connected) {
			$this->connect();
		}
	}

	/** Derives the table name from this model's class name. */
	protected function generate_table_name(): void
	{
		$this->table_name = strtolower(get_class($this));
	}

	/** Sets the override column and immediately resolves set_override(). */
	public function set_override_column(string $column_name): void
	{
		$this->override_column = $column_name;
		$this->set_override();
	}

	/**
	 * Resolve/refresh $where_override from override_column + override_id.
	 */
	public function set_override(int|string|null $id = null): void
	{
		if (!$this->override_column) {
			return;
		}

		if ($id !== null) {
			$this->override_id = $id;
		}
		if ($this->override_id === null && isset($_SESSION[$this->override_column])) {
			$this->override_id = $_SESSION[$this->override_column];
		}
		if ($this->override_id !== null) {
			$this->where_override = ["{$this->table_name}.{$this->override_column}" => $this->override_id];
		}
	}

	/**
	 * Clear the current override. Pass $reset_column = false to keep
	 * override_column configured while resetting the id
	 */
	public function del_override(bool $reset_column = true): void
	{
		$this->where_override = null;

		$this->override_id = null;
		if ($reset_column) {
			$this->override_column = null;
		}
	}

	/**
	 * Fetches a single row by primary key.
	 *
	 * @return array<string, mixed>|null Associative array of the row, null if not found or query fails
	 */
	public function get(int|string $id, string|array|null $fields = null): ?array
	{
		$this->apply_common_filters($fields);

		$this->my_db->where($this->primary_key, $id);
		return $this->execute_row();
	}

	/**
	 * Fetches a single row matching arbitrary WHERE conditions.
	 *
	 * @return array<string, mixed>|null Associative array of the row, null if not found or query fails
	 */
	public function get_where(array $where, string|array|null $fields = null): ?array
	{
		$this->apply_common_filters($fields);

		$this->my_db->where($where);
		return $this->execute_row();
	}

	/**
	 * Gets MIN and MAX for a single field in one query.
	 *
	 * @return array<string, mixed>|null Keyed min_{$field_alias}/max_{$field_alias}, null if the query failed
	 */
	public function get_min_max(string $field, array $where = [], ?string $field_alias = null): ?array
	{
		if ($field_alias === null) {
			$field_alias = $field;
		}

		$fields = "MIN({$field}) as min_{$field_alias}, MAX({$field}) as max_{$field_alias}";

		$this->apply_common_filters($fields);

		if ($where !== []) {
			$this->my_db->where($where);
		}

		return $this->execute_row();
	}

	/** @return ?array Array of result rows, empty array if no results found, null if the query failed */
	public function get_all(string|array|null $fields = null, array $where = [], int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null): ?array
	{
		$this->apply_list_filters($fields, $where, $limit, $order_by, $group_by);

		return $this->execute_list();
	}

	/** @return ?array Array of result rows, empty array if no results found, null if the query failed */
	public function get_all_join(string|array|null $fields = null, array $where = [], int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null, ?string $join_table = null, ?string $join_where = null, string  $join_method = 'left'): ?array
	{
		$this->apply_list_filters($fields, $where, $limit, $order_by, $group_by);

		if ($join_table !== null && $join_where !== null) {
			$this->my_db->join($join_table, $join_where, $join_method);
		}

		return $this->execute_list();
	}

	/** @return ?array Array of result rows, empty array if no results found, null if the query failed */
	public function get_all_like(string|array|null $fields = null, array $where = [], int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null): ?array
	{
		$this->apply_list_filters($fields, [], $limit, $order_by, $group_by);

		if ($where !== []) {
			$this->my_db->like($where);
		}

		return $this->execute_list();
	}

	/** @return ?array Array of result rows, empty array if no results found, null if the query failed */
	public function get_all_or_like(string|array|null $fields = null, array $where = [], int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null): ?array
	{
		$this->apply_list_filters($fields, [], $limit, $order_by, $group_by);

		// Grouped so or_like()'s OR can't glue onto apply_list_filters()'s
		// implicit soft-delete/override_column conditions and defeat them.
		if ($where !== []) {
			$this->my_db->group_start();
			$this->my_db->or_like($where);
			$this->my_db->group_end();
		}

		return $this->execute_list();
	}

	/** @return ?array Array of result rows, empty array if no results found, null if the query failed */
	public function get_all_in(string $field, array $values, string|array|null $fields = null, int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null): ?array
	{
		$this->apply_list_filters($fields, [], $limit, $order_by, $group_by);

		if ($values !== []) {
			$this->my_db->where_in($field, $values);
		}

		return $this->execute_list();
	}

	/** @return ?array Array of result rows, empty array if no results found, null if the query failed */
	public function get_all_updated(string $last_update, string|array|null $fields = null, array $where = [], int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null): ?array
	{
		$where['last_update >'] = $last_update;
		return $this->get_all($fields, $where, $limit, $order_by, $group_by);
	}

	/** @return ?int Row count, null if the query failed — 0 means the table genuinely has no matching rows */
	public function count_all(?array $where = null): ?int
	{
		$this->apply_common_filters();


		$this->my_db->select('count(*) AS count', false);

		if (mgr_provided($where)) {
			$this->my_db->where($where);
		}

		$data = $this->execute_list();
		if ($data === null) {
			return null;
		}

		return (int)($data[0]['count'] ?? 0);
	}


	/** Inserts a new row, returns its id. */
	public function insert(array $data): int|string|bool
	{
		$this->check_connect();

		$this->set_alter_keys($data);

		if ($this->override_column && $this->override_id) {
			$data[$this->override_column] = $this->override_id;
		}

		if (!$this->my_db->insert($this->table_name, $data)) {
			return false;
		}

		return $this->insert_id();
	}

	/** Inserts every row in $rows in one batch, returns the affected count. */
	public function insert_bulk(array $rows): int
	{
		if ($rows === []) {
			return 0;
		}

		foreach ($rows as &$row) {
			$this->set_alter_keys($row);

			if ($this->override_column && $this->override_id) {
				$row[$this->override_column] = $this->override_id;
			}
		}

		unset($row);

		$this->my_db->insert_batch($this->table_name, $rows);

		return $this->my_db->affected_rows();
	}

	/** Updates by primary key — $id may be a single value, or an array to update every matching row. */
	public function update(array $data, int|string|array $id): bool
	{
		$this->apply_alter_filters();
		$this->set_alter_keys($data);

		if (is_array($id)) {
			$this->my_db->where_in($this->primary_key, $id);
		} else {
			$this->my_db->where($this->primary_key, $id);
		}

		return $this->my_db->update($this->table_name, $data);
	}

	/** Updates rows matching $where; refuses to run (returns false) if $where is empty. */
	public function update_where(array $data, array $where): bool
	{
		if ($where === []) {
			return false;
		}

		$this->apply_alter_filters();
		$this->set_alter_keys($data);

		$this->my_db->where($where);

		return $this->my_db->update($this->table_name, $data);
	}

	/** Inserts if $id is null, otherwise updates the row at $id — false if that id doesn't exist. */
	public function upsert(array $data, int|string|null $id = null): int|string|bool
	{
		if ($id === null) {
			return $this->insert($data);
		}

		$row = $this->get_where([$this->primary_key => $id]);

		if (empty($row)) {
			return false;
		}

		if ($this->update($data, $id)) {
			return $row[$this->primary_key];
		}

		return false;
	}

	/** Inserts if no row matches $where, otherwise updates the row found. */
	public function upsert_where(array $data, array $where, array $insert_data = []): int|string|bool
	{
		$row = $this->get_where($where);

		if (empty($row)) {
			return $this->insert(array_merge($data, $where, $insert_data));
		}

		if ($this->update($data, $row[$this->primary_key])) {
			return $row[$this->primary_key];
		}

		return false;
	}

	/**
	 * Trims string values in place; with $only_trim false, also nullifies
	 * values that are "empty" but not the literal 0.
	 */
	public function cleanup_columns(array &$data, bool $only_trim = false): void
	{
		foreach ($data as &$row) {
			if (is_string($row)) {
				$row = trim($row);
			}

			if (!$only_trim && $row != 0 && empty($row)) {
				$row = null;
			}
		}
	}

	/** Deletes by primary key, or soft-deletes (flags deleted/enabled) if $soft_delete is set. */
	public function delete(int|string $id): bool
	{
		$this->apply_alter_filters();

		$this->my_db->where($this->primary_key, $id);

		if ($this->soft_delete === false) {
			return $this->my_db->delete($this->table_name);
		}

		$data = [];
		$this->set_alter_keys(data: $data, delete: true);

		return $this->my_db->update($this->table_name, $data);
	}

	/** Deletes rows matching $where (or soft-deletes); refuses to run if $where is empty. */
	public function delete_where(array $where): bool
	{
		$this->check_connect();

		if ($where === []) {
			return false;
		}

		$this->apply_alter_filters();
		$this->my_db->where($where);

		if ($this->soft_delete === false) {
			return $this->my_db->delete($this->table_name);
		}

		$data = [];
		$this->set_alter_keys($data, $delete = true);

		return $this->my_db->update($this->table_name, $data);
	}

	/** Runs raw SQL — result rows for a SELECT, affected-row count for a write, false on failure. */
	public function query(string $query, ?array $arguments = null): array|int|false
	{
		$this->check_connect();

		$Q = $this->my_db->query($query, $arguments);

		if (is_object($Q)) {
			$data = $Q->result_array();
			$Q->free_result();
			return $data;
		}

		if ($Q === true) {
			return $this->my_db->affected_rows();
		}

		return false;
	}

	/**
	 * Builds a blank row keyed by the table's real columns (or $properties
	 * if given), every value ''.
	 */
	public function empty_row(?array $properties = null, bool $include_id = true): array
	{
		$this->check_connect();

		if ($properties === null) {
			$properties = $this->my_db->list_fields($this->table_name);
			$properties = array_flip($properties);

			if (!$include_id && isset($properties[$this->primary_key])) {
				unset($properties[$this->primary_key]);
			}
		}

		return array_fill_keys(array_keys($properties), '');
	}

	/** empty_row(), as an object. */
	public function empty_object(?array $properties = null, bool $include_id = true): object
	{
		return (object) $this->empty_row($properties, $include_id);
	}

	/** Slugifies $text: strips accents/punctuation, lowercases, underscores separators. */
	public function clean_string(string $text): string
	{
		$utf8 = [
			'/[áàâãªäÁÀÂÃªÄ]/u'	=>	 'a',
			'/[íìîïÍÌÎÏ]/u'		=>	 'i',
			'/[éèêëÉÈÊË]/u'		=>	 'e',
			'/[óòôõºöÓÒÔÕºÖ]/u'	=>	 'o',
			'/[úùûüÚÙÛÜ]/u'		=>	 'u',
			'/[çÇ]/u'			=>	 'c',
			'/[ñÑ]/u'			=>	 'n',
			'/-/'				=>	 '_', // UTF-8 hyphen to "normal" hyphen
			'/[’‘‹›‚]/u'		=>	 '_', // Literally a single quote
			'/[“”«»„]/u'		=>	 '_', // Double quote
			'/ /'				=>	 '_', // nonbreaking space (equiv. to 0x160)
		];

		$clean = preg_replace(array_keys($utf8), array_values($utf8), rtrim($text)); //Remove right spaces and convert special letters
		$clean = strtolower($clean); //Convert to lower case

		return preg_replace("/[^A-Za-z0-9_]/", '', $clean); // Remove special characters
	}

	/** Generates a random hash string. */
	public function get_hash(int $length = 13): string
	{
		return mgr_generate_hash($length);
	}

	/** Generates a hash not already present in $field, giving up after 25 attempts. */
	public function get_unique_hash(int $length = 13, string $field = 'hash'): ?string
	{
		for ($i = 0; $i < 25; $i++) {
			$hash = mgr_generate_hash($length);
			$row = $this->by_hash($hash, $field);

			if (empty($row)) {
				return $hash;
			}
		}

		return null;
	}

	/**
	 * Fetches a single row by an arbitrary field, defaulting to 'hash'.
	 *
	 * @return array<string, mixed>|null Associative array of the row, null if not found or query fails
	 */
	public function by_hash(string $hash, string $field = 'hash'): ?array
	{
		return $this->get_where([$field => $hash]);
	}

	/** Echoes the last query, or logs and returns it if $return is true. */
	public function debug_query(bool $return = false): ?string
	{
		$last_query = $this->my_db->last_query();
		if ($return) {
			log_message('debug', $last_query);
			return $last_query;
		}

		echo $last_query;
		return null;
	}

	/** Sets the DB session's timezone — a no-op on SQLite/SQL Server, which have no session TZ concept. */
	public function set_database_time_zone(string $time_zone): void
	{
		$offset = mgr_get_time_zone_offset($time_zone);

		if ($offset === false) {
			return;
		}

		$offset = $this->my_db->escape_str($offset);

		$sql = match ($this->my_db_driver) {
			MgrDriver::MySQL,
			MgrDriver::MariaDB  => "SET SESSION time_zone = '{$offset}'",
			// Without INTERVAL, Postgres parses a bare offset string as a
			// POSIX-style spec, which inverts the sign vs. our ISO offset.
			MgrDriver::Postgres => "SET TIME ZONE INTERVAL '{$offset}' HOUR TO MINUTE",
			default             => null,   // SQLite, SQL Server — no session TZ concept
		};

		if ($sql !== null) {
			$this->my_db->query($sql);
		}
	}

	/**
	 * Applies the override filter and soft-delete filter shared by every
	 * query, plus an optional field selection.
	 */
	protected function apply_common_filters(string|array|null $fields = null): void
	{
		$this->check_connect();

		if (mgr_provided($fields)) {
			$this->my_db->select($fields);
		}

		if ($this->where_override) {
			$this->my_db->where($this->where_override);
		}

		if ($this->soft_delete) {
			$this->my_db->where("{$this->table_name}.deleted", 0);
		}
	}

	/**
	 * apply_common_filters() plus WHERE/limit/order/group — shared by the
	 * get_all* family.
	 */
	protected function apply_list_filters(string|array|null $fields = null, array $where = [], int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null): void
	{
		$this->apply_common_filters($fields);

		if ($where !== []) {
			$this->my_db->where($where);
		}

		if (mgr_provided($limit)) {
			if (is_array($limit)) {
				$this->my_db->limit((int) $limit[0], (int) ($limit[1] ?? 0));
			} else {
				$this->my_db->limit((int) $limit);
			}
		}

		if (mgr_provided($order_by)) {
			$this->my_db->order_by($order_by);
		}

		if (mgr_provided($group_by)) {
			$this->my_db->group_by($group_by);
		}
	}

	/** Applies the override filter for UPDATE/DELETE operations. */
	protected function apply_alter_filters(): void
	{
		$this->check_connect();

		if ($this->where_override) {
			$this->my_db->where($this->where_override);
		}
	}

	/**
	 * Stamps last_update (and, on $delete, the soft-delete flags) onto a
	 * write payload in place.
	 *
	 * @param array<string, mixed> $data Write payload, passed by reference and modified in place.
	 */
	protected function set_alter_keys(array &$data, bool $delete = false): void
	{
		if ($this->use_last_update) {
			$data['last_update'] = date('Y-m-d H:i:s');
		}

		if ($delete === true) {
			$data['deleted'] = 1;
			$data['enabled'] = 0;
			// $data['deleted_by'] = $this->user_id;
		}
	}

	/**
	 * Runs a built single-row query safely, freeing the result after fetch.
	 * Shared by get(), get_where(), and by_hash().
	 *
	 * @return array<string, mixed>|null Associative array of the row, null if not found or query fails
	 */
	protected function execute_row(?string $table = null): ?array
	{
		if ($table !== null) {
			$Q = $this->my_db->get($table);
		} else {
			$Q = $this->my_db->get($this->table_name);
		}


		if ($Q === false) {
			return null;
		}

		$row = $this->legacy_mode ? $Q->row() : $Q->row_array();

		$Q->free_result();
		return $row;
	}

	/**
	 * Runs a built list query safely, freeing the result after fetch.
	 * Shared by the get_all* family.
	 *
	 * @return ?array Array of result rows, empty array if no results found, null if the query failed
	 */
	protected function execute_list(?string $table = null): ?array
	{
		if ($table !== null) {
			$Q = $this->my_db->get($table);
		} else {
			$Q = $this->my_db->get($this->table_name);
		}
		if ($Q === false) {
			return null;
		}

		$data = $Q->result_array();
		$Q->free_result();

		return $data;
	}

	/**
	 * Resolves the id of the row just inserted.
	 *
	 * @return int|string|bool The new id, or false if it could not be read back.
	 */
	protected function insert_id(): int|string|bool
	{
		$sql = match ($this->my_db_driver) {
			MgrDriver::MySQL, MgrDriver::MariaDB => 'SELECT LAST_INSERT_ID() AS id',
			MgrDriver::SQLite                    => 'SELECT last_insert_rowid() AS id',
			default                              => null,
		};

		if ($sql === null) {
			return $this->my_db->insert_id();
		}

		$row = $this->my_db->query($sql)->row_array();

		return $row['id'] ?? false;
	}
}
