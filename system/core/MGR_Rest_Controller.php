<?php

defined('BASEPATH') or exit('No direct script access allowed');

require dirname(__FILE__) . "/../third_party/REST_Controller.php";

class MGR_Rest_Controller extends REST_Controller
{
	/** @var string|null */
	protected $user_id = null;
	/** @var string|null */
	protected $time_zone = null;
	/** @var array<string, array{level?: int, group?: mixed}> */
	protected array $group_methods = [];
	public int $logged_in_level = 0;

	/**
	 * Applies the request's configured time zone and, for an identified API
	 * user, syncs the DB session time zone and loads their profile context.
	 */
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

	/**
	 * Populates the identified caller's context: user_id, last_api_date/os, and logged_in_level.
	 *
	 * @return void
	 */
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

	/**
	 * Applies $offset as the DB session time zone, using the cross-engine
	 * SQL for the active driver; does nothing on unsupported drivers.
	 *
	 * @param string $offset Time zone offset or name to set for this session.
	 * @return void
	 */
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

	/**
	 * Resolves the level/group gate configured for the called action in
	 * $group_methods (falling back to the '*' wildcard), then responds 401
	 * before the action runs if the caller doesn't satisfy it.
	 *
	 * level=0 and group='none' are the "no gate on this axis" sentinels
	 *
	 * @param string $object_called Base method name being routed to, without the HTTP verb suffix.
	 * @param array  $arguments     Arguments CI will pass to the routed method.
	 * @return void
	 */
	public function _remap($object_called, $arguments = [])
	{
		// Spans the whole body, not just the dispatch: the gate block reaches the DB
		// through validate_group(), and an Error thrown anywhere here escapes the
		// catch (Exception) inside parent::_remap() and would answer with no body.
		try {
			$controller_method = $object_called . '_' . $this->request->method;

			$level = 0;
			if (isset($this->group_methods[$controller_method]['level'])) {
				$level = $this->group_methods[$controller_method]['level'];
			} elseif (isset($this->group_methods['*']['level'])) {
				$level = $this->group_methods['*']['level'];
			}

			$group = 'none';
			if (isset($this->group_methods[$controller_method]['group'])) {
				$group = $this->group_methods[$controller_method]['group'];
			} elseif (isset($this->group_methods['*']['group'])) {
				$group = $this->group_methods['*']['group'];
			}

			if ($level > 0 || $group !== 'none') {
				if ($level > 0 && $group !== 'none') {
					if (!$this->validate_level($level) && !$this->validate_group($group)) {
						$this->response(['status' => 0, 'message' => 'User not authorized'], REST_Controller::HTTP_UNAUTHORIZED);
					}
				} elseif ($level > 0 && !$this->validate_level($level)) {
					$this->response(['status' => 0, 'message' => 'User level not authorized'], REST_Controller::HTTP_UNAUTHORIZED);
				} elseif ($group !== 'none' && !$this->validate_group($group)) {
					$this->response(['status' => 0, 'message' => 'User group not authorized'], REST_Controller::HTTP_UNAUTHORIZED);
				}
			}

			parent::_remap($object_called, $arguments);
		} catch (\Throwable $ex) {
			$this->_handle_dispatch_throwable($ex);
		}
	}

	/**
	 * Logs a dispatch failure, then renders it. Rendering exits.
	 *
	 * Logging happens here and not in MGR_Exceptions because the handler CI
	 * registers already logs immediately before rendering — logging there too
	 * would double every development entry. The format matches that handler's
	 * so all failure paths stay one greppable shape.
	 *
	 * @param \Throwable $ex
	 * @return void
	 */
	protected function _handle_dispatch_throwable(\Throwable $ex)
	{
		$_error = &load_class('Exceptions', 'core');
		$_error->log_exception('error', 'Exception: ' . $ex->getMessage(), $ex->getFile(), $ex->getLine());
		$_error->show_exception($ex);
	}

	/**
	 * Lazy-loads $model as $model_name and sets its time zone (plus user_id if it's an API_Model).
	 *
	 * @param string $model      Model class/file name to load.
	 * @param string $model_name Property name the loaded model is assigned to.
	 * @return void
	 */
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

	/**
	 * Adds os_kind and user_agent keys to $data, read from the request's user agent.
	 *
	 * @param array<string, mixed> &$data Payload to enrich, mutated in place.
	 * @return void
	 */
	public function add_agent_data(&$data)
	{
		$data['os_kind'] = $this->get_platform();
		$data['user_agent'] = $this->agent->agent_string();
	}

	/**
	 * Maps the request's user agent platform to a numeric code.
	 *
	 * @return int 1 = iOS, 2 = Android, 0 = anything else.
	 */
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

	/**
	 * Checks the identified caller's $logged_in_level against $level.
	 *
	 * @param int $level Minimum Ion Auth group level required.
	 * @return bool True if the caller's level meets or exceeds $level.
	 */
	public function validate_level($level)
	{
		if ($this->logged_in_level < $level) {
			return false;
		}

		return true;
	}

	/**
	 * Checks whether the identified caller belongs to $group — fails closed, no DB lookup, if unidentified.
	 *
	 * @param string|array $group Group name, or a list of group names to match any of.
	 * @param string|null  $url   When set and validation fails, redirects there instead of returning false.
	 * @return bool
	 */
	public function validate_group($group, $url = null)
	{
		if (!isset($this->user_id)) {
			return false;
		}

		if (!isset($this->rest_user_group)) {
			$this->load->model('rest_user_group');
		}

		return $this->rest_user_group->validate_group($this->user_id, $group, $url);
	}

	/**
	 * Grants access if the caller satisfies $level, $group, or both — pass
	 * null for whichever one isn't gating this check.
	 *
	 * @return bool
	 */
	public function validate_access(string|int|null $level, string|array|null $group)
	{
		if ($level !== null && $this->validate_level($level)) {
			return true;
		}

		if ($group !== null && $this->validate_group($group)) {
			return true;
		}

		return false;
	}

	/**
	 * Reads pagination/search/order query params, applying the given
	 * defaults for anything missing or malformed. `order_by` is only
	 * defaulted when empty — validating it against a column allow-list is
	 * the caller's job, via `mgr_validate_order_by()`/`mgr_build_order_by()`.
	 *
	 * @return array{page: int, limit: int, search: string, order: string, order_by: string}
	 */
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

	/**
	 * Runs the parent CORS headers, then adds Access-Control-Max-Age (from
	 * config) on preflight requests and a Vary: Origin header.
	 */
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

	/**
	 * Echoes $object as timestamped, class-tagged JSON — a quick debug trace.
	 *
	 * @return void
	 */
	public function print_log(object $object)
	{
		$now = mgr_get_now_date_time();

		$timestamp = $now->format('Y-m-d H:i:s');
		echo(PHP_EOL . $timestamp . '(' . get_called_class() . '): ' . json_encode($object));
	}
}
