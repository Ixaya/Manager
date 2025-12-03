<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Get the URL for an image, optionally specifying the protocol.
 *
 * @param string $uri The URI of the image.
 * @param string|null $protocol The protocol to use (e.g., 'http' or 'https'), or null to use the default.
 * @return string The full URL to the image.
 */
function image_url($uri = '', $protocol = NULL)
{
	return get_instance()->config->image_url($uri, $protocol);
}

/**
 * Generate a secure (HTTPS) URL from a given URI.
 *
 * @param string $uri The URI to be processed.
 * @return string The secure HTTPS URL.
 */
function secure_url($uri = '')
{
	if (!empty($uri)) {
		if (strpos($uri, 'https://') === 0)
			return $uri;

		if (strpos($uri, '//') === 0)
			return 'https:' . $uri;
	}

	return get_instance()->config->base_url($uri, 'https:');
}

/**
 * Generates a secure random hexadecimal hash of the specified length.
 *
 * @param int $length The desired length of the hash. Default is 32.
 * 
 * @return string The generated hexadecimal hash.
 */
function mngr_generate_hash($length = 32)
{
	$bytes_needed = (int)ceil($length / 2);
	$bytes = random_bytes($bytes_needed);
	$hex = bin2hex($bytes);

	return substr($hex, 0, $length);
}

/**
 * Formats a hexadecimal hash by grouping characters and separating them using a specified separator.
 *
 * @param string $hash The hash to format.
 * @param int $group_by The number of characters per group.
 * @param string $separator Optional. The separator to use between groups. Default is '-'.
 * 
 * @return string The formatted hash in uppercase with groups separated by the specified separator.
 */
function mngr_format_hash($hash, $group_by, $separator = '-')
{
	if (empty($hash)) {
		return '';
	}

	$folio = strtoupper($hash);
	return implode($separator, str_split($folio, $group_by));
}

/**
 * Removes the formatting from a formatted hash by removing the separator and joining the groups.
 *
 * @param string $folio The formatted hash to unformat.
 * @param string $separator Optional. The separator used in the formatted hash. Default is '-'.
 * 
 * @return string The unformatted hash as a continuous string.
 */
function mngr_unformat_hash($folio, $separator = '-')
{
	if (empty($folio)) {
		return '';
	}

	$hash = explode($separator, $folio);
	return implode('', $hash);
}

/**
 * Processes an exception, logs the details and returns the message
 * 
 * @param Exception|Throwable $exception The exception to process
 * @param string $context Additional context for the log (optional)
 * @return string The exception message
 */
function mngr_process_exception($exception, $context = '')
{
	// Build log message
	$log_message = sprintf(
		"[EXCEPTION] %s | File: %s | Line: %d | Message: %s",
		$context ? $context : 'Error',
		$exception->getFile(),
		$exception->getLine(),
		$exception->getMessage()
	);

	// Log the error
	log_message('error', $log_message);

	// Optional: include stack trace
	// log_message('error', 'Stack trace: ' . $exception->getTraceAsString());

	// Return the message
	return $exception->getMessage();
}

function mngr_cache_key($prefix, $params)
{
	// $values = array_values($params);
	// return $prefix . implode($values);
	// return $prefix . md5(json_encode($params));
	return $prefix . hash('sha256', json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}