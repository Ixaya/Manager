<?php

if (! defined('BASEPATH')) {
	exit('No direct script access allowed');
}
/**
 * Name:    Ion Auth
 *
 * Created:  10.01.2009
 *
 * Description:  Modified auth system based on redux_auth with extensive customization.
 *               This is basically what Redux Auth 2 should be.
 * Original Author name has been kept but that does not mean that the method has not been modified.
 * Backported from CI4 to CI3 by Ixaya
 *
 * Requirements: PHP7.2 or above
 *
 * @package    CodeIgniter-Ion-Auth
 * @author     Ben Edmunds <ben.edmunds@gmail.com>
 * @author     Phil Sturgeon
 * @author     Benoit VRIGNAUD <benoit.vrignaud@zaclys.net>
 * @author     Humberto <ixaya.com>
 * @license    https://opensource.org/licenses/MIT	MIT License
 * @link       http://github.com/benedmunds/CodeIgniter-Ion-Auth
 * @filesource
 */

/**
 * This class is the IonAuth library.
 */
class Ion_auth
{
	/**
	 * account status ('not_activated', etc ...)
	 *
	 * @var string
	 **/
	protected $status;

	/**
	 * extra where
	 *
	 * @var array
	 **/
	protected $_extra_where = [];

	/**
	 * extra set
	 *
	 * @var array
	 **/
	protected $_extra_set = [];

	/**
	 * caching of users and their groups
	 *
	 * @var array
	 **/
	protected $_cache_user_in_group;

	/**
	 * IonAuth config
	 *
	 * @var object
	 */
	protected $config_auth;

	/**
	 * Allow load session lib
	 *
	 * @var bool
	 */
	protected $allow_session = false;

	/**
	 * __construct
	 *
	 * @author Ben
	 */
	public function __construct()
	{
		$this->config_auth = (object)$this->load->config_read('ion_auth');


		$this->lang->load('ion_auth');
		$this->load->helper(['cookie', 'language', 'url']);

		// Delay load session until used only
		// $this->load->library('session');

		$this->load->model('ion_auth_model');

		$this->_cache_user_in_group = &$this->ion_auth_model->cacheUserInGroup;

		//auto-login the user if they are remembered
		if (!$this->logged_in() && get_cookie($this->config_auth->identityCookieName) && get_cookie($this->config_auth->rememberCookieName)) {
			$this->ion_auth_model->login_remembered_user();
		}

		$this->ion_auth_model->trigger_events('library_constructor');
	}

	/**
	 * __call
	 *
	 * Acts as a simple way to call model methods without loads of stupid alias'
	 *
	 * @param $method
	 * @param $arguments
	 * @return mixed
	 * @throws Exception
	 */
	public function __call($method, $arguments)
	{
		if (!method_exists($this->ion_auth_model, $method)) {
			throw new Exception('Undefined method Ion_auth::' . $method . '() called');
		}
		if ($method == 'create_user') {
			return call_user_func_array([$this, 'register'], $arguments);
		}
		if ($method == 'update_user') {
			return call_user_func_array([$this, 'update'], $arguments);
		}
		return call_user_func_array([$this->ion_auth_model, $method], $arguments);
	}

	/**
	 * __get
	 *
	 * Enables the use of CI super-global without having to define an extra variable.
	 *
	 * I can't remember where I first saw this, so thank you if you are the original author. -Militis
	 *
	 * @access	public
	 * @param	$var
	 * @return	mixed
	 */
	public function __get($var)
	{
		return get_instance()->$var;
	}


	/**
	 * Forgotten password feature
	 *
	 * @param string $identity Identity
	 *
	 * @return array|boolean
	 * @author Mathew
	 */
	public function forgotten_password(string $identity)
	{
		// Retrieve user information
		$user = $this->where($this->ion_auth_model->identityColumn, $identity)
			->where('active', 1)
			->users()->row();

		if ($user) {
			// Generate code
			$code = $this->ion_auth_model->forgotten_password($identity);

			if ($code) {
				$data = [
					'identity'              => $identity,
					'forgottenPasswordCode' => $code,
					'email'					=> $user->email,
					'subject'				=> $this->lang->line('email_forgotten_password_subject')
				];

				$this->set_message('forgot_password_successful');
				return $data;
			}
		}

		$this->set_error('forgot_password_unsuccessful');
		return false;
	}

	/**
	 * Forgotten password check
	 *
	 * @param string $code Code
	 *
	 * @return object|boolean
	 * @author Michael
	 */
	public function forgotten_password_check(string $code)
	{
		$user = $this->ion_auth_model->get_user_by_forgotten_password_code($code); //pass the code to profile

		if (!is_object($user)) {
			$this->set_error('password_change_unsuccessful');
			return false;
		} else {
			if ($this->config_auth->forgotPasswordExpiration > 0) {
				//Make sure it isn't expired
				$expiration = $this->config_auth->forgotPasswordExpiration;
				if (time() - $user->forgotten_password_time > $expiration) {
					//it has expired
					$identity = $user->{$this->config_auth->identity};
					$this->ion_auth_model->clear_forgotten_password_code($identity);
					$this->set_error('password_change_unsuccessful');
					return false;
				}
			}
			return $user;
		}
	}

	/**
	 * Register
	 *
	 * @param string $identity       Identity
	 * @param string $password       Password
	 * @param string $email          Email
	 * @param array  $additional_data Additional data
	 * @param array  $group_ids       Groups id
	 *
	 * @return integer|array|boolean The new user's ID if e-mail activation is disabled or Ion-Auth e-mail activation
	 *                               was completed;
	 *                               or an array of activation details if CI e-mail validation is enabled; or false
	 *                               if the operation failed.
	 * @author Mathew
	 */
	public function register(string $identity, string $password, string $email, array $additional_data = [], array $group_ids = [])
	{
		$this->ion_auth_model->trigger_events('pre_account_creation');

		$email_activation = $this->config_auth->emailActivation;

		$id = $this->ion_auth_model->register($identity, $password, $email, $additional_data, $group_ids);

		if (!$email_activation) {
			if ($id !== false) {
				$this->set_message('account_creation_successful');
				$this->ion_auth_model->trigger_events(['post_account_creation', 'post_account_creation_successful']);
				return $id;
			} else {
				$this->set_error('account_creation_unsuccessful');
				$this->ion_auth_model->trigger_events(['post_account_creation', 'post_account_creation_unsuccessful']);
				return false;
			}
		} else {
			if (!$id) {
				$this->set_error('account_creation_unsuccessful');
				return false;
			}

			// deactivate so the user must follow the activation flow
			$deactivate = $this->ion_auth_model->deactivate($id);

			// the deactivate method call adds a message, here we need to clear that
			$this->ion_auth_model->clear_messages();


			if (!$deactivate) {
				$this->set_error('deactivate_unsuccessful');
				$this->ion_auth_model->trigger_events(['post_account_creation', 'post_account_creation_unsuccessful']);
				return false;
			}

			$activation_code = $this->ion_auth_model->activationCode;
			$identity		= $this->config_auth->identity;
			$user			= $this->ion_auth_model->user($id)->row();

			$data = [
				'identity'   => $user->{$identity},
				'id'         => $user->id,
				'email'      => $email,
				'activation' => $activation_code,
				'subject'	 => $this->lang->line('email_activation_subject')
			];

			$this->ion_auth_model->trigger_events(['post_account_creation', 'post_account_creation_successful', 'activation_email_successful']);
			$this->set_message('activation_email_successful');
			return $data;
		}
	}

	/**
	 * Send activation email.
	 *
	 * @param string $identity
	 *
	 * @return boolean|array return an array of activation details if CI e-mail validation is enabled
	 * @author Ali Ragab
	 */
	public function send_activation_email(string $identity)
	{
		if (empty($identity)) {
			$this->set_error('empty_identity');
			return false;
		}

		if (!$this->ion_auth_model->identity_check($identity)) {
			$this->set_error("unregistered_identity");
			return false;
		}

		// Retrieve user information
		$user = $this->where($this->ion_auth_model->identityColumn, $identity)
			->limit(1)
			->users()->row();

		if ($user->active) {
			$this->set_error("already_activated_identity");
			return false;
		}

		// deactivate so the user must follow the activation flow
		$deactivate = $this->ion_auth_model->deactivate($user->id);

		// the deactivate method call adds a message, here we need to clear that
		$this->ion_auth_model->clear_messages();

		if (!$deactivate) {
			$this->set_error('deactivate_unsuccessful');
			return false;
		}

		$activationCode = $this->ion_auth_model->activationCode;
		$identity       = $this->config_auth->identity;

		$data = [
			'identity'   		  => $user->{$identity},
			'id'         		  => $user->id,
			'email'      		  => $user->email,
			'activation' 		  => $activationCode,
		];

		$this->ion_auth_model->trigger_events(['activation_email_successful']);
		$this->set_message('activation_email_successful');
		return $data;
	}

	/**
	 * Logout
	 *
	 * @return bool
	 * @author Mathew
	 */
	public function logout(): bool
	{
		if (!$this->allow_session) {
			return false;
		}
		$this->load->library('session');

		$this->ion_auth_model->trigger_events('logout');

		$identity = $this->config_auth->identity;

		$this->session->unset_userdata([$identity, 'id', 'user_id']);

		// delete the remember me cookies if they exist
		delete_cookie($this->config_auth->rememberCookieName);

		// Clear all codes
		if (isset($identity)) {
			$this->ion_auth_model->clear_forgotten_password_code($identity);
			$this->ion_auth_model->clear_remember_code($identity);
		}

		// Destroy the session
		$this->session->sess_destroy();

		//Recreate the session
		session_start();
		$this->session->sess_regenerate(true);

		$this->set_message('logout_successful');
		return true;
	}

	/**
	 * Auto logs-in the user if they are remembered
	 *
	 * @author Mathew
	 *
	 * @return boolean Whether the user is logged in
	 */
	public function logged_in(): bool
	{
		if (!$this->allow_session) {
			return false;
		}
		$this->load->library('session');

		$this->ion_auth_model->trigger_events('logged_in');

		$recheck = $this->ion_auth_model->recheck_session();

		// auto-login the user if they are remembered
		if (! $recheck && get_cookie($this->config_auth->rememberCookieName)) {
			$recheck = $this->ion_auth_model->login_remembered_user();
		}

		return $recheck;
	}

	/**
	 * Get user id
	 *
	 * @return integer|null The user's ID from the session user data or NULL if not found
	 * @author jrmadsen67
	 **/
	public function get_user_id(): ?int
	{
		if (!$this->allow_session) {
			return null;
		}
		$this->load->library('session');

		$user_id = $this->session->userdata('user_id');
		if (!empty($user_id)) {
			return $user_id;
		}
		return null;
	}
	/**
	 * client_id
	 *
	 * @return integer|null
	 * @author gumoz
	 **/
	public function get_client_id(): ?int
	{
		if (!$this->allow_session) {
			return null;
		}
		$this->load->library('session');

		$client_id = $this->session->userdata('client_id');
		if (!empty($client_id)) {
			return $client_id;
		}
		return null;
	}

	/**
	 * user
	 *
	 * @return object
	 * @author Ben Edmunds
	 **/
	public function user(?int $id = null): object
	{
		$id = $id ?? $this->get_user_id();
		if (!$id) {
			return $this->ion_auth_model;
		}
		return $this->ion_auth_model->user($id);
	}

	/**
	 * Check to see if the currently logged in user is an admin.
	 *
	 * @param integer|null $id User id
	 *
	 * @return boolean Whether the user is an administrator
	 * @author Ben Edmunds
	 */
	public function is_admin(?int $id = null): bool
	{
		$id = $id ?? $this->get_user_id();
		if (empty($id)) {
			return false;
		}

		$this->ion_auth_model->trigger_events('is_admin');

		$admin_group = $this->config_auth->adminGroup;

		return $this->ion_auth_model->in_group($admin_group, $id);
	}

	/**
	 * Updates a users row with an activation code.
	 *
	 * @param integer $id User id
	 *
	 * @return boolean
	 * @author Mathew
	 */
	public function deactivate(int $id): bool
	{
		if ($this->use_sessions && $this->logged_in() && $this->get_user_id() == $id) {
			$this->ion_auth_model->set_error('IonAuth.deactivate_current_user_unsuccessful');
			return false;
		}

		return $this->ion_auth_model->deactivate($id);
	}

	/**
	 * Check to see if a user is in a group(s)
	 *
	 * @param int|string|array $checkGroup Group(s) to check
	 * @param integer       $id         User id
	 * @param boolean       $checkAll   Check if all groups is present, or any of the groups
	 *
	 * @return boolean Whether the/all user(s) with the given ID(s) is/are in the given group
	 * @author Phil Sturgeon
	 **/
	public function in_group(int|string|array $checkGroup, ?int $id = null, bool $checkAll = false): bool
	{
		$this->ion_auth_model->trigger_events('in_group');

		$id = $id ?? $this->get_user_id();

		return $this->ion_auth_model->in_group($checkGroup, $id, $checkAll);
	}
}
