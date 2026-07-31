<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (! function_exists('ui_selected_item')) {
	function ui_selected_item($current_value, $value)
	{
		if ($current_value == $value) {
			return 'selected="selected"';
		}

		return '';
	}
}
