<?php

defined('BASEPATH') or exit('No direct script access allowed');

/* load the MX_Config class */
require dirname(__FILE__) . "/../../third_party/MX/Config.php";

class MGR_Config extends MX_Config
{
	// Must sync $this into the true global $CFG here, or MX's Modules.php/
	// Ci.php fallback replaces it with a bare MX_Config under a
	// function-scope boot (PHPUnit), breaking module-scoped config reads.
	public function __construct()
	{
		parent::__construct();
		global $CFG;
		$CFG = $this;
	}

	/**
	 * Get the full file path of a config file (supports modules)
	 * Returns the path without loading the config
	 *
	 * @param string $file Config filename (without .php)
	 * @param string $_module Module name (optional, auto-detected if empty)
	 * @return string|null Full file path or FALSE if not found
	 */
	public function path(string $file = '', string $_module = ''): ?string
	{
		if (empty($file)) {
			return null;
		}

		$file =  str_replace('.php', '', $file);

		if ($_module == '') {
			return $this->path_env($file);
		}

		return $this->path_module($file, $_module);
	}

	/**
	 * Load and return config array without storing it in $this->config
	 * Useful for reading sensitive configs that you don't want to keep in memory
	 *
	 * @param string $file Config filename
	 * @param string $_module Module name (optional)
	 * @return array|null Config array or null on failure
	 */
	public function read(string $file = '', string $_module = '', $fail_gracefully = true): ?array
	{
		$file_path = $this->path($file, $_module);

		if ($file_path === null) {
			if (!$fail_gracefully) {
				show_error("Config file not found: {$file}");
			}

			return null;
		}

		return $this->read_path($file_path, $fail_gracefully);
	}
}
