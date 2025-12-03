<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Extended CodeIgniter Redis Caching Class
 *
 * Adds support for selecting a Redis logical database while
 * preserving all behavior from the core driver. The parent
 * constructor handles the full connection, configuration load,
 * and authentication workflow. This class only performs an
 * additional database selection when configured.
 *
 * @package     CodeIgniter
 * @subpackage  Libraries
 * @category    Core
 */
class MY_Cache_redis extends CI_Cache_redis
{
	/**
	 * Class constructor
	 *
	 * Calls the parent constructor to initialize the Redis
	 * connection. After a successful connection, selects the
	 * configured Redis database (if provided in the config).
	 *
	 * @return  void
	 * @see     Redis::connect()
	 * @see     Redis::select()
	 */
	public function __construct()
	{
		parent::__construct(); // runs all upstream logic

		if (!isset($this->_redis)) {
			return;
		}

		$CI = &get_instance();
		$config = $CI->config->item('redis');

		if (isset($config['database'])) {
			try {
				$this->_redis->select((int) $config['database']);
			} catch (RedisException $e) {
				log_message('error', 'Cache: Redis database selection failed (' . $e->getMessage() . ')');
			}
		}
	}
}