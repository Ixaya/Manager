<?php

class MX_Migration extends CI_Migration
{
	/**
	 * Retrieves list of available migration scripts
	 *
	 * @return array list of migration file paths sorted by version
	 */
	public function find_migrations()
	{
		$migrations = array();

		// Check if the migration path is absolute for backward compatibility
		if (strpos($this->_migration_path, APPPATH) === 0) {
			$this->_collect_migrations_from_path($this->_migration_path, $migrations);
		} else {
			// Collect from the main migration path
			$main_path = APPPATH . "database/{$this->_migration_path}";
			if (is_dir($main_path)) {
				$this->_collect_migrations_from_path($main_path, $migrations);
			}

			// Then, collect from all modules using HMVC module locations
			$this->_collect_migrations_from_modules($migrations);
		}

		// Sort migrations by version number
		ksort($migrations);

		return $migrations;
	}

	/**
	 * Collects migration files from a specific path and adds them to the migrations array
	 *
	 * @param string $path The path to scan for migrations
	 * @param array &$migrations The migrations array passed by reference
	 * @return void
	 */
	private function _collect_migrations_from_path($path, &$migrations)
	{
		// Ensure path ends with a slash
		$path = rtrim($path, '/');

		// Load all *_*.php files in the specified path
		foreach (glob($path . '/*_*.php') as $file) {
			$name = basename($file, '.php');

			// Filter out non-migration files
			if (preg_match($this->_migration_regex, $name)) {
				$number = $this->_get_migration_number($name);

				// There cannot be duplicate migration numbers
				if (isset($migrations[$number])) {
					$this->_error_string = sprintf(
						$this->lang->line('migration_multiple_version'),
						$number
					);
					show_error($this->_error_string);
				}

				$migrations[$number] = $file;
			}
		}
	}

	/**
	 * Collects migrations from all modules using HMVC module locations
	 *
	 * @param array &$migrations The migrations array passed by reference
	 * @return void
	 */
	private function _collect_migrations_from_modules(&$migrations)
	{
		// Check if Modules class exists and has locations configured
		if (!class_exists('Modules') || empty(Modules::$locations)) {
			return;
		}

		// Iterate through all configured module locations
		foreach (Modules::$locations as $module_location => $offset) {

			// Get all module directories in this location
			$module_directories = glob($module_location . '*', GLOB_ONLYDIR);
			foreach ($module_directories as $module_directory) {
				$module_directory = rtrim($module_directory, '/');
				$module_migration_path =  "{$module_directory}/{$this->_migration_path}/";

				// Check if the module has a migrations directory
				if (is_dir($module_migration_path)) {
					$this->_collect_migrations_from_path($module_migration_path, $migrations);
				}
			}
		}
	}
}
