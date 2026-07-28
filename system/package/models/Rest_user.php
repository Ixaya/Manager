<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Rest_user extends MY_Model
{
	/**
	 * Points the model at the `user` table before the parent connects.
	 */
	public function __construct()
	{
		$this->table_name = 'user';
		parent::__construct();
	}

	/**
	 * @deprecated Use Rest_user_group::validate_group() — this now only delegates.
	 */
	public function validate_group($user_id, $group, $url = null)
	{
		$this->load->model('rest_user_group');
		return $this->rest_user_group->validate_group($user_id, $group, $url);
	}

	/**
	 * @deprecated Use Rest_user_group::get_user_group_names() — this now only delegates.
	 */
	public function get_user_group_names($user_id)
	{
		$this->load->model('rest_user_group');
		return $this->rest_user_group->get_user_group_names($user_id);
	}

	/**
	 * @deprecated Use Rest_user_group::get_highest_level() — this now only delegates.
	 */
	public function get_highest_level($user_id)
	{
		$this->load->model('rest_user_group');
		return $this->rest_user_group->get_highest_level($user_id);
	}
}
