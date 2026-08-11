<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Mgr_date_option
{
	public const start_of_next_seven_days = 'start_of_next_seven_days';
	public const start_of_day = 'start_of_day';
	public const end_of_day = 'end_of_day';
	public const start_of_week = 'start_of_week';
	public const end_of_week = 'end_of_week';
	public const start_of_last_six_days = 'start_of_last_six_days';
	public const start_of_last_seven_days = 'start_of_last_seven_days';
	public const start_of_last_week = 'start_of_last_week';
	public const end_of_last_week = 'end_of_last_week';
	public const start_of_month = 'start_of_month';
	public const end_of_month = 'end_of_month';
	public const start_of_year = 'start_of_year';
	public const end_of_year = 'end_of_year';
}

function mgr_get_date_option_obj(string $option, $date = null): ?DateTime
{
	if (empty($date)) {
		$date = new DateTime();
	} elseif (is_string($date)) {
		$date = mgr_create_date_time($date);
	} else {
		$date = clone $date;
	}

	switch ($option) {
		case Mgr_date_option::start_of_next_seven_days:
			$date->modify('+7 days')->setTime(0, 0, 0);
			return $date;

		case Mgr_date_option::start_of_day:
			$date->setTime(0, 0, 0);
			return $date;

		case Mgr_date_option::end_of_day:
			$date->setTime(23, 59, 59);
			return $date;

		case Mgr_date_option::start_of_week:
			$date->modify('this week')->setTime(0, 0, 0);
			return $date;

		case Mgr_date_option::end_of_week:
			$date->modify('this week +6 days')->setTime(23, 59, 59);
			return $date;

		case Mgr_date_option::start_of_last_six_days:
			$date->modify('-6 days')->setTime(0, 0, 0);
			return $date;

		case Mgr_date_option::start_of_last_seven_days:
			$date->modify('-7 days')->setTime(0, 0, 0);
			return $date;

		case Mgr_date_option::start_of_last_week:
			$date->modify('last week')->setTime(0, 0, 0);
			return $date;

		case Mgr_date_option::end_of_last_week:
			$date->modify('last week +6 days')->setTime(23, 59, 59);
			return $date;

		case Mgr_date_option::start_of_month:
			$date->setDate((int)$date->format('Y'), (int)$date->format('m'), 1)->setTime(0, 0, 0);
			return $date;

		case Mgr_date_option::end_of_month:
			$date->modify('last day of this month')->setTime(23, 59, 59);
			return $date;

		case Mgr_date_option::start_of_year:
			$date->setDate((int)$date->format('Y'), 1, 1)->setTime(0, 0, 0);
			return $date;

		case Mgr_date_option::end_of_year:
			$date->setDate((int)$date->format('Y'), 12, 31)->setTime(23, 59, 59);
			return $date;

		default:
			return null;
	}
}

function mgr_get_date_option_unix(string $option, $date = null): ?int
{
	$dateObj = mgr_get_date_option_obj($option, $date);
	return $dateObj ? $dateObj->getTimestamp() : null;
}

function mgr_get_date_option(string $option, $date = null): string
{
	$dateObj = mgr_get_date_option_obj($option, $date);
	return (!empty($dateObj)) ? $dateObj->format('Y-m-d H:i:s') : '';
}

/**
 * Create DateTime object from various string formats
 *
 * @param string|null $date_string Date string to parse
 * @param string|null $format Specific format to expect (e.g., 'Y-m-d H:i:s')
 * @return DateTime|null DateTime object or null if parsing fails
 */
function mgr_create_date_time(?string $date_string = null, ?string $format = null): ?DateTime
{
	if (empty($date_string)) {
		return mgr_get_now_date_time();
	}

	$app_timezone = new DateTimeZone(date_default_timezone_get());

	if ($format !== null) {
		$date_time = DateTime::createFromFormat($format, $date_string);

		if ($date_time !== false) {
			$errors = DateTime::getLastErrors();
			if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
				log_message('debug', "Date format warnings/errors for '{$date_string}' with format '{$format}'");
			} else {
				return $date_time->setTimezone($app_timezone);
			}
		}
	}

	try {
		// converges an offset-suffixed input (TIMESTAMPTZ/TIMESTAMP reads) onto
		// the app timezone instead of leaving it locked to its parsed offset.
		return (new DateTime($date_string))->setTimezone($app_timezone);
	} catch (Exception $e) {
		log_message('error', "Failed to parse date string '{$date_string}': " . $e->getMessage());
		return null;
	}
}

/**
 * Format a Timestamp column's raw driver value as ISO-8601, so API output
 * reads identically regardless of the connected engine (MySQL's naive
 * string vs. Postgres/SQL-Server's offset-suffixed one).
 */
function mgr_format_date_time_iso(string $raw_value): ?string
{
	return mgr_create_date_time($raw_value)?->format(DateTimeInterface::ATOM);
}

/**
 * Standardizes any input into a Unix Timestamp for BIGINT columns.
 * Reuses mgr_create_date_time to ensure consistent parsing.
 */
function mgr_to_unix($date): ?int
{
	if (empty($date)) {
		return null;
	}

	// If it's already an integer (Unix time), just return it
	if (is_numeric($date)) {
		return (int) $date;
	}

	// Use your existing robust parsing logic
	$dateObj = ($date instanceof DateTime) ? $date : mgr_create_date_time($date);

	return ($dateObj) ? $dateObj->getTimestamp() : null;
}
