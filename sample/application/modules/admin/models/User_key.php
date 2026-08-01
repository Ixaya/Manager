<?php

defined('BASEPATH') or exit('No direct script access allowed');

class User_key extends MY_Model
{
	public function get_by_user(string|int $id): ?string
	{
		$row = $this->get_where(['user_id' => $id]);

		return $row['key'] ?? null;
	}
}
