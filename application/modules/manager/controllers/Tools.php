<?php

class Tools extends CI_Controller {

	public function __construct() {
		parent::__construct();

		// can only be called from the command line
		if (!$this->input->is_cli_request()) {
			exit('Direct access is not allowed. This is a command line tool, use the terminal');
		}
	}

	public function message($to = 'World') {
		echo "Hello {$to}!" . PHP_EOL;
	}

	public function help() {
		$result = "The following are the available command line interface commands\n\n";
		$result .= "php index.php tools migration \"file_name\"		 Create new migration file\n";
		$result .= "php index.php tools migrate [\"version_number\"]	Run all migrations. The version number is optional.\n";
		$result .= "php index.php tools seeder \"file_name\"			Creates a new seed file.\n";
		$result .= "php index.php tools seed \"file_name\"			  Run the specified seed file.\n";

		echo $result . PHP_EOL;
	}
	public function generate_enc_key($length = 16){
		$this->load->library('encryption');
		$key = bin2hex($this->encryption->create_key($length));
		die($key);
	}
	public function migration($name) {
		$this->make_migration_file($name);
	}

	public function migrate($version = null) {
		$migration_databases = $this->config->item('migration_db') ?? ['default'];
		foreach ($migration_databases as $database) {
			$this->migrate_database($database, $version);
		}
	}

	public function migrate_database($connection_name = 'default', $version = null)
	{
		$this->load->dbforge();
		
		$migration_path = 'migrations/' . $connection_name;

		// Configuration for this specific migration
		$migration_config = array(
			'migration_path' => $migration_path,
			'db_group' => $connection_name
		);

		// Load MY_Migration library with the specific config
		$migration_lib_name = 'migration_' . $connection_name;
		$this->load->library('migration', $migration_config, $migration_lib_name);

		if ($version != null) {
			if ($this->{$migration_lib_name}->version($version) === FALSE) {
				echo $this->{$migration_lib_name}->error_string() . PHP_EOL;
			} else {
				echo "Migrations run successfully" . PHP_EOL;
			}

			return;
		}

		if ($this->{$migration_lib_name}->latest() === FALSE) {
			echo $this->{$migration_lib_name}->error_string() . PHP_EOL;
		} else {
			echo "Migrations run successfully" . PHP_EOL;
		}
	}
	public function seeder($name) {
		$this->make_seed_file($name);
	}

	public function seed($name) {
		//Note: add "fzaninotto/faker" to composer
		$this->faker = Faker\Factory::create();

		$seeder = new Seeder();

		$seeder->call($name);
	}

	public function model($name) {
		$this->make_model_file($name);
	}

	protected function make_migration_file($name) {
		$date = new DateTime();
		$timestamp = $date->format('YmdHis');

		$table_name = strtolower($name);

		$path = APPPATH . "database/migrations/$timestamp" . "_" . "$name.php";

		$my_migration = fopen($path, "w") or die("Unable to create migration file!");

		$migration_template = "<?php

class Migration_$name extends CI_Migration {

	public function up() {
		\$this->dbforge->add_field(array(
			'id' => array(
				'type' => 'INT',
				'constraint' => 11,
				'auto_increment' => TRUE
			)
		));
		\$this->dbforge->add_key('id', TRUE);
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

	protected function make_seed_file($name) {
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

	protected function make_model_file($name) {
		$path = APPPATH . "modules/admin/models/$name.php";

		$my_model = fopen($path, "w") or die("Unable to create model file!");

		$model_template = "<?php (defined('BASEPATH')) OR exit('No direct script access allowed');

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

}
