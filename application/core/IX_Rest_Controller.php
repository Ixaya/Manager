<?php
defined('BASEPATH') or exit('No direct script access allowed');

class IX_Rest_Controller extends REST_Controller
{
	protected $user_id = '';
	protected $group_methods = [];
	public $logged_in_level;
	public $user_group;

	public function __construct()
	{
		parent::__construct();

		date_default_timezone_set('UTC');

		if (isset($this->_apiuser)) {
			$this->rest->db->query("SET SESSION time_zone='-00:00'");

			$this->user_id = $this->_apiuser->user_id;

			$this->load->library(['user_agent', 'ion_auth']);

			$now = new DateTime('now', new DateTimeZone('UTC'));
			$data['last_activity_date'] = $now->format('Y-m-d H:i:s');
			$data['last_activity_os'] = $this->getPlatform();

			$this->rest->db->where('id', $this->user_id);
			$this->rest->db->update('user', $data);

			$user_groups = $this->ion_auth->get_users_groups($this->user_id)->result();
			foreach ($user_groups as $user_group) {
				if ($this->logged_in_level < $user_group->level)
					$this->logged_in_level = $user_group->level;
			}
		}
	}

	//Called before any function to validate the priviledges of the user
	public function _remap($object_called, $arguments = [])
	{
		if (isset($this->_apiuser)) {
			$controller_method = $object_called . '_' . $this->request->method;

			// If no level is set use 0, they probably aren't using permissions
			$level = 0;
			if (isset($this->group_methods[$controller_method]['level'])) {
				$level = $this->group_methods[$controller_method]['level'];
			} else if (isset($this->group_methods['*']['level'])) {
				$level = $this->group_methods['*']['level'];
			}



			$group = null;
			if (isset($this->group_methods[$controller_method]['group'])) {
				$group = $this->group_methods[$controller_method]['group'];
			} else if (isset($this->group_methods['*']['group'])) {
				$group = $this->group_methods['*']['group'];
			}

			if ($level > 0 && $group != null) {
				if (!$this->validate_level($level) && !$this->validate_group($group)) {
					$this->response(['status' => 0, 'message' => 'User not authorized'], REST_Controller::HTTP_UNAUTHORIZED);
				}
			} else if ($level > 0 && !$this->validate_level($level)) {
				$this->response(['status' => 0, 'message' => 'User level not authorized'], REST_Controller::HTTP_UNAUTHORIZED);
			} else if ($group != null && !$this->validate_group($group)) {
				$this->response(['status' => 0, 'message' => 'User group not authorized'], REST_Controller::HTTP_UNAUTHORIZED);
			}
		}

		parent::_remap($object_called, $arguments);
	}

	public function setupModel($model = false, $modelName = false)
	{
		if ($modelName == false) {
			$this->main_model->db->query("SET SESSION time_zone='-00:00'");

			if (is_a($this->main_model, 'API_Model'))
				$this->main_model->user_id = $this->user_id;
		} else {
			$this->load->model($model, $modelName);
			$this->{$modelName}->db->query("SET SESSION time_zone='-00:00'");

			if (is_a($this->{$modelName}, 'API_Model'))
				$this->{$modelName}->user_id = $this->user_id;
		}
	}

	public function addAgentData(&$data)
	{
		$data['user_agent'] = $this->agent->agent_string();
		$data['os_kind'] = $this->getPlatform();
	}

	public function getPlatform()
	{
		$platform = $this->agent->platform();
		if ($platform == 'iOS')
			return 1;
		if ($platform == 'Android')
			return 2;

		return 0;
	}

	public function validate_level($level)
	{
		if ($this->logged_in_level < $level) {
			return false;
		}

		return true;
	}

	public function validate_group($group, $url = NULL)
	{
		if (!isset($this->user)) {
			$this->load->model('admin/user');
		}

		return $this->user->validate_group($this->user_id, $group, $url);
	}

	public function validate_access($level, $group)
	{
		if ($level !== FALSE && $this->logged_in_level >= $level) {
			return TRUE;
		}

		if ($group == FALSE) {
			return FALSE;
		}

		if (!isset($this->user)) {
			$this->load->model('admin/user');
		}

		return $this->user->validate_group($this->user_id, $group, FALSE);
	}

	public function print_log($object)
	{
		$now = new DateTime('now', new DateTimeZone('UTC'));
		$timestamp = $now->format('Y-m-d H:i:s');
		echo (PHP_EOL . $timestamp . '(' . get_called_class() . '): ' . json_encode($object));
	}
}
