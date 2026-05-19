<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Extended Cache Library
 *
 * Adds configurable serialization (JSON, msgpack, gzip) and default TTL
 * while maintaining backwards compatibility with CI's cache library
 */
class MY_Cache extends CI_Cache
{
	protected $default_ttl;
	protected $serialization;
	protected $enable_logging;
	protected $cache_bypass;

	/**
	 * Constructor
	 */
	public function __construct($config = [])
	{
		$this->cache_bypass = $this->_is_cache_bypass();
		if (!$this->cache_bypass) {
			parent::__construct($config);

			// Set configuration with fallbacks
			$this->default_ttl = isset($config['default_ttl'])
				? $config['default_ttl']
				: 3600;

			$this->serialization = isset($config['serialization'])
				? $config['serialization']
				: null;

			$this->enable_logging = isset($config['enable_logging'])
				? $config['enable_logging']
				: false;
		}
	}


	private function _is_cache_bypass(): bool
	{
		// Disable cache for pentest/dev IPs
		$env_ips = str_replace(' ', '', mngr_env('CACHE_BYPASS_IPS', ''));

		if ($env_ips === '') {
			return false;
		}

		$ci = &get_instance();
		$ip_address = $ci->input->ip_address();

		$bypass_ips = array_flip(explode(',', $env_ips));
		return isset($bypass_ips[$ip_address]);
	}

	/**
	 * Cache Save
	 *
	 * @param	string	$id	Cache ID
	 * @param	mixed	$data	Data to store
	 * @param	int|null $ttl Time to live (NULL = use default)
	 * @param	bool	$raw	Whether to store the raw value
	 * @param string|null $encoding Serialization method (NULL = use default)
	 * @return	bool	TRUE on success, FALSE on failure
	 */
	public function save($id, $data, $ttl = null, $raw = false, $encoding = null)
	{
		if ($this->cache_bypass) {
			return true;
		}

		$ttl = ($ttl === null) ? $this->default_ttl : $ttl;
		$encoding = ($encoding === null) ? $this->serialization : $encoding;

		if ($this->enable_logging) {
			log_message('debug', "Cache save: {$id}, TTL: {$ttl}, Encoding: {$encoding}");
		}

		if ($encoding !== 'none') {
			$data = $this->_serialize($data, $encoding);
			if ($data === false) {
				return false;
			}
		}

		return parent::save($id, $data, $ttl, $raw);
	}

	/**
	 * Save one or more items to a list (append-only).
	 * TTL is reset on every call.
	 *
	 * @param string $id       Cache key
	 * @param mixed  $data     Single item or array of items
	 * @param int    $ttl      Seconds (0 = no expiry, NULL = use default)
	 * @param string $encoding Serialization method (NULL = use default, 'none' = no serialization)
	 * @param bool   $prepend If true, add to beginning (lPush), if false add to end (rPush)
	 * @return bool
	 */
	public function save_list($id, $data, $ttl = null, $encoding = null, $prepend = false)
	{
		if ($this->cache_bypass) {
			return true;
		}

		$ttl = ($ttl === null) ? $this->default_ttl : $ttl;
		$encoding = ($encoding === null) ? $this->serialization : $encoding;

		if ($this->enable_logging) {
			log_message('debug', "Cache save_list: {$id}, TTL: {$ttl}, Encoding: {$encoding}");
		}

		$items = $this->_serialize_collection($data, $encoding);

		if ($items === false) {
			return false;
		}

		if (method_exists($this->{$this->_adapter}, 'save_list')) {
			return $this->{$this->_adapter}->save_list($id, $items, $ttl, $prepend);
		}

		log_message('error', "Driver does not support save_list method");
		return false;
	}

	/**
	 * Save one or more items to a set (append-only).
	 * TTL is reset on every call.
	 *
	 * @param string $id       Cache key
	 * @param mixed  $data     Single item or array of items
	 * @param int    $ttl      Seconds (0 = no expiry, NULL = use default)
	 * @param string $encoding Serialization method (NULL = use default, 'none' = no serialization)
	 * @return bool
	 */
	public function save_set($id, $data, $ttl = null, $encoding = null)
	{
		if ($this->cache_bypass) {
			return true;
		}

		$ttl = ($ttl === null) ? $this->default_ttl : $ttl;
		$encoding = ($encoding === null) ? $this->serialization : $encoding;

		if ($this->enable_logging) {
			log_message('debug', "Cache save_set: {$id}, TTL: {$ttl}, Encoding: {$encoding}");
		}

		$items = $this->_serialize_collection($data, $encoding);

		if ($items === false) {
			return false;
		}

		if (method_exists($this->{$this->_adapter}, 'save_set')) {
			return $this->{$this->_adapter}->save_set($id, $items, $ttl);
		}

		log_message('error', "Driver does not support save_set method");
		return false;
	}

	/**
	 * Save one or more items to a zset with auto-timestamp scores (append-only).
	 * TTL is reset on every call.
	 *
	 * @param string $id       Cache key
	 * @param mixed  $data     Single item or array of items
	 * @param int    $ttl      Seconds (0 = no expiry, NULL = use default)
	 * @param string $encoding Serialization method (NULL = use default, 'none' = no serialization)
	 * @return bool
	 */
	public function save_zset($id, $data, $ttl = null, $encoding = null)
	{
		if ($this->cache_bypass) {
			return true;
		}

		$ttl = ($ttl === null) ? $this->default_ttl : $ttl;
		$encoding = ($encoding === null) ? $this->serialization : $encoding;

		if ($this->enable_logging) {
			log_message('debug', "Cache save_zset: {$id}, TTL: {$ttl}, Encoding: {$encoding}");
		}

		$items = $this->_serialize_zcollection($data, $encoding);

		if ($items === false) {
			return false;
		}

		if (method_exists($this->{$this->_adapter}, 'save_zset')) {
			return $this->{$this->_adapter}->save_zset($id, $items, $ttl);
		}

		log_message('error', "Driver does not support save_zset method");
		return false;
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
	public function save_hash($id, array $data, $ttl = null)
	{
		if ($this->cache_bypass) {
			return true;
		}

		$ttl = ($ttl === null) ? $this->default_ttl : $ttl;

		if ($this->enable_logging) {
			log_message('debug', "Cache save_hash: {$id}, TTL: {$ttl}");
		}

		if (empty($data)) {
			return false;
		}

		if (method_exists($this->{$this->_adapter}, 'save_hash')) {
			return $this->{$this->_adapter}->save_hash($id, $data, $ttl);
		}

		log_message('error', "Driver does not support save_hash method");
		return false;
	}

	/**
	 * Get data from cache
	 *
	 * @param string $id Cache ID
	 * @param string|null $encoding Serialization method (NULL = use default)
	 * @return mixed|false
	 */
	public function get($id, $encoding = null)
	{
		if ($this->cache_bypass) {
			return false;
		}

		$data = parent::get($id);

		if ($data === false) {
			if ($this->enable_logging) {
				log_message('debug', "Cache miss: {$id}");
			}
			return false;
		}

		if ($this->enable_logging) {
			log_message('debug', "Cache hit: {$id}");
		}

		$encoding = ($encoding === null) ? $this->serialization : $encoding;

		if ($encoding !== null && $encoding !== 'none') {
			return $this->_unserialize($data, $encoding);
		}

		return $data;
	}

	/**
	 * Delete from cache
	 *
	 * @param string $id Cache ID
	 * @return bool
	 */
	public function delete($id)
	{
		if ($this->cache_bypass) {
			return true;
		}

		if ($this->enable_logging) {
			log_message('debug', "Cache delete: {$id}");
		}

		return parent::delete($id);
	}

	/**
	 * Remove one or more items from a collection (list/set/zset).
	 * Auto-detects the Redis type. Values are serialized before removal.
	 * TTL is not modified.
	 *
	 * @param string $id       Cache key
	 * @param mixed  $data     Value(s) to remove
	 * @param string $encoding Serialization method (NULL = use default, 'none' = no serialization)
	 * @return int|false       Number of items removed, or FALSE on error
	 */
	public function delete_from($id, $data, $encoding = null)
	{
		if ($this->cache_bypass) {
			return 1;
		}

		$encoding = ($encoding === null) ? $this->serialization : $encoding;

		// Serialize the values we're looking for (must match what was stored)
		$items = $this->_serialize_collection($data, $encoding);

		if ($items === false) {
			return false;
		}

		if (method_exists($this->{$this->_adapter}, 'delete_from')) {
			return $this->{$this->_adapter}->delete_from($id, $items);
		}

		log_message('error', "Driver does not support delete_from method");
		return false;
	}

	/**
	 * Remove one or more fields from a hash.
	 * TTL is not modified.
	 *
	 * @param string $id     Cache key
	 * @param mixed  $fields Single field name or array of field names (strings)
	 * @return int|false     Number of fields removed, or FALSE on error
	 */
	public function delete_hash_fields($id, $fields)
	{
		if ($this->cache_bypass) {
			return 1;
		}

		$fields = is_array($fields) ? $fields : [$fields];

		if (method_exists($this->{$this->_adapter}, 'delete_hash_fields')) {
			return $this->{$this->_adapter}->delete_hash_fields($id, $fields);
		}

		log_message('error', "Driver does not support delete_hash_fields method");
		return false;
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
		if ($this->cache_bypass) {
			return 0;
		}

		if ($this->enable_logging) {
			log_message('debug', "Cache publish: {$channel}");
		}

		if (method_exists($this->{$this->_adapter}, 'publish')) {
			return $this->{$this->_adapter}->publish($channel, $message);
		}

		return -1;
	}

	/**
	 * Subscribe to one or more channels
	 * This is a BLOCKING operation
	 *
	 * @param array|string $channels Channel(s) to subscribe to
	 * @param callable $callback Function to call when message received
	 */
	public function subscribe($channels, $callback)
	{
		if ($this->cache_bypass) {
			return true;
		}

		if ($this->enable_logging) {
			$debug_channels = (is_array($channels)) ? implode('-', $channels) : $channels;
			log_message('debug', "Cache publish: {$debug_channels}");
		}

		if (method_exists($this->{$this->_adapter}, 'subscribe')) {
			return $this->{$this->_adapter}->subscribe($channels, $callback);
		}
	}

	/**
	 * Subscribe to channels matching a pattern
	 * This is a BLOCKING operation
	 *
	 * @param array|string $patterns Pattern(s) to match
	 * @param callable $callback Function to call when message received
	 */
	public function psubscribe($patterns, $callback)
	{
		if ($this->cache_bypass) {
			return true;
		}

		if ($this->enable_logging) {
			$debug_patterns = (is_array($patterns)) ? implode('-', $patterns) : $patterns;
			log_message('debug', "Cache publish: {$debug_patterns}");
		}

		if (method_exists($this->{$this->_adapter}, 'psubscribe')) {
			return $this->{$this->_adapter}->psubscribe($patterns, $callback);
		}
	}

	/**
	 * Serialize all items in a collection.
	 *
	 * @param mixed  $data    Array of items to serialize
	 * @param string $encoding Serialization method
	 * @return array|false     Serialized items array, or FALSE on error
	 */
	private function _serialize_collection($data, $encoding)
	{
		$items = (is_array($data) && array_is_list($data)) ? $data : [$data];

		if ($encoding === 'none') {
			return $items;
		}

		$serialized = [];
		foreach ($items as $item) {
			$serialized_item = $this->_serialize($item, $encoding);
			if ($serialized_item === false) {
				return false;
			}
			$serialized[] = $serialized_item;
		}

		return $serialized;
	}

	/**
	 * Serialize all items in a collection with scores.
	 *
	 * @param mixed  $data    Array of items to serialize
	 * @param string $encoding Serialization method
	 * @return array|false     Serialized items array, or FALSE on error
	 */
	private function _serialize_zcollection($data, $encoding)
	{
		$items = (is_array($data) && array_is_list($data)) ? $data : [$data];

		if ($encoding === 'none') {
			return $items;
		}

		$serialized = [];
		foreach ($items as $item) {
			if (!isset($item['value'])) {
				log_message('error', 'Item missing value key');
				return false;
			}

			$serialized_value = $this->_serialize($item['value'], $encoding);
			if ($serialized_value === false) {
				return false;
			}

			$serialized_item = ['value' => $serialized_value];
			if (isset($item['score'])) {
				$serialized_item['score'] = $item['score'];
			}

			$serialized[] = $serialized_item;
		}

		return $serialized;
	}

	/**
	 * Serialize data based on encoding type
	 *
	 * @param mixed $data
	 * @param string $encoding
	 * @return string|false
	 */
	private function _serialize($data, $encoding)
	{
		try {
			if ($data === '' || $encoding === 'none') {
				return $data;
			}

			switch ($encoding) {
				case 'json':
					return json_encode($data, JSON_THROW_ON_ERROR);

				case 'json_gzip':
					$json = json_encode($data, JSON_THROW_ON_ERROR);
					return gzcompress($json, 6);

				case 'msgpack':
					if (function_exists('msgpack_pack')) {
						return msgpack_pack($data);
					}
					if (class_exists('MessagePack\MessagePack')) {
						return MessagePack\MessagePack::pack($data);
					}

					throw new RuntimeException('msgpack extension not available. Install with: pecl install msgpack');

				case 'php':
					return serialize($data);

				default:
					return $data;
			}
		} catch (Exception $e) {
			log_message('error', 'Cache serialization exception: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Unserialize data based on encoding type
	 *
	 * @param mixed $data
	 * @param string $encoding
	 * @return mixed|false
	 */
	private function _unserialize($data, $encoding)
	{
		try {
			if ($data === '' || !is_string($data) || $encoding === 'none') {
				return $data;
			}

			switch ($encoding) {
				case 'json':
					return json_decode($data, true, 512, JSON_THROW_ON_ERROR);

				case 'json_gzip':
					$decompressed = gzuncompress($data);
					if ($decompressed === false) {
						log_message('error', 'gzip decompression failed');
						return false;
					}
					return json_decode($decompressed, true, 512, JSON_THROW_ON_ERROR);

				case 'msgpack':
					if (function_exists('msgpack_unpack')) {
						return msgpack_unpack($data);
					}
					if (class_exists('MessagePack\MessagePack')) {
						return MessagePack\MessagePack::unpack($data);
					}

					throw new RuntimeException('msgpack extension not available. Install with: pecl install msgpack');

				case 'php':
					return unserialize($data);

				default:
					return $data;
			}
		} catch (Exception $e) {
			log_message('error', 'Cache unserialization exception: ' . $e->getMessage());
			return $data;
		}
	}
}
