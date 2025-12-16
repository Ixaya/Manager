<?php
defined('BASEPATH') or exit('No direct script access allowed');

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
	/** @var String */
	private $channelPrefix;

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

		$this->channelPrefix = $config['channel_prefix'] ?? '';
	}

	/**
	 * Publish a message to a channel
	 * 
	 * @param string $channel Channel name
	 * @param mixed $message Message (will be JSON encoded if array)
	 * @return int Number of subscribers that received the message
	 */
	public function publish($channel, $message)
	{
		if (!isset($this->_redis)) {
			return -1;
		}

		// Auto-encode arrays/objects to JSON
		if (is_array($message) || is_object($message)) {
			$message = json_encode($message);
		}

		//Add prefix to channel name
		if ($this->channelPrefix != '') {
			$channel = $this->channelPrefix . $channel;
		}

		try {
			return $this->_redis->publish($channel, $message);
		} catch (Exception $e) {
			log_message('error', 'Redis publish error: ' . $e->getMessage());
			return -1;
		}
	}

	/**
	 * Subscribe to one or more channels
	 * This is a BLOCKING operation
	 * 
	 * @param array|string $channels Channel(s) to subscribe to
	 * @param callable $callback Function to call when message received
	 * 
	 */
	public function subscribe($channels, $callback)
	{
		if (!isset($this->_redis)) {
			return;
		}

		if (!is_array($channels)) {
			$channels = [$channels];
		}

		if ($this->channelPrefix != ''){
			foreach ($channels as &$channel) {
					$channel = $this->channelPrefix . $channel;
			}
			unset($channel);
		}

		try {
			$this->_redis->subscribe($channels, function ($redis, $channel, $message) use ($callback) {
				if ($this->channelPrefix != '' && strpos($channel, $this->channelPrefix) === 0) {
					$channel = substr($channel, strlen($this->channelPrefix));
				}

				call_user_func($callback, $channel, $message);
			});
		} catch (Exception $e) {
			log_message('error', 'Redis subscribe error: ' . $e->getMessage());
		}
	}

	/**
	 * Subscribe to channels matching a pattern
	 * This is a BLOCKING operation
	 * 
	 * @param array|string $patterns Pattern(s) to match
	 * @param callable $callback Function to call when message received
	 * 
	 */
	public function psubscribe($patterns, $callback)
	{
		if (!isset($this->_redis)) {
			return;
		}

		if (!is_array($patterns)) {
			$patterns = [$patterns];
		}

		if ($this->channelPrefix != ''){
			foreach ($patterns as &$pattern) {
				$pattern = $this->channelPrefix . $pattern;
			}
			unset($pattern);
		}

		try {
			$this->_redis->psubscribe($patterns, function ($redis, $pattern, $channel, $message) use ($callback) {
				if ($this->channelPrefix != ''){
					if (strpos($pattern, $this->channelPrefix) === 0) {
						$pattern = substr($pattern, strlen($this->channelPrefix));
					}

					if (strpos($channel, $this->channelPrefix) === 0) {
						$channel = substr($channel, strlen($this->channelPrefix));
					}
				}				

				call_user_func($callback, $channel, $message, $pattern);
			});
		} catch (Exception $e) {
			log_message('error', 'Redis psubscribe error: ' . $e->getMessage());
		}
	}
}
