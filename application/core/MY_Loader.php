<?php (defined('BASEPATH')) or exit('No direct script access allowed');

/* load the MX_Loader class */
require APPPATH . "third_party/MX/Loader.php";
class MY_Loader extends MX_Loader
{
	/** Load a module view **/
	public $header_vars = false;
	public function view($view, $vars = [], $return = FALSE)
	{
		if ($this->header_vars != false)
			$vars = array_merge($vars, $this->header_vars);

		list($path, $_view) = Modules::find($view, $this->_module, 'views/');

		if ($path != FALSE) {
			$this->_ci_view_paths = array($path => TRUE) + $this->_ci_view_paths;
			$view = $_view;
		}

		return $this->_ci_load(array('_ci_view' => $view, '_ci_vars' => ((method_exists($this, '_ci_object_to_array')) ? $this->_ci_object_to_array($vars) : $this->_ci_prepare_view_vars($vars)), '_ci_return' => $return));
	}

	private $_db_cache = [];
	public function &database_cache($params = '', $query_builder = NULL)
	{
		// Return main db if params empty
		if (empty($params)) {
			$this->database();
			return CI::$APP->db;
		}

		// Return from array if a DSN string wasn't passed
		if (is_string($params) && strpos($params, '://') === FALSE) {
			if (isset($this->_db_cache[$params]) && is_object($this->_db_cache[$params]) && ! empty($this->_db_cache[$params])) {
				return $this->_db_cache[$params];
			}

			$this->_db_cache[$params] = $this->database($params, TRUE, $query_builder);
			return $this->_db_cache[$params];
		}

		return $this->database($params, TRUE, $query_builder);
	}
}
