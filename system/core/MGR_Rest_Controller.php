<?php

defined('BASEPATH') or exit('No direct script access allowed');

require dirname(__FILE__) . "/../third_party/REST_Controller.php";

class MGR_Rest_Controller extends REST_Controller
{
	/** @var string */
	protected $user_id = '';
	/** @var array<string, array{level?: int, group?: mixed}> */
	protected array $group_methods = [];
	/** @var string|null */
	protected $time_zone = null;

	public ?int $logged_in_level = null;

	public function __construct()
	{
		parent::__construct();

		$this->time_zone = $this->config->item('rest_time_zone');
		if (!empty($this->time_zone)) {
			mgr_date_default_timezone_set($this->time_zone);
		}

		if (isset($this->_apiuser)) {
			$offset = mgr_get_time_zone_offset($this->time_zone);
			if ($offset !== false) {
				$this->set_rest_timezone($offset);
			}

			$this->process_api_user();
		}
	}

	protected function process_api_user()
	{
		$this->user_id = $this->_apiuser->user_id;

		$now = mgr_get_now_date_time();

		$data['last_api_date'] = $now->format('Y-m-d H:i:s');
		$data['last_api_os'] = $this->get_platform();

		$this->rest->db->where('id', $this->user_id);
		$this->rest->db->update('user', $data);

		$this->load->model('ion_auth_model');
		$user_groups = $this->ion_auth_model->get_users_groups($this->user_id)->result();
		foreach ($user_groups as $user_group) {
			$user_group_level = (int) $user_group->level;
			if ($this->logged_in_level < $user_group_level) {
				$this->logged_in_level = $user_group_level;
			}
		}
	}

	protected function set_rest_timezone(string $offset)
	{
		$offset = $this->rest->db->escape_str($offset);
		$driver = MgrDriver::fromCI($this->rest->db->dbdriver ?? '');
		$sql = match ($driver) {
			MgrDriver::MySQL,
			MgrDriver::MariaDB  => "SET SESSION time_zone = '{$offset}'",
			MgrDriver::Postgres => "SET TIME ZONE '{$offset}'",
			default             => null,
		};

		if ($sql !== null) {
			$this->rest->db->query($sql);
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
			} elseif (isset($this->group_methods['*']['level'])) {
				$level = $this->group_methods['*']['level'];
			}



			$group = null;
			if (isset($this->group_methods[$controller_method]['group'])) {
				$group = $this->group_methods[$controller_method]['group'];
			} elseif (isset($this->group_methods['*']['group'])) {
				$group = $this->group_methods['*']['group'];
			}

			if ($level > 0 && $group != null) {
				if (!$this->validate_level($level) && !$this->validate_group($group)) {
					$this->response(['status' => 0, 'message' => 'User not authorized'], REST_Controller::HTTP_UNAUTHORIZED);
				}
			} elseif ($level > 0 && !$this->validate_level($level)) {
				$this->response(['status' => 0, 'message' => 'User level not authorized'], REST_Controller::HTTP_UNAUTHORIZED);
			} elseif ($group != null && !$this->validate_group($group)) {
				$this->response(['status' => 0, 'message' => 'User group not authorized'], REST_Controller::HTTP_UNAUTHORIZED);
			}
		}

		parent::_remap($object_called, $arguments);
	}

	public function setup_model($model, $model_name)
	{
		if (!isset($this->{$model_name})) {
			$this->load->model($model);
		}

		$this->{$model_name}->set_database_time_zone($this->time_zone);

		if (is_a($this->{$model_name}, 'API_Model')) {
			$this->{$model_name}->user_id = $this->user_id;
		}
	}

	public function add_agent_data(&$data)
	{
		$data['os_kind'] = $this->get_platform();
		$data['user_agent'] = $this->agent->agent_string();
	}

	public function get_platform()
	{
		$this->load->library('user_agent');

		$platform = $this->agent->platform();
		if ($platform == 'iOS') {
			return 1;
		}
		if ($platform == 'Android') {
			return 2;
		}

		return 0;
	}

	public function validate_level($level)
	{
		if ($this->logged_in_level < $level) {
			return false;
		}

		return true;
	}

	public function validate_group($group, $url = null)
	{
		if (!isset($this->rest_user)) {
			$this->load->model('rest_user');
		}

		return $this->rest_user->validate_group($this->user_id, $group, $url);
	}

	public function validate_access($level, $group)
	{
		if ($level !== false && $this->logged_in_level >= $level) {
			return true;
		}

		if ($group == false) {
			return false;
		}

		if (!isset($this->rest_user)) {
			$this->load->model('rest_user');
		}

		return $this->rest_user->validate_group($this->user_id, $group, false);
	}

	protected function build_list_params(
		string $default_order_by = 'id',
		string $default_order    = 'ASC',
		int    $default_limit    = 10,
	): array {
		$page  = $this->get('page');
		$limit = $this->get('limit');
		$order = strtoupper($this->get('order') ?? '');

		return [
			'page'     => ($page  && is_numeric($page)  && $page  > 0) ? (int)$page : 1,
			'limit'    => ($limit && is_numeric($limit) && $limit > 0) ? (int)$limit : $default_limit,
			'search'   => trim($this->get('search_query') ?? ''),
			'order'    => in_array($order, ['ASC', 'DESC']) ? $order : $default_order,
			'order_by' => trim($this->get('order_by') ?? '') ?: $default_order_by,
		];
	}

	protected function _apply_cors_headers(string $origin, string $method): void
	{
		parent::_apply_cors_headers($origin, $method);

		if ($method == 'options') {
			$cors_max_age = $this->config->item('cors_max_age');
			if ($cors_max_age > 0) {
				header('Access-Control-Max-Age: ' . $cors_max_age);
			}
		}

		header('Vary: Origin ');
	}

	public function print_log(object $object)
	{
		$now = mgr_get_now_date_time();

		$timestamp = $now->format('Y-m-d H:i:s');
		echo (PHP_EOL . $timestamp . '(' . get_called_class() . '): ' . json_encode($object));
	}
}
