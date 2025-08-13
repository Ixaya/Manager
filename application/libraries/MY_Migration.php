<?php (defined('BASEPATH')) or exit('No direct script access allowed');

/* load the MX_Migration class */
require APPPATH . "third_party/MX/Migration.php";

class MY_Migration extends MX_Migration
{
	/**
	 * Database connection group
	 *
	 * @var string
	 */
	protected $_db_group = 'default';

	/**
	 * Initialize Migration Class with database connection support
	 *
	 * @param array $config
	 * @return void
	 */
	public function __construct($config = array())
	{
		// Merge in this order: config file -> custom config
		$this->config->load('migration', TRUE, TRUE);
		$base_config = $this->config->item('migration') ?: array();
		$config = array_merge($base_config, $config);

		// Extract database connection info before parent constructor
		if (isset($config['db_group'])) {
			$this->_db_group = $config['db_group'];
			unset($config['db_group']); // Remove so parent doesn't try to set it

			$this->_setup_database_connection();
		}

		// Call parent constructor
		parent::__construct($config);
	}

	/**
	 * Set up database connection
	 *
	 * @return void
	 */
	protected function _setup_database_connection()
	{
		// Load the specified database connection
		$CI = &get_instance();
		$CI->db = $this->load->database($this->_db_group, TRUE);


		// Override the default db and dbforge with our connection
		$this->load->dbforge($this->db);
	}

	/**
	 * Get current database group
	 *
	 * @return string
	 */
	public function get_db_group()
	{
		return $this->_db_group;
	}
}