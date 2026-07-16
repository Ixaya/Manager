<?php

defined('BASEPATH') or exit('No direct script access allowed');

class MGR_Model extends CI_Model
{
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

	public function __construct()
	{
		// $this->load->helper('inflector');

		parent::__construct();

		if (!$this->lazy_connect) {
			$this->connect();
		}
	}

	public function connect(?string $connection_name = null): void
	{
		if ($connection_name) {
			$this->connection_name = $connection_name;
		}

		if (mgr_provided($this->connection_name)) {
			$this->my_db = $this->load->database_cache($this->connection_name);
			$this->my_db_driver = MgrDriver::fromCI($this->my_db->dbdriver ?? '');
		} else {
			$this->my_db = $this->load->database_cache();
			$this->my_db_driver = MgrDriver::fromCI($this->my_db->dbdriver ?? '');
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

	public function set_connection(object $db_connection): void
	{
		$this->my_db = $db_connection;

		if (!$this->table_name) {
			$this->generate_table_name();
		}

		$this->set_override();
		$this->connected = true;
	}

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

	public function check_connect(): void
	{
		if (!$this->connected) {
			$this->connect();
		}
	}

	protected function generate_table_name(): void
	{
		$this->table_name = strtolower(get_class($this));
	}

	public function set_override_column(string $column_name): void
	{
		$this->override_column = $column_name;
		$this->set_override();
	}

	public function set_override(int|string|null $id = null): void
	{
		if (!$this->override_column || $this->where_override !== null) {
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

	public function del_override(): void
	{
		$this->where_override = null;
		$this->override_column = null;
		$this->override_id = null;
	}

	/* @return array<string, mixed>|null Associative array of the row, null if not found or query fails */
	public function get(int|string $id, string|array|null $fields = null): ?array
	{
		$this->apply_common_filters($fields);

		$this->my_db->where($this->primary_key, $id);
		return $this->execute_row();
	}
	/* @return array<string, mixed>|null Associative array of the row, null if not found or query fails */
	public function get_where(array $where, string|array|null $fields = null): ?array
	{
		$this->apply_common_filters($fields);

		$this->my_db->where($where);
		return $this->execute_row();
	}

	/**
	 * Get MIN and MAX values for a single field
	 *
	 * @param string $field Field name to get min/max for
	 * @param array $where Optional WHERE conditions
	 * @param string|null $field_alias Field alias to get min/max for
	 * @return array<string, mixed> Array with min_{field} and max_{field} properties, or null if no results
	 *
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

	/* @return array Array of result rows, empty array if query fails or no results found */
	public function get_all(string|array|null $fields = null, array $where = [], int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null): array
	{
		$this->apply_list_filters($fields, $where, $limit, $order_by, $group_by);

		return $this->execute_list();
	}

	/* @return array Array of result rows, empty array if query fails or no results found */
	public function get_all_join(string|array|null $fields = null, array $where = [], int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null, ?string $join_table = null, ?string $join_where = null, string  $join_method = 'left'): array
	{
		$this->apply_list_filters($fields, $where, $limit, $order_by, $group_by);

		if ($join_table !== null && $join_where !== null) {
			$this->my_db->join($join_table, $join_where, $join_method);
		}

		return $this->execute_list();
	}

	/* @return array Array of result rows, empty array if query fails or no results found */
	public function get_all_like(string|array|null $fields = null, array $where = [], int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null): array
	{
		$this->apply_list_filters($fields, [], $limit, $order_by, $group_by);

		if ($where !== []) {
			$this->my_db->like($where);
		}

		return $this->execute_list();
	}

	/* @return array Array of result rows, empty array if query fails or no results found */
	public function get_all_or_like(string|array|null $fields = null, array $where = [], int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null): array
	{
		$this->apply_list_filters($fields, [], $limit, $order_by, $group_by);

		if ($where !== []) {
			$this->my_db->or_like($where);
		}

		return $this->execute_list();
	}

	/* @return array Array of result rows, empty array if query fails or no results found */
	public function get_all_in(string $field, array $values, string|array|null $fields = null, int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null): array
	{
		$this->apply_list_filters($fields, [], $limit, $order_by, $group_by);

		if ($values !== []) {
			$this->my_db->where_in($field, $values);
		}

		return $this->execute_list();
	}

	/* @return array Array of result rows, empty array if query fails or no results found */
	public function get_all_updated(string $last_update, string|array|null $fields = null, array $where = [], int|string|array|null $limit = null, ?string $order_by = null, ?string $group_by = null): array
	{
		$where['last_update >'] = $last_update;
		return $this->get_all($fields, $where, $limit, $order_by, $group_by);
	}

	public function count_all(?array $where = null): int
	{
		$this->apply_common_filters();


		$this->my_db->select('count(*) AS count', false);

		if (mgr_provided($where)) {
			$this->my_db->where($where);
		}

		$data = $this->execute_list();

		return (int)($data[0]['count'] ?? 0);
	}


	public function insert(array $data): int|string|bool
	{
		$this->check_connect();

		$this->set_alter_keys($data);

		if ($this->override_column && $this->override_id) {
			$data[$this->override_column] = $this->override_id;
		}

		$success = $this->my_db->insert($this->table_name, $data);
		if ($success) {
			return $this->my_db->insert_id();
		} else {
			return false;
		}
	}

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

	public function upsert(array $data, int|string|null $id = null): int|string|bool
	{
		if ($id !== null) {
			if ($this->update($data, $id)) {
				return $id;
			}
		} else {
			return $this->insert($data);
		}

		return false;
	}

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

	public function sync_update_insert(array $data, array $where, bool $insert = true, bool $add_sync = false, bool $add_import = true, array $extra_data = [], bool &$modified = false): int|string|false
	{
		$this->check_connect();

		$this->cleanup_columns($where, true);
		$row = $this->get_where($where);

		$this->cleanup_columns($data);
		if (!empty($row)) {
			$update_data = [];
			foreach (array_keys($data) as $key) {
				// Loose compare: DB drivers return strings ("5") that must equal typed values (5); strict here would resync every row.
				if (($row[$key] ?? null) != $data[$key]) {
					$update_data[$key] = $data[$key];
				}
			}

			if (count($update_data) > 0) {
				$this->set_alter_keys($update_data);

				$update_data = array_merge($extra_data, $update_data);
			} elseif (!$add_sync) {
				return $row[$this->primary_key];
			}

			if ($add_sync) {
				$update_data['sync_enabled'] = 1;
			}

			$this->apply_alter_filters();
			$result = $this->my_db->update($this->table_name, $update_data, [$this->primary_key => $row[$this->primary_key]]);
			if ($result === true) {
				$modified = true;
				return $row[$this->primary_key];
			}
		} elseif ($insert) {
			$this->set_alter_keys($data);

			if ($add_import) {
				$data['import_date'] = $data['last_update'];
			}
			if ($add_sync) {
				$data['sync_enabled'] = 1;
			}

			$result = $this->my_db->insert($this->table_name, array_merge($data, $where, $extra_data));
			if ($result === true) {
				$modified = true;
				return $this->my_db->insert_id();
			}
		}

		return false;
	}

	public function sync_update(int|string $id, array $data, bool $timestamp = true, ?array $row = null, int $default_count = 0): bool
	{
		$this->check_connect();
		$this->cleanup_columns($data);

		if ($row !== null) {
			$update_data = [];

			foreach (array_keys($data) as $key) {
				// Loose compare: DB drivers return strings ("5") that must equal typed values (5); strict here would resync every row.
				if ($row[$key] != $data[$key]) {
					$update_data[$key] = $data[$key];
				}
			}

			$update_count = count($update_data);
			if ($update_count == 0) {
				return false;
			}

			if ($timestamp === true && $update_count <= $default_count) {
				$timestamp = false;
			}

			$id =  $row[$this->primary_key];
			$data = $update_data;
		}

		if ($this->use_last_update && $timestamp === true) {
			$data['last_update'] = date('Y-m-d H:i:s');
		}

		$this->apply_alter_filters();
		$this->my_db->where($this->primary_key, $id);

		return $this->my_db->update($this->table_name, $data);
	}
	public function sync_update_enabled(int|string|null $id, int $status): bool
	{
		$this->check_connect();

		$query = "UPDATE {$this->table_name} SET sync_enabled = ?";
		$args = [$status];
		if ($id !== null) {
			$query .= " WHERE id = ?";
			$args[] = $id;
		}
		return $this->my_db->query($query, $args);
	}
	public function sync_commit_enabled(): bool
	{
		$this->check_connect();

		$deleted_expr = match ($this->my_db_driver) {
			MgrDriver::Postgres => 'NOT sync_enabled',
			default             => '!sync_enabled',
		};

		$query = "UPDATE $this->table_name SET enabled = sync_enabled, deleted = $deleted_expr, last_update = ? WHERE enabled != sync_enabled AND (enabled = 0 OR enabled = 1)";

		$now = date('Y-m-d H:i:s');
		return $this->my_db->query($query, [$now]);
	}
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

	public function replace(array $data): int|string|bool
	{
		$this->apply_alter_filters();
		$this->set_alter_keys($data);

		$success = $this->my_db->replace($this->table_name, $data);
		if ($success) {
			return $this->my_db->insert_id();
		} else {
			return false;
		}
	}

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

	public function empty_object(?array $properties = null, bool $include_id = true): object
	{
		return (object) $this->empty_row($properties, $include_id);
	}

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

	public function get_hash(int $length = 13): string
	{
		return mgr_generate_hash($length);
	}

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
	/* @return array<string, mixed>|null Associative array of the row, null if not found or query fails */
	public function by_hash(string $hash, string $field = 'hash'): ?array
	{
		return $this->get_where([$field => $hash]);
	}

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
			MgrDriver::Postgres => "SET TIME ZONE '{$offset}'",
			default             => null,   // SQLite, SQL Server — no session TZ concept
		};

		if ($sql !== null) {
			$this->my_db->query($sql);
		}
	}

	/**
	 * Apply common filters to all queries
	 *
	 * Ensures database connection is established and applies standard WHERE conditions
	 * that should be present in all queries:
	 * - Override conditions from $this->where_override (e.g., tenant filtering, user scope)
	 * - Soft delete filter to exclude deleted records (if enabled)
	 *
	 * @param string $fields Comma-separated field names for SELECT clause (empty = SELECT *)
	 * @return void
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
			$this->my_db->where("{$this->table}.deleted", 0);
		}
	}

	/**
	 * Apply common filters for list/collection queries
	 * Includes field selection, where conditions, pagination, sorting, and grouping
	 *
	 * @param string $fields Comma-separated field names for SELECT clause (empty = SELECT *)
	 * @param array $where Additional WHERE conditions as associative array, (empty = No where)
	 * @param string|array $limit LIMIT clause (e.g., "10" or "10, 20" for offset)
	 * @param string $order_by ORDER BY clause (e.g., "created_at DESC")
	 * @param string $group_by GROUP BY clause (e.g., "category_id")
	 *
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

	/**
	 * Apply WHERE conditions for UPDATE/DELETE operations
	 *
	 * Ensures operations respect override column for data isolation
	 * Also applies where_override if set
	 *
	 */
	protected function apply_alter_filters(): void
	{
		$this->check_connect();

		if ($this->where_override) {
			$this->my_db->where($this->where_override);
		}
	}

	/**
	 * Stamp automatic bookkeeping columns onto a write payload.
	 *
	 * Mutates $data in place before an insert/update/delete applying last_update and soft delete rules
	 *
	 * @param array<string, mixed> $data   Write payload, passed by reference and modified in place.
	 * @param bool                 $delete Whether this write represents a soft-delete operation.
	 * @return void
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
	 * Execute a single-row query and return result safely
	 *
	 * Executes the built query on the specified table, handles query failures gracefully,
	 * frees memory after fetching the result, and returns data as an associative array.
	 * This is a helper method for get* methods that fetch a single record.
	 *
	 * @param string|null $table The table name to query
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
	 * Execute a list query and return results safely
	 *
	 * Executes the built query on the specified table, handles query failures gracefully,
	 * frees memory after fetching results, and returns data as an array.
	 * This is a helper method for get_all* methods that need to execute queries
	 * with different WHERE conditions (LIKE, IN, BETWEEN, etc.)
	 *
	 * @param string $table The table name to query
	 * @return array Array of result rows, empty array if query fails or no results found
	 */
	protected function execute_list(?string $table = null): array
	{
		if ($table !== null) {
			$Q = $this->my_db->get($table);
		} else {
			$Q = $this->my_db->get($this->table_name);
		}
		if ($Q === false) {
			return [];
		}

		$data = $Q->result_array();
		$Q->free_result();

		return $data;
	}
}
