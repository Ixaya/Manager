<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Mngr_date_option
{
	const start_of_next_seven_days = 'start_of_next_seven_days';
	const start_of_day = 'start_of_day';
	const end_of_day = 'end_of_day';
	const start_of_week = 'start_of_week';
	const end_of_week = 'end_of_week';
	const start_of_last_six_days = 'start_of_last_six_days';
	const start_of_last_seven_days = 'start_of_last_seven_days';
	const start_of_last_week = 'start_of_last_week';
	const end_of_last_week = 'end_of_last_week';
	const start_of_month = 'start_of_month';
	const end_of_month = 'end_of_month';
	const start_of_year = 'start_of_year';
	const end_of_year = 'end_of_year';
}

function mngr_get_date_option_obj(string $option, $date = null): ?DateTime
{
	if (empty($date)) {
		$date = new DateTime();
	} elseif (is_string($date)) {
		$date = mngr_create_date_time($date);
	} else {
		$date = clone $date;
	}

	switch ($option) {
		case Mngr_date_option::start_of_next_seven_days:
			$date->modify('+7 days')->setTime(0, 0, 0);
			return $date;

		case Mngr_date_option::start_of_day:
			$date->setTime(0, 0, 0);
			return $date;

		case Mngr_date_option::end_of_day:
			$date->setTime(23, 59, 59);
			return $date;

		case Mngr_date_option::start_of_week:
			$date->modify('this week')->setTime(0, 0, 0);
			return $date;

		case Mngr_date_option::end_of_week:
			$date->modify('this week +6 days')->setTime(23, 59, 59);
			return $date;

		case Mngr_date_option::start_of_last_six_days:
			$date->modify('-6 days')->setTime(0, 0, 0);
			return $date;

		case Mngr_date_option::start_of_last_seven_days:
			$date->modify('-7 days')->setTime(0, 0, 0);
			return $date;

		case Mngr_date_option::start_of_last_week:
			$date->modify('last week')->setTime(0, 0, 0);
			return $date;

		case Mngr_date_option::end_of_last_week:
			$date->modify('last week +6 days')->setTime(23, 59, 59);
			return $date;

		case Mngr_date_option::start_of_month:
			$date->setDate((int)$date->format('Y'), (int)$date->format('m'), 1)->setTime(0, 0, 0);
			return $date;

		case Mngr_date_option::end_of_month:
			$date->modify('last day of this month')->setTime(23, 59, 59);
			return $date;

		case Mngr_date_option::start_of_year:
			$date->setDate((int)$date->format('Y'), 1, 1)->setTime(0, 0, 0);
			return $date;

		case Mngr_date_option::end_of_year:
			$date->setDate((int)$date->format('Y'), 12, 31)->setTime(23, 59, 59);
			return $date;

		default:
			return null;
	}
}

function mngr_get_date_option(string $option, $date = null): string
{
	$dateObj = mngr_get_date_option_obj($option, $date);
	return (!empty($dateObj)) ? $dateObj->format('Y-m-d H:i:s') : '';
}

function mngr_create_date_time($date_string, $format = null)
{
	if ($format != null) {
		$date_time = DateTime::createFromFormat($format, $date_string);
		if ($date_time !== false) {
			return $date_time;
		}
	}

	try {
		$date_time = new DateTime($date_string);
		return $date_time;
	} catch (Exception $e) {
		log_message('debug', "Error parsing date string: " . $e->getMessage());
	}

	$timestamp = strtotime($date_string);
	if ($timestamp !== false) {
		return (new DateTime())->setTimestamp($timestamp);
	}

	return null;
}
