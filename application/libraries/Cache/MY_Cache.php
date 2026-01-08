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

	/**
	 * Constructor
	 */
	public function __construct($config = array())
	{
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
			: FALSE;
	}

	/**
	 * Save data to cache
	 *
	 * @param string $id Cache ID
	 * @param mixed $data Data to cache
	 * @param int|null $ttl Time to live (NULL = use default)
	 * @param string|null $encoding Serialization method (NULL = use default)
	 * @return bool
	 */
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
	public function save($id, $data, $ttl = NULL, $raw = FALSE, $encoding = NULL)
	// public function save($id, $data, $ttl = NULL, $encoding = NULL)
	{
		$ttl = ($ttl === NULL) ? $this->default_ttl : $ttl;
		$encoding = ($encoding === NULL) ? $this->serialization : $encoding;

		if ($this->enable_logging) {
			log_message('debug', "Cache save: {$id}, TTL: {$ttl}, Encoding: {$encoding}");
		}

		// Serialize the data
		$serialized = $this->_serialize($data, $encoding);

		if ($serialized === FALSE) {
			log_message('error', "Cache serialization failed for key: {$id}");
			return FALSE;
		}

		return parent::save($id, $serialized, $ttl);
	}

	/**
	 * Get data from cache
	 *
	 * @param string $id Cache ID
	 * @param string|null $encoding Serialization method (NULL = use default)
	 * @return mixed|false
	 */
	public function get($id, $encoding = NULL)
	{
		$data = parent::get($id);

		if ($data === FALSE) {
			if ($this->enable_logging) {
				log_message('debug', "Cache miss: {$id}");
			}
			return FALSE;
		}

		if ($this->enable_logging) {
			log_message('debug', "Cache hit: {$id}");
		}

		$encoding = ($encoding === NULL) ? $this->serialization : $encoding;

		return $this->_unserialize($data, $encoding);
	}

	/**
	 * Delete from cache
	 *
	 * @param string $id Cache ID
	 * @return bool
	 */
	public function delete($id)
	{
		if ($this->enable_logging) {
			log_message('debug', "Cache delete: {$id}");
		}

		return parent::delete($id);
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
		if ($this->enable_logging) {
			$debug_patterns = (is_array($patterns)) ? implode('-', $patterns) : $patterns;
			log_message('debug', "Cache publish: {$debug_patterns}");
		}

		if (method_exists($this->{$this->_adapter}, 'psubscribe')) {
			return $this->{$this->_adapter}->psubscribe($patterns, $callback);
		}
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
			if ($encoding == null) {
				return $data;
			}

			switch ($encoding) {
				case 'json':
					$result = json_encode($data);
					if (json_last_error() !== JSON_ERROR_NONE) {
						log_message('error', 'JSON encode error: ' . json_last_error_msg());
						return FALSE;
					}
					return $result;

				case 'json_gzip':
					$json = json_encode($data);
					if (json_last_error() !== JSON_ERROR_NONE) {
						log_message('error', 'JSON encode error: ' . json_last_error_msg());
						return FALSE;
					}
					return gzcompress($json, 6);

				case 'msgpack':
					if (!function_exists('msgpack_pack')) {
						return msgpack_pack($data);
					}
					if (class_exists('MessagePack\MessagePack')) {
						return MessagePack\MessagePack::pack($data);
					}

					log_message('error', 'msgpack extension not available, falling back to JSON');
					return json_encode($data);

				case 'php':
				default:
					return serialize($data);
			}
		} catch (Exception $e) {
			log_message('error', 'Cache serialization exception: ' . $e->getMessage());
			return FALSE;
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
			if (!is_string($data) || $encoding == null) {
				return $data;
			}

			switch ($encoding) {
				case 'json':
					$result = json_decode($data, TRUE);
					if (json_last_error() !== JSON_ERROR_NONE) {
						log_message('error', 'JSON decode error: ' . json_last_error_msg());
						return FALSE;
					}
					return $result;

				case 'json_gzip':
					$decompressed = gzuncompress($data);
					if ($decompressed === FALSE) {
						log_message('error', 'gzip decompression failed');
						return FALSE;
					}
					$result = json_decode($decompressed, TRUE);
					if (json_last_error() !== JSON_ERROR_NONE) {
						log_message('error', 'JSON decode error: ' . json_last_error_msg());
						return FALSE;
					}
					return $result;

				case 'msgpack':
					if (!function_exists('msgpack_unpack')) {
						return msgpack_unpack($data);
					}
					if (class_exists('MessagePack\MessagePack')) {
						return MessagePack\MessagePack::unpack($data);
					}
					log_message('error', 'msgpack extension not available');
					return FALSE;

				case 'php':
				default:
					return unserialize($data);
			}
		} catch (Exception $e) {
			log_message('error', 'Cache unserialization exception: ' . $e->getMessage());
			return FALSE;
		}
	}
}
