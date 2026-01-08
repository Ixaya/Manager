<?php (defined('BASEPATH')) or exit('No direct script access allowed');

class User extends MY_Model {
	private $user_groups = NULL;

	public function __construct() {
		//overrides
		//$this->connection_name = '';
		//$this->table_name = '';
		//$this->override_column = '';
		//$this->soft_delete = true;

		//initialize after overriding
		parent::__construct();
	}

	public function validate_group($user_id, $group, $url = false)
	{
		if ($this->user_groups === NULL){
			$this->user_groups = $this->get_user_group_names($user_id);
		}

		if (!is_array($group)) {
			if (in_array($group, $this->user_groups))
				return TRUE;
		} else {
			$result = array_intersect($group, $this->user_groups);
			if (!empty($result))
				return TRUE;
		}

		if ($url == false){
			return FALSE;
		} else{
			redirect($url);
		}
	}

	public function get_user_group_names($user_id)
	{
		$query = "SELECT g.name FROM user_group
		 					LEFT JOIN `group` AS g ON g.id = user_group.group_id
							WHERE user_id = ? ";
		$user_groups = $this->query_as_array_auto($query, [$user_id]);

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
		$user_group = $this->query_as_array_auto($query, [$user_id]);

		if (!empty($user_group))
			return $user_group[0]['level'];

		return 0;
	}
}