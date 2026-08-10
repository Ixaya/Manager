<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Manager_option extends MY_Model
{
	public function __construct()
	{
		$this->primary_key = 'key';

		parent::__construct();
	}

	public function save_value(string $key, mixed $value): int|string|bool
	{
		if (!empty($key)) {
			return $this->upsert_where(data: ['value' => $value], where: ['key' => $key]);
		}

		return false;
	}

	public function get_value(string $key, mixed $default = null): mixed
	{
		if (!empty($key)) {
			$result = $this->get($key);
			if (!empty($result['value'])) {
				return $result['value'];
			}
		}

		return $default;
	}
}
