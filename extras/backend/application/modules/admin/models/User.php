<?php

(defined('BASEPATH')) or exit('No direct script access allowed');

class User extends MY_Model
{
	private ?array $user_groups = null;

	public function validate_group($user_id, $group, $url = false)
	{
		if ($this->user_groups === null) {
			$this->user_groups = $this->get_user_group_names($user_id);
		}

		if (!is_array($group)) {
			if (in_array($group, $this->user_groups)) {
				return true;
			}
		} else {
			$result = array_intersect($group, $this->user_groups);
			if (!empty($result)) {
				return true;
			}
		}

		if ($url == false) {
			return false;
		} else {
			redirect($url);
		}
	}

	public function get_user_group_names($user_id)
	{
		$query = "SELECT g.name FROM user_group
		 					LEFT JOIN `group` AS g ON g.id = user_group.group_id
							WHERE user_id = ? ";
		$user_groups = $this->query($query, [$user_id]);

		$result = [];
		foreach ($user_groups as $row) {
			$result[] = $row['name'];
		}
		return $result;
	}

	public function get_highest_level($user_id)
	{
		$query = "SELECT g.level FROM user_group
		 					LEFT JOIN `group` AS g ON g.id = user_group.group_id
							WHERE user_id = ?
							ORDER BY g.level DESC
							LIMIT 1";
		$user_group = $this->query($query, [$user_id]);

		if (!empty($user_group)) {
			return $user_group[0]['level'];
		}

		return 0;
	}
}
