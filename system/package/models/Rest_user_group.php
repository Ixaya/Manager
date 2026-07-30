<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Rest_user_group extends MY_Model
{
	/** @var array<int|string, array<int, string>> Group names cache, keyed by user_id. */
	protected array $user_groups = [];

	/**
	 * Points the model at the `user_group` pivot table before the parent connects.
	 */
	public function __construct()
	{
		$this->table_name = 'user_group';

		parent::__construct();
	}

	/**
	 * Checks whether $user_id belongs to $group. Caches resolved group names per $user_id.
	 *
	 * @param int|string   $user_id User to check.
	 * @param string|array $group   Group name, or a list of group names to match any of.
	 * @param string|null  $url     When set and validation fails, redirects there instead of returning false.
	 * @return bool
	 */
	public function validate_group($user_id, $group, $url = null)
	{
		if (!isset($this->user_groups[$user_id])) {
			$this->user_groups[$user_id] = $this->get_user_group_names($user_id);
		}

		$user_groups = $this->user_groups[$user_id];

		if (!is_array($group)) {
			if (in_array($group, $user_groups)) {
				return true;
			}
		} else {
			$result = array_intersect($group, $user_groups);
			if (!empty($result)) {
				return true;
			}
		}

		// $url still accepts the deprecated `false` sentinel at runtime even
		// though the doc type narrows it to string|null — hence the two ignores.
		if ($url !== false && mgr_provided($url)) { // @phpstan-ignore notIdentical.alwaysTrue
			redirect($url); // @phpstan-ignore return.missing (redirect() always exits)
		} else {
			return false;
		}
	}

	/**
	 * Fetches the names of every group $user_id belongs to.
	 *
	 * @param int|string $user_id
	 * @return array<int, string>
	 */
	public function get_user_group_names($user_id)
	{
		$rows = $this->get_all_join(
			fields: 'group.name',
			where: ['user_group.user_id' => $user_id],
			join_table: 'group',
			join_where: 'group.id = user_group.group_id',
			join_method: 'left',
		);

		// Fails closed: this feeds authorization, so a failed lookup means no groups.
		return array_column($rows ?? [], 'name');
	}

	/**
	 * Returns $user_id's highest group level, or 0 if they belong to no group.
	 *
	 * @param int|string $user_id
	 * @return int
	 */
	public function get_highest_level($user_id)
	{
		$rows = $this->get_all_join(
			fields: 'group.level',
			where: ['user_group.user_id' => $user_id],
			limit: 1,
			order_by: 'group.level DESC',
			join_table: 'group',
			join_where: 'group.id = user_group.group_id',
			join_method: 'left',
		);

		return $rows ? $rows[0]['level'] : 0;
	}
}
