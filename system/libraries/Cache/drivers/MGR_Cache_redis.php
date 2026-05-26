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
class MGR_Cache_redis extends CI_Cache_redis
{
	// const SERIALIZE_PREFIX = "\x00PHP_SER\x00"; // Magic header for PHP serialize
	public const SERIALIZE_PREFIX = "~PHP~"; // Magic header for PHP serialize

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

		if ($this->_redis === null) {
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
	 * Save cache
	 * Fix: recognize ttl < 1 and set without expiry
	 *
	 * @param	string	$id	Cache ID
	 * @param	mixed	$data	Data to save
	 * @param	int	$ttl	Time to live in seconds
	 * @param	bool	$raw	Whether to store the raw value (unused)
	 * @return	bool	TRUE on success, FALSE on failure
	 */
	public function save($id, $data, $ttl = 60, $raw = false)
	{
		if (is_array($data) or is_object($data)) {
			$data = self::SERIALIZE_PREFIX . serialize($data);
		}
		if ($ttl < 1) {
			return $this->_redis->set($id, $data);
		}

		return $this->_redis->set($id, $data, $ttl);
	}

	/**
	 * Save one or more items to a list (append-only).
	 * TTL is reset on every call.
	 *
	 * @param string $id      Cache key
	 * @param mixed  $data    Single string or array of strings
	 * @param int    $ttl     Seconds (0 = no expiry)
	 * @param bool   $prepend If true, add to beginning (lPush), if false add to end (rPush)
	 * @return bool
	 */
	public function save_list($id, $data, $ttl = 60, $prepend = false)
	{
		$items = is_array($data) ? $data : [$data];

		if ($prepend) {
			$result = $this->_redis->lPush($id, ...$items);
		} else {
			$result = $this->_redis->rPush($id, ...$items);
		}

		if ($result === false) {
			return false;
		}

		if ($ttl > 0) {
			$this->_redis->expire($id, $ttl);
		}

		return true;
	}

	/**
	 * Save one or more items to a set (append-only).
	 * TTL is reset on every call.
	 *
	 * @param string $id    Cache key
	 * @param mixed  $data  Single string or array of strings
	 * @param int    $ttl   Seconds (0 = no expiry)
	 * @return bool
	 */
	public function save_set(string $id, mixed $data, int $ttl = 60)
	{
		$items = is_array($data) ? $data : [$data];
		$result = $this->_redis->sAdd($id, ...$items);

		if ($result === false) {
			return false;
		}

		if ($ttl > 0) {
			$this->_redis->expire($id, $ttl);
		}

		return true;
	}

	/**
	 * Add one or more members to a sorted set with explicit scores (append-only).
	 * TTL is reset on every call.
	 *
	 * $data can be:
	 *   - a single pair:  ['value' => 'foo', 'score' => 1.5]
	 *   - multiple pairs: [['value' => 'foo', 'score' => 1.5], ['value' => 'bar', 'score' => 2.0]]
	 *
	 * @param string $id   Cache key
	 * @param mixed  $data Single pair array or array of pairs
	 * @param int    $ttl  Seconds (0 = no expiry)
	 * @return bool
	 */
	public function save_zset(string $id, mixed $data, int $ttl = 60)
	{
		// Normalize: if it's a single ['value'=>..., 'score'=>...] wrap it
		$items = isset($data['value']) ? [$data] : $data;

		$result = true;
		foreach ($items as $item) {
			if (! isset($item['value'], $item['score'])) {
				return false;
			}

			$result = $this->_redis->zAdd($id, (float) $item['score'], $item['value']) !== false && $result;
		}

		if ($result === false) {
			return false;
		}

		if ($ttl > 0) {
			$this->_redis->expire($id, $ttl);
		}

		return true;
	}

	/**
	 * Set one or more fields in a hash (append-only, fields are upserted).
	 * TTL is reset on every call.
	 *
	 * @param string $id   Cache key
	 * @param array  $data Field map or single field pair
	 * @param int    $ttl  Seconds (0 = no expiry)
	 * @return bool
	 */
	public function save_hash(string $id, array $data, int $ttl = 60)
	{
		if (empty($data)) {
			return false;
		}

		$result = $this->_redis->hMSet($id, $data);

		if ($result === false) {
			return false;
		}

		if ($ttl > 0) {
			$this->_redis->expire($id, $ttl);
		}

		return true;
	}

	/**
	 * Delete from cache
	 *
	 * @param	string	$key	Cache key
	 * @return	bool
	 */
	public function delete($key)
	{
		return ($this->_redis->del($key) === 1);
	}

	/**
	 * Remove one or more items from a collection (list/set/zset/hash).
	 * Auto-detects the Redis type. Ignores string types (use delete() for those).
	 * TTL is not modified.
	 *
	 * @param string $id    Cache key
	 * @param mixed  $data  For list/set/zset: value(s) to remove
	 *                      For hash: field name(s) to remove
	 * @return int|false    Number of items removed, or FALSE on error
	 */
	public function delete_from(string $id, mixed $data)
	{
		$type = $this->_redis->type($id);
		$items = is_array($data) ? $data : [$data];

		switch ($type) {
			case Redis::REDIS_LIST:
				$removed = 0;
				foreach ($items as $item) {
					$count = $this->_redis->lRem($id, $item, 0);
					if ($count === false) {
						return false;
					}
					$removed += $count;
				}
				return $removed;

			case Redis::REDIS_SET:
				return $this->_redis->sRem($id, ...$items);

			case Redis::REDIS_ZSET:
				return $this->_redis->zRem($id, ...$items);

			case Redis::REDIS_HASH:
			case Redis::REDIS_STRING:
			case Redis::REDIS_NOT_FOUND:
				return false;

			default:
				return false;
		}
	}

	/**
	 * Remove one or more fields from a hash.
	 * TTL is not modified.
	 *
	 * @param string $id     Cache key
	 * @param array  $fields Field names to remove
	 * @return int|false     Number of fields removed, or FALSE on error
	 */
	public function delete_hash_fields(string $id, array $fields)
	{
		$type = $this->_redis->type($id);
		switch ($type) {
			case Redis::REDIS_HASH:
				return $this->_redis->hDel($id, ...$fields);

			case Redis::REDIS_NOT_FOUND:
				return 0;  // Key doesn't exist, nothing to delete

			default:
				return false;  // Wrong type
		}
	}

	/**
	 * Get cache
	 *
	 * @param	string	$key	Cache ID
	 * @return	mixed
	 */
	public function get($key)
	{
		$type = $this->_redis->type($key);

		switch ($type) {

			case Redis::REDIS_STRING:
				$value = $this->_redis->get($key);

				if (
					is_string($value) &&
					str_starts_with($value, self::SERIALIZE_PREFIX)
				) {
					return unserialize(
						substr($value, strlen(self::SERIALIZE_PREFIX))
					);
				}

				return $value;

			case Redis::REDIS_HASH:
				return $this->_redis->hGetAll($key);

			case Redis::REDIS_LIST:
				return $this->_redis->lRange($key, 0, -1);

			case Redis::REDIS_SET:
				return $this->_redis->sMembers($key);

			case Redis::REDIS_ZSET:
				return $this->_redis->zRange($key, 0, -1, true);
				// returns member => score

			default:
				return null;
		}
	}

	/**
	 * Publish a message to a channel
	 *
	 * @param string $channel Channel name
	 * @param mixed $message Message (will be JSON encoded if array)
	 * @return int Number of subscribers that received the message
	 */
	public function publish(string $channel, mixed $message)
	{
		if ($this->_redis === null) {
			return -1;
		}

		// Auto-encode arrays/objects to JSON
		if (!is_string($message)) {
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
		if ($this->_redis === null) {
			return;
		}

		if (!is_array($channels)) {
			$channels = [$channels];
		}

		if ($this->channelPrefix != '') {
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
		if ($this->_redis === null) {
			return;
		}

		if (!is_array($patterns)) {
			$patterns = [$patterns];
		}

		if ($this->channelPrefix != '') {
			foreach ($patterns as &$pattern) {
				$pattern = $this->channelPrefix . $pattern;
			}
			unset($pattern);
		}

		try {
			$this->_redis->psubscribe($patterns, function ($redis, $pattern, $channel, $message) use ($callback) {
				if ($this->channelPrefix != '') {
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
