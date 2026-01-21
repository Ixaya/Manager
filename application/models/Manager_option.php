<?php defined('BASEPATH') or exit('No direct script access allowed');

class Manager_option extends MY_Model
{
	public function __construct()
	{
		$this->primary_key = 'key';

		parent::__construct();
	}

	public function save_value($key, $value)
	{
		if (!empty($key)) {

			$data = ['key' => $key, 'value' => $value];
			return $this->replace($data);
		}

		return NULL;
	}

	public function get_value($key, $default = NULL)
	{
		if (!empty($key)) {
			$result = $this->get($key);
			if (!empty($result['value'])){
				return $result['value'];
			}
		}

		return $default;
	}
}
