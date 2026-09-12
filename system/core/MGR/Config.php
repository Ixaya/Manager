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
	 * Load and return a config array without storing it in $this->config.
	 * Resolves exactly as load() does — a module-scoped file when one
	 * exists, the merged application/package cascade otherwise — minus the
	 * persistence: $this->config and $this->is_loaded are neither read nor
	 * written, so a repeat call always re-reads from disk.
	 *
	 * @param string $_module Module name (optional, auto-detected when empty)
	 * @return array<array-key, mixed>|null
	 */
	public function read(string $file = '', string $_module = '', bool $fail_gracefully = true): ?array
	{
		if ($file === '') {
			return null;
		}

		$file = str_replace('.php', '', $file);

		if ($_module !== '') {
			$file_path = $this->path_module($file, $_module, false);

			if ($file_path !== null) {
				return $this->read_path($file_path, $fail_gracefully);
			}
		}

		$config = $this->_read_cascade($this->_cascade_paths($file), $fail_gracefully);

		if ($config === null && $fail_gracefully === false) {
			show_error("Config file not found: {$file}");
		}

		return $config;
	}

	/**
	 * Merge every application/package layer that defines $file into
	 * $this->config, the application last so it wins. Replaces CI3's
	 * package-last-wins cascade; MX_Config::load() calls this whenever its
	 * module lookup misses.
	 */
	protected function load_fallback(string $file = '', bool $use_sections = false, bool $fail_gracefully = false): bool
	{
		$file = ($file === '') ? 'config' : str_replace('.php', '', $file);
		$paths = $this->_cascade_paths($file);

		// Every layer of a file is recorded in the same call, so any one of
		// them already being in is_loaded means the whole cascade has run.
		if (array_intersect($paths, $this->is_loaded) !== []) {
			return true;
		}

		$config = $this->_read_cascade($paths, $fail_gracefully);

		if ($config === null) {
			if ($fail_gracefully === true) {
				return false;
			}

			show_error('The configuration file ' . $file . '.php does not exist.');

			return false;
		}

		if ($use_sections === true) {
			$this->config[$file] = isset($this->config[$file])
				? array_merge($this->config[$file], $config)
				: $config;
		} else {
			$this->config = array_merge($this->config, $config);
		}

		array_push($this->is_loaded, ...$paths);

		log_message('debug', 'Config file loaded: ' . implode(', ', $paths));

		return true;
	}

	/**
	 * Every existing path for $file, in merge order: package layers first,
	 * application last, so the application wins on conflict. _config_paths
	 * itself stays application-first — its first-match consumers
	 * (path_env(), CI_Loader::_ci_init_library()) reach the same outcome
	 * from the opposite direction, so reordering the array would invert
	 * them.
	 *
	 * @return array<int, string>
	 */
	protected function _cascade_paths(string $file): array
	{
		$paths = [];

		foreach (array_reverse($this->_config_paths) as $path) {
			foreach ([$file, ENVIRONMENT . DIRECTORY_SEPARATOR . $file] as $location) {
				$file_path = $path . 'config/' . $location . '.php';

				if (file_exists($file_path)) {
					$paths[] = $file_path;
				}
			}
		}

		return $paths;
	}

	/**
	 * Include $paths in order into one $config scope, so a later file edits,
	 * unsets or appends to what an earlier file set instead of merging
	 * separately-scoped arrays. Pure — touches neither $this->config nor
	 * $this->is_loaded — which is what lets read() and load_fallback() share
	 * one resolution while only load_fallback() persists anything.
	 *
	 * @param array<int, string> $paths
	 * @return array<array-key, mixed>|null
	 */
	protected function _read_cascade(array $paths, bool $fail_gracefully): ?array
	{
		if ($paths === []) {
			return null;
		}

		$config = $this->read_path(array_shift($paths), $fail_gracefully);

		if ($config === null) {
			return null;
		}

		// The included file runs in this closure's own scope, not the
		// method's — an unlikely parameter name keeps a package/project
		// config file from ever colliding with a variable it can see.
		$include_layer = static function (array $config, string $__mgr_cascade_layer_path): array {
			include $__mgr_cascade_layer_path;

			/** @var array $config */
			return $config;
		};

		foreach ($paths as $file_path) {
			$before = $config;
			$config = $include_layer($config, $file_path);

			if ($config === $before) {
				log_message('debug', "Config layer had no effect: {$file_path}");
			}
		}

		return $config;
	}

	/**
	 * Image URL
	 *
	 * Returns image_url [. uri_string]
	 *
	 * @uses	CI_Config::_uri_string()
	 *
	 * @param	string|string[]	$uri	URI string or an array of segments
	 * @param	string	$protocol
	 * @return	string
	 */
	public function image_url($uri = '', $protocol = null)
	{
		$image_url = $this->slash_item('image_url');
		if (empty($image_url)) {
			$image_url = $this->slash_item('base_url');
		}

		if (isset($protocol)) {
			// For protocol-relative links
			if ($protocol === '') {
				$image_url = substr($image_url, strpos($image_url, '//'));
			} else {
				$image_url = $protocol . substr($image_url, strpos($image_url, '://'));
			}
		}

		return $image_url . $this->_uri_string($uri);
	}
}
