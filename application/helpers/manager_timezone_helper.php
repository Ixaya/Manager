<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Set PHP date default timezone.
 *
 * @param string $time_zone The timezone identifier (e.g., "America/New_York").
 * @return void
 */
function mngr_date_default_timezone_set($timezone)
{
	// Get the list of valid timezone identifiers
	$valid_timezones = timezone_identifiers_list();

	// Check if the provided timezone is valid
	if (in_array($timezone, $valid_timezones)) {
		ini_set('date.timezone', $timezone);
		date_default_timezone_set($timezone);
	}
}

/**
 * Get the timezone offset in `±HH:MM` format.
 *
 * @param string $time_zone The timezone identifier (e.g., "America/New_York").
 * @return string|bool The timezone offset (e.g., "-05:00").
 */
function mngr_get_time_zone_offset($time_zone)
{
	if (empty($time_zone)) {
		return false;
	}

	// Check if the time zone is already in offset format
	if (preg_match('/^[+-]\d{2}:\d{2}$/', $time_zone)) {
		return $time_zone;
	}

	// Try to get the offset from a valid time zone name
	try {
		$timezone = new DateTimeZone($time_zone);
		$datetime = new DateTime('now', $timezone);
		$offset_in_seconds = $timezone->getOffset($datetime);

		$hours = intdiv($offset_in_seconds, 3600);
		$minutes = abs($offset_in_seconds % 3600) / 60;

		return sprintf("%+03d:%02d", $hours, $minutes);
	} catch (Exception $e) {
		return false; // Invalid time zone name
	}
}

/**
 * Get the current date and time with an optional timezone.
 *
 * @param string|null $time_zone The timezone identifier (e.g., "America/New_York"), or null for the default timezone.
 * @return DateTime The current date and time object.
 */
function mngr_get_now_date_time($time_zone = null)
{
	$date_time_zone = !empty($time_zone) ? new DateTimeZone($time_zone) : null;
	return new DateTime('now', $date_time_zone);
}