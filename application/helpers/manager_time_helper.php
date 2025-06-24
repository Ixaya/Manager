<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Mngr_date_option
{
	const start_of_day = 'start_of_day';
	const end_of_day = 'end_of_day';
	const start_of_week = 'start_of_week';
	const end_of_week = 'end_of_week';
	const start_of_six_days = 'start_of_six_days';
	const start_of_seven_days = 'start_of_seven_days';
	const start_of_last_week = 'start_of_last_week';
	const end_of_last_week = 'end_of_last_week';
	const start_of_month = 'start_of_month';
	const end_of_month = 'end_of_month';
	const start_of_year = 'start_of_year';
	const end_of_year = 'end_of_year';
}

function mngr_get_date_option(string $option, $date = null): string
{
	if (empty($date)){
		$date = new DateTime();
	} else {
		$date = clone $date;
	}

	switch ($option) {
		case Mngr_date_option::start_of_day:
			return $date->format('Y-m-d') . ' 00:00:00';
		case Mngr_date_option::end_of_day:
			return $date->format('Y-m-d') . ' 23:59:59';
		case Mngr_date_option::start_of_week:
			$date->modify('this week');
			return $date->format('Y-m-d') . ' 00:00:00';
		case Mngr_date_option::end_of_week:
			$date->modify('this week +6 days');
			return $date->format('Y-m-d') . ' 23:59:59';
		case Mngr_date_option::start_of_six_days:
			$date->modify('-6 days');
			return $date->format('Y-m-d') . ' 00:00:00';
		case Mngr_date_option::start_of_seven_days:
			$date->modify('-7 days');
			return $date->format('Y-m-d') . ' 00:00:00';
		case Mngr_date_option::start_of_last_week:
			$date->modify('last week');
			return $date->format('Y-m-d') . ' 00:00:00';
		case Mngr_date_option::end_of_last_week:
			$date->modify('last week +6 days');
			return $date->format('Y-m-d') . ' 23:59:59';
		case Mngr_date_option::start_of_month:
			return $date->format('Y-m') . '-01 00:00:00';
		case Mngr_date_option::end_of_month:
			$date->modify('last day of this month');
			return $date->format('Y-m-d') . ' 23:59:59';
		case Mngr_date_option::start_of_year:
			return $date->format('Y') . '-01-01 00:00:00';
		case Mngr_date_option::end_of_year:
			return $date->format('Y') . '-12-31 23:59:59';
		default:
			return '';
			// throw new InvalidArgumentException("Invalid Option");
	}
}

function mngr_create_date_time($date_string, $format = null)
{
	if ($format != null){
		$date_time = DateTime::createFromFormat($format, $date_string);
		if ($date_time !== false){
			echo 'ooof1';
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