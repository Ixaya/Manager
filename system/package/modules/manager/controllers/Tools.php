<?php

class Tools extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();

		// can only be called from the command line
		if (!$this->input->is_cli_request()) {
			exit('Direct access is not allowed. This is a command line tool, use the terminal');
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
			['migration <name>', 'Create a new migration file from the MGR_Migration_builder template.'],
			['generate_migration_timestamp <name>', 'Print a timestamped migration file name.'],
			['seeder <name>', 'Create a new seed file.'],
			['seed <name>', 'Run the specified seed file (stub — not implemented).'],
			['model <name> <module>', 'Create a new MY_Model skeleton in the given module.'],
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

	public function generate_migration_timestamp(string $name)
	{
		$timestamp = date('YmdHis');

		echo $timestamp . '_' . $name . ".php\r\n";
	}

	public function generate_enc_key(string $length = '16')
	{
		$this->load->library('encryption');
		$key = bin2hex($this->encryption->create_key((int)$length));
		die($key);
	}
	public function migration(string $name)
	{
		$this->make_migration_file($name);
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

	public function seeder(string $name)
	{
		$this->make_seed_file($name);
	}

	public function seed(string $name)
	{
		//Note: add "fzaninotto/faker" to composer and uncomment
		// $this->faker = Faker\Factory::create();

		// $seeder = new Seeder();

		// $seeder->call($name);
	}

	public function model($name, $module)
	{
		$this->make_model_file($name, $module);
	}

	protected function make_migration_file($name, $database = 'default', $module = '')
	{
		$date = new DateTime();
		$timestamp = $date->format('YmdHis');

		$table_name = strtolower($name);

		if ($module == '') {
			$base_path = APPPATH . "database/migrations/$database";
		} else {
			$base_path = APPPATH . "modules/$module/migrations/$database";
		}

		if (!is_dir($base_path)) {
			if (!mkdir($base_path, 0755, true) && !is_dir($base_path)) {
				throw new \RuntimeException('Unable to create migrations directory: ' . $base_path);
			}
		}

		$path = "$base_path/{$timestamp}_{$name}.php";

		$my_migration = fopen($path, "w") or die("Unable to create migration file!");

		$migration_template = "<?php

class Migration_$name extends MGR_Migration_builder {

	public function up() {
		\$this->dbforge->add_field([
			...\$this->field_id('id'),
			...\$this->field(name: 'name', type: MgrFieldType::VarChar, constraint: 100),
			...\$this->field_timestamps()
		]);

		\$this->dbforge->add_key('id', true);
		\$this->dbforge->create_table('$table_name');
	}

	public function down() {
		\$this->dbforge->drop_table('$table_name');
	}

}";

		fwrite($my_migration, $migration_template);

		fclose($my_migration);

		echo "$path migration has successfully been created." . PHP_EOL;
	}

	protected function make_seed_file(string $name)
	{
		$path = APPPATH . "database/seeds/$name.php";

		$my_seed = fopen($path, "w") or die("Unable to create seed file!");

		$seed_template = "<?php

class $name extends Seeder {

	private \$table = 'users';

	public function run() {
		\$this->db->truncate(\$this->table);

		//seed records manually
		\$data = [
			'user_name' => 'admin',
			'password' => '9871'
		];
		\$this->db->insert(\$this->table, \$data);

		//seed many records using faker
		\$limit = 33;
		echo \"seeding \$limit user accounts\";

		for (\$i = 0; \$i < \$limit; \$i++) {
			echo \".\";

			\$data = array(
				'user_name' => \$this->faker->unique()->userName,
				'password' => '1234',
			);

			\$this->db->insert(\$this->table, \$data);
		}

		echo PHP_EOL;
	}
}
";

		fwrite($my_seed, $seed_template);

		fclose($my_seed);

		echo "$path seeder has successfully been created." . PHP_EOL;
	}

	protected function make_model_file(string $name, string $module)
	{
		$path = APPPATH . "modules/$module/models/$name.php";

		$my_model = fopen($path, "w") or die("Unable to create model file!");

		$model_template = "<?php defined('BASEPATH') or exit('No direct script access allowed');

class $name extends MY_Model {

	public function __construct() {
		parent::__construct();
	}
}
";

		fwrite($my_model, $model_template);

		fclose($my_model);

		echo "$path model has successfully been created." . PHP_EOL;
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
			'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS',
			'CF_ENCRYPTION_KEY', 'CF_SESS_SAVE_PATH',
			'LIB_REDIS_HOST', 'LIB_REDIS_PASSWORD',
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
