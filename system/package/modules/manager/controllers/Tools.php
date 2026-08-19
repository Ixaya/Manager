<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tools extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();

		// can only be called from the command line
		if (!is_cli()) {
			show_error('Direct access is not allowed. This is a command line tool, use the terminal');
		}
	}

	public function message($to = 'World')
	{
		echo "Hello {$to}!" . PHP_EOL;
	}

	public function help()
	{
		$commands = [
			// [invocation, description] — <arg> required, [arg] optional (positional: skipping one skips the rest)
			['migrate [version] [module_key]', 'Run pending migrations on every configured database (optionally to a target version).'],
			['migrate_database [connection] [version] [module_key]', 'Run migrations on one database connection only.'],
			['plan', 'Per-module migration status: current/latest/pending per database.'],
			['version_list [module_key] [database]', 'List recorded migration versions (or one module\'s migration files).'],
			['version_set <version> [module_key] [database]', 'Force the recorded migration version without running migrations.'],
			['migration_file <name> <module> [database] [force_modification]', 'Print a `cat > ... <<\'MGR_EOF\'` command that writes an auto-versioned migration file (_v{n} if the name already exists in that module) — paste it into a host shell. force_modification=1 starts a table with pre-tool history at _v2 instead of a fresh create.'],
			['migration_path <name> <module> [database] [force_modification]', 'Print the auto-versioned name + destination path for a migration, as JSON. See migration_file for force_modification.'],
			['model_file <name> <module> [table]', 'Print a `cat > ... <<\'MGR_EOF\'` command that writes a new MY_Model skeleton in the given module, optionally overriding $table_name — paste it into a host shell.'],
			['generate_enc_key [length]', 'Generate a random encryption key (hex, default 16 bytes).'],
			['claim_admin', 'One-shot: rotate the seeded admin\'s factory password and print the new one.'],
			['env_check [key]', 'Per-key env source report (values never printed). No key = framework must-haves.'],
			['log_check', 'Log destination report: path, ownership and whether appends actually succeed.'],
			['cli_exec <module> <library> <function> [identifier]', 'Run a library call in-process (async_exec_lib dispatch target).'],
			['message [name]', 'Smoke-test echo.'],
			['help', 'This list.'],
		];

		echo 'Available commands (php index.php manager/tools/<command>) — <arg> required, [arg] optional' . PHP_EOL . PHP_EOL;
		foreach ($commands as [$invocation, $description]) {
			echo sprintf("  %-62s %s" . PHP_EOL, $invocation, $description);
		}
		echo PHP_EOL;
	}

	public function generate_enc_key(string $length = '16')
	{
		$this->load->library('encryption');
		$key = bin2hex($this->encryption->create_key((int)$length));
		die($key);
	}

	public function plan()
	{
		$this->load->library('migration_module_lib');

		$migration_databases = $this->config->item('migration_db') ?? ['default'];
		foreach ($migration_databases as $database) {
			foreach ($this->migration_module_lib->plan($database) as $t) {
				$pending = count($t['pending']);
				echo sprintf(
					"%-24s current:%s latest:%s pending:%d" . PHP_EOL,
					$t['label'],
					$t['current'] ?: '-',
					$t['latest'] ?: '-',
					$pending
				);

				if ($pending > 0 && $t['current'] === 0) {
					$key_cli = $t['key'] !== null ? str_replace('/', ':', $t['key']) : 'app';
					$hint = "version_set {$t['latest']} {$key_cli} {$database}";
					echo sprintf("       hint: if already applied -> tools %s" . PHP_EOL, $hint);
				}
			}
		}
	}

	public function version_list(?string $module_key = null, ?string $database = null)
	{
		$this->load->library('migration_module_lib');

		if ($module_key === null) {
			$databases = $this->config->item('migration_db') ?? ['default'];
			foreach ($databases as $db) {
				foreach ($this->migration_module_lib->version_list($db) as $line) {
					echo $line . PHP_EOL;
				}
			}
			return;
		}

		$key = $module_key === 'app' ? null : str_replace(':', '/', $module_key);
		foreach ($this->migration_module_lib->version_list_files($database ?? 'default', $key) as $line) {
			echo $line . PHP_EOL;
		}
	}

	public function version_set(?string $version = null, ?string $module_key = null, string $database = 'default')
	{
		if ($version === null) {
			echo "[FAIL] version required" . PHP_EOL;
			return;
		}
		if ($module_key === 'app') {
			$module_key = null;
		} elseif ($module_key !== null) {
			$module_key = str_replace(':', '/', $module_key);
		}

		$this->load->library('migration_module_lib');
		$this->migration_module_lib->force_db_debug();
		echo $this->migration_module_lib->version_set($database, $module_key, $version) . PHP_EOL;
	}

	public function migrate(?string $version = null, ?string $module_key = null)
	{
		if ($module_key !== null) {
			$module_key = str_replace(':', '/', $module_key);
		}

		$migration_databases = $this->config->item('migration_db') ?? ['default'];
		foreach ($migration_databases as $database) {
			$this->migrate_database($database, $version, $module_key);
		}
	}

	public function migrate_database(string $connection_name = 'default', ?string $version = null, ?string $module_key = null)
	{
		$this->load->library('migration_module_lib');
		$this->migration_module_lib->force_db_debug();

		// 2. Targeted version (single target) — may run down() migrations
		if ($version !== null) {
			echo $this->migration_module_lib->migrate_target($connection_name, $module_key, $version) . PHP_EOL;
			return;
		}

		// 3. Default: everything forward to latest
		foreach ($this->migration_module_lib->run($connection_name) as $line) {
			echo $line . PHP_EOL;
		}
	}

	/**
	 * Prints a `cat > ... <<'MGR_EOF'` command that writes the migration
	 * file when pasted into a host shell — nothing is written by this call
	 * itself. Auto-versions the name with `_v{n}` when it already exists
	 * in the module — see migration_path().
	 *
	 * @param string $force_modification Truthy to force the modification
	 *   branch (`_v2`) for a table whose history predates the
	 *   `{Module}_{name}` naming convention, instead of a duplicate
	 *   create-table migration for a table that already exists.
	 */
	public function migration_file(string $name, string $module, string $database = 'default', string $force_modification = '0')
	{
		[
			'name'            => $versioned_name,
			'table_name'      => $table_name,
			'path'            => $relative_path,
			'is_modification' => $is_modification,
			'legacy_cutover'  => $legacy_cutover,
		] = $this->_migration_path($name, $module, $database, (bool) $force_modification);

		$date      = new DateTime();
		$timestamp = $date->format('YmdHis');
		$path      = "application/{$relative_path}/{$timestamp}_{$versioned_name}.php";

		$migration_template = $is_modification
			? $this->_migration_modification_template($versioned_name, $table_name, $legacy_cutover)
			: $this->_migration_creation_template($versioned_name, $table_name);

		echo $this->_write_file_command($path, $migration_template) . PHP_EOL;
	}

	/**
	 * Prints the auto-versioned migration name and destination path as
	 * JSON, for wiring into a migration authored by hand instead of going
	 * through migration_file()'s template. See migration_file() for
	 * $force_modification.
	 */
	public function migration_path(string $name, string $module, string $database = 'default', string $force_modification = '0')
	{
		echo json_encode($this->_migration_path($name, $module, $database, (bool) $force_modification)) . PHP_EOL;
	}

	/**
	 * Module-qualified migration name (`{Module}_{name}`, `_v{n}` appended
	 * if that name already exists anywhere under the module's own
	 * migrations, across every database connection it has), plus the
	 * destination directory and the unqualified table name.
	 *
	 * @param bool $force_modification Skip the existence check and always
	 *   return the modification branch — for a table whose migrations
	 *   predate the `{Module}_{name}` convention, where the on-disk glob
	 *   can never match the qualified name even though the table already
	 *   exists.
	 * @return array{name: string, table_name: string, path: string, is_modification: bool, legacy_cutover: bool}
	 */
	protected function _migration_path(string $name, string $module, string $database = 'default', bool $force_modification = false): array
	{
		$table_name         = strtolower($name);
		$qualified_base     = ucfirst(strtolower("{$module}_{$name}"));
		$qualified_base_key = strtolower($qualified_base);
		$existing           = $this->_module_migration_names($module);
		$path               = "modules/$module/migrations/$database";
		$has_history        = in_array($qualified_base_key, $existing, true)
			|| preg_grep('/^' . preg_quote($qualified_base_key, '/') . '_v\d+$/', $existing) !== [];

		if (!$force_modification && !$has_history) {
			return [
				'name'            => $qualified_base,
				'table_name'      => $table_name,
				'path'            => $path,
				'is_modification' => false,
				'legacy_cutover'  => false,
			];
		}

		for ($version = 2; in_array("{$qualified_base_key}_v{$version}", $existing, true); $version++) {
		}

		return [
			'name'            => "{$qualified_base}_v{$version}",
			'table_name'      => $table_name,
			'path'            => $path,
			'is_modification' => true,
			'legacy_cutover'  => $force_modification,
		];
	}

	/**
	 * Every migration name segment already used under a module's
	 * migrations directory, across every database connection it has,
	 * lowercased.
	 *
	 * @return string[]
	 */
	protected function _module_migration_names(string $module): array
	{
		$names = [];

		foreach (glob(APPPATH . "modules/$module/migrations/*", GLOB_ONLYDIR) ?: [] as $connection_dir) {
			foreach (glob("$connection_dir/*_*.php") ?: [] as $file) {
				if (preg_match('/^\d+_(.+)$/', basename($file, '.php'), $matches) === 1) {
					$names[] = strtolower($matches[1]);
				}
			}
		}

		return $names;
	}

	/**
	 * Template for a migration's first version — a generic table with an
	 * id primary key and the standard timestamp pair, safe to run unedited.
	 */
	protected function _migration_creation_template(string $name, string $table_name): string
	{
		return "<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_$name extends MGR_Migration_builder {
	protected \$table_name = '$table_name';
	public function up() {
		\$this->dbforge->add_field([
			...\$this->field_id('id'),
			...\$this->field(name: 'create_date', type: MgrFieldType::Timestamp, nullable: true),
			...\$this->field(name: 'last_update', type: MgrFieldType::Timestamp, nullable: true),
		]);

		\$this->dbforge->add_key('id', true);
		\$this->dbforge->create_table(\$this->table_name);

		\$this->modify_field_timestamp(table: \$this->table_name, column: 'last_update');
		\$this->modify_field_timestamp(table: \$this->table_name, column: 'create_date', on_update: false);
	}

	public function down() {
		\$this->dbforge->drop_table(\$this->table_name);
	}

}";
	}

	/**
	 * Template for a later version against an existing table — deliberately
	 * empty rather than a sample: a fabricated add_column()/drop_column()
	 * pair left un-edited would succeed silently against a real table.
	 *
	 * @param bool $legacy_cutover Table whose migrations predate the
	 *   `{Module}_{name}` naming convention — adds a pointer comment since
	 *   `_v{n}` restarts at `_v2` here with no `_v1`/bare version in this
	 *   module, which could otherwise misread as the table's first
	 *   migration.
	 */
	protected function _migration_modification_template(string $name, string $table_name, bool $legacy_cutover = false): string
	{
		$legacy_comment = $legacy_cutover
			? "\n// Continues pre-existing migration history for this table; earlier migrations predate the {Module}_{name} naming convention.\n"
			: '';

		return "<?php

defined('BASEPATH') or exit('No direct script access allowed');
{$legacy_comment}
class Migration_$name extends MGR_Migration_builder {
	protected \$table_name = '$table_name';
	public function up() {
	}

	public function down() {
	}

}";
	}

	/**
	 * Prints a ready-to-run `cat > ... <<'MGR_EOF'` shell command that
	 * writes the model file — see migration_file()'s docblock for why this
	 * prints instead of writing directly.
	 */
	public function model_file(string $name, string $module, ?string $table = null)
	{
		$path = "application/modules/$module/models/$name.php";

		$table_property = $table !== null
			? "\n\tprotected \$table_name = '{$table}';\n"
			: '';

		$model_template = "<?php

defined('BASEPATH') or exit('No direct script access allowed');

class $name extends MY_Model {
$table_property
	public function __construct() {
		parent::__construct();
	}
}
";

		echo $this->_write_file_command($path, $model_template) . PHP_EOL;
	}

	/**
	 * A `mkdir -p`-then-`cat > $path <<'MGR_EOF' ... MGR_EOF` command:
	 * pasted into a host shell (not run in-container), it creates $path's
	 * directory if needed and writes $content to $path verbatim — the
	 * quoted delimiter disables the shell's own `$`/backtick expansion,
	 * which would otherwise mangle the PHP inside.
	 */
	protected function _write_file_command(string $path, string $content): string
	{
		$dir = dirname($path);

		return "mkdir -p $dir && cat > $path <<'MGR_EOF'\n{$content}\nMGR_EOF";
	}

	public function cli_exec(string $module, string $library, string $function, string $identifier = '')
	{
		if (!is_cli()) {
			show_error('CLI only', 403);
		}

		$this->load->library('async_exec_lib');

		$this->async_exec_lib->run_library_call($module, $library, $function, $identifier);
	}

	/**
	 * One-shot bootstrap: rotate the seeded admin's factory password to a
	 * generated one, printed once. Refuses once the row no longer carries
	 * the exact factory hash. Takes no password argument on purpose —
	 * argv would leak it into shell history and process lists.
	 */
	public function claim_admin()
	{
		//Admin seed, copied verbatim from the Ion_Auth migration (which stays plain on purpose).
		$SEED_ADMIN_IDENTITY = 'admin@admin.com';
		$SEED_ADMIN_PASSWORD_HASH = '$2y$11$cXuqWNc/NGzL3.cpCGkAvOMn/Thyu6yWEgW1CTIHLADiPw7uwuBlK';

		$this->load->database();

		if (!$this->db->conn_id) {
			throw new RuntimeException('Tools::claim_admin: unable to connect to the database.');
		}

		$user = $this->db
			->select('id, password')
			->where('username', $SEED_ADMIN_IDENTITY)
			->get('user')
			->row();

		if ($user === null) {
			echo 'No seeded admin user (' . $SEED_ADMIN_IDENTITY . ') found — nothing to claim.' . PHP_EOL;
			return;
		}

		if (!hash_equals($SEED_ADMIN_PASSWORD_HASH, (string) $user->password)) {
			echo 'Admin account already claimed — use the normal password-reset flow.' . PHP_EOL;
			return;
		}

		$password = bin2hex(random_bytes(16));

		$this->load->library('ion_auth');

		if (!$this->ion_auth->reset_password($SEED_ADMIN_IDENTITY, $password)) {
			echo 'Password update failed: ' . strip_tags((string) $this->ion_auth->errors()) . PHP_EOL;
			return;
		}

		log_message('info', 'claim_admin: seeded admin account claimed, factory password rotated via CLI');

		echo 'Seeded admin claimed.' . PHP_EOL;
		echo 'Identity: ' . $SEED_ADMIN_IDENTITY . PHP_EOL;
		echo 'Password: ' . $password . PHP_EOL;
		echo '(shown once — store it now, it is not recoverable)' . PHP_EOL;
	}

	/**
	 * Per-key env resolution report: which layer answered (process env,
	 * $_ENV, .env.priv, .env) with set-state and length. Values are never
	 * printed — every key is treated as secret.
	 *
	 * With a key argument, checks that single key; with none, checks the
	 * keys the framework cannot run without.
	 */
	public function env_check(?string $key = null)
	{
		$keys = $key !== null ? [$key] : [
			'APP_ENV',
			'DB_HOST',
			'DB_PORT',
			'DB_NAME',
			'DB_USER',
			'DB_PASS',
			'CF_ENCRYPTION_KEY',
			'CF_SESS_SAVE_PATH',
			'LIB_REDIS_HOST',
			'LIB_REDIS_PASSWORD',
		];

		$missing = 0;
		foreach ($keys as $check_key) {
			$row = Env_lib::resolve_source($check_key, ENVIRONMENT);

			echo sprintf(
				"%-36s source=%-12s set=%-3s len=%d" . PHP_EOL,
				$check_key,
				$row['source'],
				$row['set'] ? 'yes' : 'NO',
				$row['length']
			);

			if (!$row['set']) {
				$missing++;
			}
		}

		if ($key === null && $missing > 0) {
			echo PHP_EOL . "[WARN] {$missing} framework key(s) missing — the app will misbehave without them." . PHP_EOL;
		}
	}

	/**
	 * Reports whether log writes can actually land: destination, ownership and
	 * append permission for the file the logger will use today.
	 *
	 * There is no runtime symptom to notice otherwise — CI opens the log with
	 * a silenced fopen() and log_message() discards the result, so a
	 * destination the web-server user cannot append to loses every entry with
	 * no error anywhere.
	 */
	public function log_check()
	{
		$log_path = (string) config_item('log_path');
		if ($log_path === '') {
			$log_path = APPPATH . 'logs/';
		}

		$log_path  = rtrim($log_path, '/\\') . DIRECTORY_SEPARATOR;
		$extension = (string) config_item('log_file_extension');
		$log_file  = $log_path . 'log-' . date('Y-m-d') . '.' . ($extension === '' ? 'php' : $extension);

		echo 'running as       ' . $this->describe_user() . PHP_EOL;
		echo 'directory        ' . $log_path . PHP_EOL;
		echo 'threshold        ' . var_export(config_item('log_threshold'), true) . PHP_EOL;

		$problems = [];

		if (!is_dir($log_path)) {
			echo 'directory state  MISSING' . PHP_EOL;
			$problems[] = 'the log directory does not exist';
		} else {
			echo 'directory state  ' . $this->describe_path($log_path) . PHP_EOL;
			if (!is_writable($log_path)) {
				$problems[] = 'the log directory is not writable by this user';
			}
		}

		echo 'today\'s file     ' . $log_file . PHP_EOL;

		if (!file_exists($log_file)) {
			// Deliberately not created here: a file this command makes belongs to
			// whoever ran it, which is the very mismatch it exists to detect.
			echo 'file state       not created yet' . PHP_EOL;
		} else {
			echo 'file state       ' . $this->describe_path($log_file) . PHP_EOL;

			$handle = @fopen($log_file, 'ab');
			if ($handle === false) {
				echo 'append test      FAILED' . PHP_EOL;
				$problems[] = 'the current log file cannot be appended to — every entry is being dropped';
			} else {
				fclose($handle);
				echo 'append test      ok' . PHP_EOL;
			}
		}

		echo PHP_EOL;

		// Root passes every permission test and creates root-owned files the
		// web-server user then cannot append to — the failure this command exists
		// to catch is the one a root run is blind to.
		if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
			echo '[WARN] running as root: appends succeed regardless of ownership, so this'
				. ' says nothing about the web-server user. Re-run as that user.' . PHP_EOL;
		}

		if ($problems === []) {
			echo '[ ok ] log writes land for this user.' . PHP_EOL;

			return;
		}

		foreach ($problems as $problem) {
			echo '[WARN] ' . $problem . '.' . PHP_EOL;
		}
		echo 'Logging fails silently, so nothing else will report this.' . PHP_EOL;
	}

	/**
	 * Owner, group and mode of a path, for comparing against the web-server user.
	 */
	protected function describe_path(string $path): string
	{
		$owner = function_exists('posix_getpwuid') ? posix_getpwuid((int) fileowner($path)) : null;
		$group = function_exists('posix_getgrgid') ? posix_getgrgid((int) filegroup($path)) : null;

		return sprintf(
			'owner=%s group=%s mode=%s writable=%s',
			$owner['name'] ?? (string) fileowner($path),
			$group['name'] ?? (string) filegroup($path),
			substr(sprintf('%o', fileperms($path)), -4),
			is_writable($path) ? 'yes' : 'NO'
		);
	}

	/**
	 * The effective user of this process.
	 */
	protected function describe_user(): string
	{
		if (!function_exists('posix_geteuid')) {
			return get_current_user();
		}

		$user = posix_getpwuid(posix_geteuid());

		return ($user['name'] ?? get_current_user()) . ' (uid ' . posix_geteuid() . ')';
	}
}
