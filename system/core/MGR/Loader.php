<?php

(defined('BASEPATH')) or exit('No direct script access allowed');

/* load the MX_Loader class */
require dirname(__FILE__) . "/../../third_party/MX/Loader.php";

class MGR_Loader extends MX_Loader
{
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

	private $_db_cache = [];
	public function &database_cache($params = '', $query_builder = null)
	{
		// Return main db if params empty
		if (empty($params)) {
			$this->database();
			return CI::$APP->db;
		}

		// Return from array if a DSN string wasn't passed
		if (is_string($params) && strpos($params, '://') === false) {
			if (isset($this->_db_cache[$params]) && is_object($this->_db_cache[$params]) && ! empty($this->_db_cache[$params])) {
				return $this->_db_cache[$params];
			}

			$this->_db_cache[$params] = $this->database($params, true, $query_builder);
			return $this->_db_cache[$params];
		}

		return $this->database($params, true, $query_builder);
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
