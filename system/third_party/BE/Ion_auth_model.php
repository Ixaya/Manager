<?php

if (! defined('BASEPATH')) {
	exit('No direct script access allowed');
}

/**
 * Name:    Ion Auth Model
 *
 * Created:  10.01.2009
 *
 * Description:  Modified auth system based on redux_auth with extensive customization.
 *               This is basically what Redux Auth 2 should be.
 * Original Author name has been kept but that does not mean that the method has not been modified.
 * Backported from CI4 to CI3 by Ixaya
 *
 * @package    CodeIgniter-Ion-Auth
 * @author     Ben Edmunds <ben.edmunds@gmail.com>
 * @author     Phil Sturgeon
 * @author     Benoit VRIGNAUD <benoit.vrignaud@tangue.fr>
 * @author     Humberto <ixaya.com>
 * @license    https://opensource.org/licenses/MIT	MIT License
 * @link       http://github.com/benedmunds/CodeIgniter-Ion-Auth
 * @filesource
 */

/**
 * Class IonAuthModel
 *
 * @property Ion_auth $ion_auth The Ion_auth library
 */
class BE_Ion_auth_model extends CI_Model
{
	/**
	 * Max cookie lifetime constant
	 */
	public const MAX_COOKIE_LIFETIME = 63072000; // 2 years = 60*60*24*365*2 = 63072000 seconds;

	/**
	 * Max password size constant
	 */
	public const MAX_PASSWORD_SIZE_BYTES = 4096;

	/**
	 * IonAuth config
	 *
	 * @var object
	 */
	protected $configAuth;


	/**
	 * Holds an array of tables used
	 *
	 * @var array
	 */
	public $tables = [];

	/**
	 * Activation code
	 *
	 * Set by deactivate() function
	 * Also set on register() function, if email_activation
	 * option is activated
	 *
	 * This is the value devs should give to the user
	 * (in an email, usually)
	 *
	 * It contains the *user* version of the activation code
	 * It's a value of the form "selector.validator"
	 *
	 * This is not the same activationCode as the one in DB.
	 * The DB contains a *hashed* version of the validator
	 * and a selector in another column.
	 *
	 * THe selector is not private, and only used to lookup
	 * the validator.
	 *
	 * The validator is private, and to be only known by the user
	 * So in case of DB leak, nothing could be actually used.
	 *
	 * @var string|null
	 */
	public ?string $activationCode;

	/**
	 * Identity column
	 *
	 * @var string
	 */
	public string $identityColumn;

	/**
	 * Where
	 *
	 * @var array
	 */
	protected array $ionWhere = [];

	/**
	 * Select
	 *
	 * @var array
	 */
	protected array $ionSelect = [];

	/**
	 * Like
	 *
	 * @var array
	 */
	protected array $ionLike = [];

	/**
	 * Limit
	 *
	 * @var int|null
	 */
	protected ?int $ionLimit = null;

	/**
	 * Offset
	 *
	 * @var int|null
	 */
	protected ?int $ionOffset = null;

	/**
	 * Order By
	 *
	 * @var string|null
	 */
	protected ?string $ionOrderBy = null;

	/**
	 * Order
	 *
	 * @var string|null
	 */
	protected ?string $ionOrder = null;

	/**
	 * Hooks
	 *
	 * @var object|null
	 */
	protected ?object $ionHooks;

	/**
	 * Response
	 *
	 * @var \CodeIgniter\Database\ResultInterface|null
	 */
	protected $response = null;

	/**
	 * Message (uses lang file)
	 *
	 * @var array
	 */
	protected $messages = [];

	/**
	 * Error message (uses lang file)
	 *
	 * @var array
	 */
	protected $errors = [];

	/**
	 * Messages templates (single, list).
	 *
	 * @var array
	 */
	protected $templates = [];

	/**
	 * Caching of users and their groups
	 *
	 * @var array
	 */
	public $cacheUserInGroup = [];

	/**
	 * Caching of groups
	 *
	 * @var array
	 */
	protected $cacheGroups = [];

	/**
	 * Database object
	 *
	 * @var mixed
	 */
	protected $my_db = null;

	/**
	 * Table joins
	 *
	 * @var array
	 */
	protected $join;

	/**
	 * Hash method
	 *
	 * @var string
	 */
	protected $hashMethod;

	/**
	 * Detect if we should use sessions to store user data
	 *
	 * @var bool
	 */
	protected $useSessions = false;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->configAuth = (object)$this->load->config_read('ion_auth');
		$this->load->helper(['cookie', 'date']);

		// initialize the database
		if (empty($this->configAuth->connectionName)) {
			// By default, use CI's db that should be already loaded
			$this->my_db = $this->load->database_cache();
		} else {
			// For specific group name, open a new specific connection
			$this->my_db = $this->load->database_cache($this->configAuth->connectionName);
		}

		// initialize db tables data
		$this->tables = $this->configAuth->tables;

		// initialize data
		$this->identityColumn = $this->configAuth->identity;
		$this->join           = $this->configAuth->join;

		// initialize hash method options (Bcrypt)
		$this->hashMethod = $this->configAuth->hashMethod;

		// load the messages template from the config file
		$this->templates = $this->configAuth->templates;

		// initialize our hooks object
		$this->ionHooks = new \stdClass();

		$this->useSessions = (isset($this->sessions));

		$this->trigger_events('model_constructor');
	}

	/**
	 * Getter to the DB connection used by Ion Auth
	 * May prove useful for debugging
	 *
	 * @return object
	 */
	public function db()
	{
		return $this->my_db;
	}

	/**
	 * Hashes the password to be stored in the database.
	 *
	 * @param string $password Password
	 * @param string $identity Identity
	 *
	 * @return false|string
	 * @author Mathew
	 */
	public function hash_password(string $password, string $identity = '')
	{
		// Check for empty password, or password containing null char, or password above limit
		// Null char may pose issue: http://php.net/manual/en/function.password-hash.php#118603
		// Long password may pose DOS issue (note: strlen gives size in bytes and not in multibyte symbol)
		if (
			empty($password) || strpos($password, "\0") !== false ||
			strlen($password) > self::MAX_PASSWORD_SIZE_BYTES
		) {
			return false;
		}

		$algo   = $this->get_hash_algo();
		$params = $this->get_hash_parameters($identity);

		if ($algo !== false && $params !== false) {
			$hash = password_hash($password, $algo, $params);

			if (empty($hash)) {
				return false;
			}

			return $hash;
		}

		return false;
	}

	/**
	 * This function takes a password and validates it
	 * against an entry in the users table.
	 *
	 * @param string $password       Password
	 * @param string $hashPasswordDb
	 * @param string $identity		 Optional @deprecated only for BC SHA1
	 *
	 * @return boolean
	 * @author Mathew
	 */
	public function verify_password(string $password, string $hashPasswordDb, string $identity = ''): bool
	{
		// Check for empty id or password, or password containing null char, or password above limit
		// Null char may pose issue: http://php.net/manual/en/function.password-hash.php#118603
		// Long password may pose DOS issue (note: strlen gives size in bytes and not in multibyte symbol)
		if (
			empty($password) || empty($hashPasswordDb) || strpos($password, "\0") !== false
			|| strlen($password) > self::MAX_PASSWORD_SIZE_BYTES
		) {
			return false;
		}

		return password_verify($password, $hashPasswordDb);
	}

	/**
	 * Check if password needs to be rehashed
	 * If true, then rehash and update it in DB
	 *
	 * @param string $hash     Hash
	 * @param string $identity Identity
	 * @param string $password Password
	 *
	 * @return void
	 */
	public function rehash_password_if_needed(string $hash, string $identity, string $password): void
	{
		$algo   = $this->get_hash_algo();
		$params = $this->get_hash_parameters($identity);

		if ($algo !== false && $params !== false) {
			if (password_needs_rehash($hash, $algo, $params)) {
				if ($this->set_password_db($identity, $password)) {
					$this->trigger_events(['rehash_password', 'rehash_password_successful']);
				} else {
					$this->trigger_events(['rehash_password', 'rehash_password_unsuccessful']);
				}
			}
		}
	}

	/**
	 * Get a user by its activation code
	 *
	 * @param string $userCode The activation code
	 *                         It's the *user* one, containing "selector.validator"
	 *                         the one you got in activation_code member
	 *
	 * @return null|object
	 * @author Indigo
	 */
	public function get_user_by_activation_code(string $userCode): ?object
	{
		// Retrieve the token object from the code
		$token = $this->retrieve_selector_validator_couple($userCode);

		if ($token == false) {
			return null;
		}

		// Retrieve the user according to this selector
		$user = $this->where('activation_selector', $token->selector)->users()->row();

		if ($user) {
			// Check the hash against the validator
			if ($this->verify_password($token->validator, $user->activation_code)) {
				return $user;
			}
		}

		return null;
	}

	/**
	 * Validates and removes activation code.
	 *
	 * @param integer|string $id   The user identifier
	 * @param string|null    $code The *user* activation code
	 *                             if omitted, simply activate the user without check
	 *
	 * @return boolean
	 * @author Mathew
	 */
	public function activate($id, ?string $code = null): bool
	{
		$this->trigger_events('pre_activate');

		$valid_request = true;
		if ($code) {
			$user = $this->get_user_by_activation_code($code);

			// If no user found or ID mismatch, fail early
			if (!$user || $user->id != $id) {
				$valid_request = false;
			}
		}

		// Activate if no code is given
		// Or if a user was found with this code, and that it matches the id
		if ($valid_request) {
			$data = [
				'activation_selector' => null,
				'activation_code'     => null,
				'active'              => 1,
			];

			$this->trigger_events('extra_where');
			$this->my_db->update($this->tables['users'], $data, ['id' => $id]);

			if ($this->my_db->affected_rows() === 1) {
				$this->trigger_events(['post_activate', 'post_activate_successful']);
				$this->set_message('IonAuth.activate_successful');
				return true;
			}
		}

		$this->trigger_events(['post_activate', 'post_activate_unsuccessful']);
		$this->set_error('IonAuth.activate_unsuccessful');
		return false;
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
		$this->trigger_events('deactivate');

		$token                = $this->generate_selector_validator_couple(20, 40);
		$this->activationCode  = $token->userCode;

		$data = [
			'activation_selector' => $token->selector,
			'activation_code'     => $token->validatorHashed,
			'active'              => 0,
		];

		$this->trigger_events('extra_where');
		$this->my_db->update($this->tables['users'], $data, ['id' => $id]);

		$return = $this->my_db->affected_rows() === 1;
		if ($return) {
			$this->set_message('IonAuth.deactivate_successful');
		} else {
			$this->set_error('IonAuth.deactivate_unsuccessful');
		}

		return $return;
	}

	/**
	 * Clear the forgotten password code for a user
	 *
	 * @param string $identity Identity
	 *
	 * @return boolean Success
	 */
	public function clear_forgotten_password_code(string $identity): bool
	{
		if (empty($identity)) {
			return false;
		}

		$data = [
			'forgotten_password_selector' => null,
			'forgotten_password_code'     => null,
			'forgotten_password_time'     => null,
		];

		return $this->my_db->where($this->identityColumn, $identity)->update($this->tables['users'], $data);
	}

	/**
	 * Clear the remember code for a user
	 *
	 * @param string $identity Identity
	 *
	 * @return boolean Success
	 */
	public function clear_remember_code(string $identity): bool
	{
		if (empty($identity)) {
			return false;
		}

		$data = [
			'remember_selector' => null,
			'remember_code'     => null,
		];

		return $this->my_db->where($this->identityColumn, $identity)->update($this->tables['users'], $data);
	}

	/**
	 * Reset password
	 *
	 * @param string $identity Identity
	 * @param string $new      New password
	 *
	 * @return boolean
	 * @author Mathew
	 */
	public function reset_password(string $identity, string $new)
	{
		$this->trigger_events('pre_change_password');

		if (! $this->identity_check($identity)) {
			$this->trigger_events(['post_change_password', 'post_change_password_unsuccessful']);
			return false;
		}

		$return = $this->set_password_db($identity, $new);

		if ($return) {
			$this->trigger_events(['post_change_password', 'post_change_password_successful']);
			$this->set_message('IonAuth.password_change_successful');
		} else {
			$this->trigger_events(['post_change_password', 'post_change_password_unsuccessful']);
			$this->set_error('IonAuth.password_change_unsuccessful');
		}

		return $return;
	}

	/**
	 * Change password
	 *
	 * @param string $identity Identity
	 * @param string $old      Old password
	 * @param string $new      New password
	 *
	 * @return boolean
	 * @author Mathew
	 */
	public function change_password(string $identity, string $old, string $new): bool
	{
		$this->trigger_events('pre_change_password');

		$this->trigger_events('extra_where');

		$query = $this->my_db->select('id, password')
			->where($this->identityColumn, $identity)
			->limit(1)
			->get($this->tables['users']);

		$user = $query->row();

		if (empty($user)) {
			$this->trigger_events(['post_change_password', 'post_change_password_unsuccessful']);
			$this->set_error('IonAuth.password_change_unsuccessful');
			return false;
		}

		if ($this->verify_password($old, $user->password, $identity)) {
			$result = $this->set_password_db($identity, $new);

			if ($result) {
				$this->trigger_events(['post_change_password', 'post_change_password_successful']);
				$this->set_message('IonAuth.password_change_successful');
			} else {
				$this->trigger_events(['post_change_password', 'post_change_password_unsuccessful']);
				$this->set_error('IonAuth.password_change_unsuccessful');
			}

			return $result;
		}

		$this->set_error('IonAuth.password_change_unsuccessful');
		return false;
	}

	/**
	 * Checks username
	 *
	 * @param string $username User name
	 *
	 * @return boolean
	 * @author Mathew
	 */
	public function username_check(string $username): bool
	{
		$this->trigger_events('username_check');

		if (empty($username)) {
			return false;
		}

		$this->trigger_events('extra_where');

		return $this->my_db->where('username', $username)
			->limit(1)
			->count_all_results($this->tables['users']) > 0;
	}

	/**
	 * Checks email to see if the email is already registered.
	 *
	 * @param string $email Email to check
	 *
	 * @return boolean true if the user is registered false if the user is not registered.
	 * @author Mathew
	 */
	public function email_check(string $email = ''): bool
	{
		$this->trigger_events('emailCheck');

		if (empty($email)) {
			return false;
		}

		$this->trigger_events('extra_where');

		return $this->my_db->where('email', $email)
			->limit(1)
			->count_all_results($this->tables['users']) > 0;
	}

	/**
	 * Identity check : Check to see if the identity is already registered
	 *
	 * @param string $identity Identity
	 *
	 * @return boolean
	 * @author Mathew
	 */
	public function identity_check(string $identity = ''): bool
	{
		$this->trigger_events('identity_check');

		if (empty($identity)) {
			return false;
		}

		return $this->my_db->where($this->identityColumn, $identity)
			->limit(1)
			->count_all_results($this->tables['users']) > 0;
	}

	/**
	 * Get user ID from identity
	 *
	 * @param string $identity Identity
	 *
	 * @return boolean|integer
	 */
	public function get_user_id_from_identity(string $identity = '')
	{
		if (empty($identity)) {
			return false;
		}

		$query = $this->my_db->select('id')
			->where($this->identityColumn, $identity)
			->limit(1)
			->get($this->tables['users']);

		$user = $query->row();

		if ($user) {
			return $user->id;
		}

		return false;
	}

	/**
	 * Insert a forgotten password key.
	 *
	 * @param string $identity As defined in Config/IonAuth
	 *
	 * @return boolean|string
	 *
	 * @author Mathew
	 * @author Ryan
	 */
	public function forgotten_password(string $identity)
	{
		if (empty($identity)) {
			$this->trigger_events(['post_forgotten_password', 'post_forgotten_password_unsuccessful']);
			return false;
		}

		// Generate random token: smaller size because it will be in the URL
		$token = $this->generate_selector_validator_couple(20, 80);

		$update = [
			'forgotten_password_selector' => $token->selector,
			'forgotten_password_code'     => $token->validatorHashed,
			'forgotten_password_time'     => time(),
		];

		$this->trigger_events('extra_where');
		$this->my_db->update($this->tables['users'], $update, [$this->identityColumn => $identity]);

		if ($this->my_db->affected_rows() === 1) {
			$this->trigger_events(['post_forgotten_password', 'post_forgotten_password_successful']);
			return $token->userCode;
		} else {
			$this->trigger_events(['post_forgotten_password', 'post_forgotten_password_unsuccessful']);
			return false;
		}
	}

	/**
	 * Get a user from a forgotten password key.
	 *
	 * @param string $userCode Forgotten password key
	 *
	 * @return  boolean|object
	 * @author  Mathew
	 * @updated Ryan
	 */
	public function get_user_by_forgotten_password_code(string $userCode)
	{
		// Retrieve the token object from the code
		$token = $this->retrieve_selector_validator_couple($userCode);

		if ($token == false) {
			return false;
		}

		// Retrieve the user according to this selector
		$user = $this->where('forgotten_password_selector', $token->selector)->users()->row();

		if ($user) {
			// Check the hash against the validator
			if (isset($user->forgotten_password_code) && $this->verify_password($token->validator, $user->forgotten_password_code)) {
				return $user;
			}
		}

		return false;
	}

	/**
	 * Register (create) a new user
	 *
	 * @param string $identity       This must be the value that uniquely identifies the user when he is registered
	 * @param string $password       Password
	 * @param string $email          Email
	 * @param array  $additionalData Multidimensional array
	 * @param array  $groups         If not passed the default group name set in the config will be used
	 *
	 * @return integer|boolean
	 * @author Mathew
	 */
	public function register(string $identity, string $password, string $email, array $additionalData = [], array $groups = [])
	{
		$this->trigger_events('pre_register');

		$manualActivation = $this->configAuth->manualActivation;

		if ($this->identity_check($identity)) {
			$this->set_error('IonAuth.account_creation_duplicate_identity');
			return false;
		} elseif (! $this->configAuth->defaultGroup && empty($groups)) {
			$this->set_error('IonAuth.account_creation_missing_defaultGroup');
			return false;
		}

		// check if the default set in config exists in database
		$query = $this->my_db->where(['name' => $this->configAuth->defaultGroup], 1)->get($this->tables['groups'])->row();
		if (! isset($query->id) && empty($groups)) {
			$this->set_error('IonAuth.account_creation_invalid_defaultGroup');
			return false;
		}

		// capture default group details
		$defaultGroup = $query;

		// IP Address
		$ipAddress = $this->input->ip_address();

		// Do not pass $identity as user is not known yet so there is no need
		$password = $this->hash_password($password);

		if ($password === false) {
			$this->set_error('IonAuth.account_creation_unsuccessful');
			return false;
		}

		// Users table.
		$data = [
			$this->identityColumn => $identity,
			'username'            => $identity,
			'password'            => $password,
			'email'               => $email,
			'ip_address'          => $ipAddress,
			'created_on'          => time(),
			'active'              => ($manualActivation === false ? 1 : 0),
		];

		// filter out any data passed that doesnt have a matching column in the users table
		// and merge the set user data and the additional data
		$userData = array_merge($this->filter_data($this->tables['users'], $additionalData), $data);

		$this->trigger_events('extra_set');

		$this->my_db->insert($this->tables['users'], $userData);

		$id = $this->my_db->insert_id();

		// add in groups array if it doesn't exists and stop adding into default group if default group ids are set
		if (isset($defaultGroup->id) && empty($groups)) {
			$groups[] = $defaultGroup->id;
		}

		if (! empty($groups)) {
			// add to groups
			foreach ($groups as $group) {
				$this->add_to_group($group, $id);
			}
		}

		$this->trigger_events('post_register');

		return $id ?? false;
	}

	/**
	 * Logs the user into the system
	 *
	 * @param string  $identity Username, email or any unique value in your users table, depending on your configuration
	 * @param string  $password Password
	 * @param boolean $remember Sets the user to be remembered if enabled in the configuration
	 *
	 * @return boolean
	 * @author Mathew
	 */
	public function login(string $identity, string $password, bool $remember = false): object|bool
	{
		$this->trigger_events('pre_login');

		if (empty($identity) || empty($password)) {
			$this->set_error('IonAuth.login_unsuccessful');
			return false;
		}

		$extraColumns = '';
		if (!empty($this->configAuth->identityExtraColumns)) {
			$extraColumns = ', ';
			$extraColumns .= implode(', ', $this->configAuth->identityExtraColumns);
		}

		$this->trigger_events('extra_where');
		$query = $this->my_db->select($this->identityColumn . ', email, id, password, active, last_login' . $extraColumns)
			->where($this->identityColumn, $identity)
			->limit(1)
			->order_by('id', 'desc')
			->get($this->tables['users']);

		if ($this->is_max_login_attempts_exceeded($identity)) {
			// Hash something anyway, just to take up time
			// $this->hash_password($password);

			$this->trigger_events('post_login_unsuccessful');
			$this->set_error('IonAuth.login_timeout');

			return false;
		}

		$user = $query->row();

		if (isset($user)) {
			if ($this->verify_password($password, $user->password, $identity)) {
				if ($user->active == 0) {
					$this->trigger_events('post_login_unsuccessful');
					$this->set_error('IonAuth.login_unsuccessful_not_active');

					return false;
				}

				$this->update_last_login($user->id);

				$this->clear_login_attempts($identity);
				$this->clear_forgotten_password_code($identity);

				// Rehash if needed
				$this->rehash_password_if_needed($user->password, $identity, $password);

				if ($this->useSessions) {
					$this->set_session($user);

					if ($this->configAuth->rememberUsers) {
						if ($remember) {
							$this->remember_user($identity);
						} else {
							$this->clear_remember_code($identity);
						}
					}

					// Regenerate the session (for security purpose: to avoid session fixation)
					$this->session->regenerate(false);
				}

				$this->trigger_events(['post_login', 'post_login_successful']);
				$this->set_message('IonAuth.login_successful');

				if (isset($user->password)) {
					unset($user->password);
				}
				return $this->useSessions ? true : $user;
			}
		}

		// Hash something anyway, just to take up time
		$this->hash_password($password);

		$this->increase_login_attempts($identity);

		$this->trigger_events('post_login_unsuccessful');
		$this->set_error('IonAuth.login_unsuccessful');

		return false;
	}

	/**
	 * Verifies if the session should be rechecked according to the configuration item recheckTimer. If it does, then
	 * it will check if the user is still active
	 *
	 * @return boolean
	 */
	public function recheck_session(): bool
	{
		if (!$this->useSessions) {
			return false;
		}

		$recheck = (null !== $this->configAuth->recheckTimer) ? $this->configAuth->recheckTimer : 0;

		if ($recheck !== 0) {
			$lastLogin = $this->session->userdata('last_check');
			if ($lastLogin + $recheck < time()) {
				$user = $this->my_db
					->select('id')
					->where([
						$this->identityColumn => $this->session->userdata('identity'),
						'active'              => '1',
					])
					->limit(1)
					->order_by('id', 'desc')
					->get($this->tables['users'])
					->row();

				if (!empty($user)) {
					$this->session->set_userdata('last_check', time());
				} else {
					$this->trigger_events('logout');

					$identity = $this->configAuth->identity;

					$this->session->unset_userdata([$identity, 'id', 'user_id']);

					return false;
				}
			}
		}

		return (bool)$this->session->userdata('identity');
	}

	/**
	 * Check if max login attempts exceeded
	 * Based on code from Tank Auth, by Ilya Konyukhov (https://github.com/ilkon/Tank-Auth)
	 *
	 * @param string      $identity  User's identity
	 * @param string|null $ipAddress IP address
	 *                               Only used if trackLoginIpAddress is set to true.
	 *                               If null (default value), the current IP address is used.
	 *                               Use getLastAttemptIp($identity) to retrieve a user's last IP
	 *
	 * @return boolean
	 */
	public function is_max_login_attempts_exceeded(string $identity, $ipAddress = null): bool
	{
		if ($this->configAuth->trackLoginAttempts) {
			$maxAttempts = $this->configAuth->maximumLoginAttempts;
			if ($maxAttempts > 0) {
				$attempts = $this->get_attempts_num($identity, $ipAddress);
				return $attempts >= $maxAttempts;
			}
		}
		return false;
	}

	/**
	 * Get number of login attempts for the given IP-address or identity
	 * Based on code from Tank Auth, by Ilya Konyukhov (https://github.com/ilkon/Tank-Auth)
	 *
	 * @param string      $identity  User's identity
	 * @param string|null $ipAddress IP address
	 *                               Only used if trackLoginIpAddress is set to true.
	 *                               If null (default value), the current IP address is used.
	 *                               Use getLastAttemptIp($identity) to retrieve a user's last IP
	 *
	 * @return integer
	 */
	public function get_attempts_num(string $identity, $ipAddress = null): int
	{
		if ($this->configAuth->trackLoginAttempts) {
			$builder = $this->my_db->where('login', $identity);
			if ($this->configAuth->trackLoginIpAddress) {
				if (! isset($ipAddress)) {
					$ipAddress = $this->input->ip_address();
				}
				$this->my_db->where('ip_address', $ipAddress);
			}
			$this->my_db->where('time >', time() - $this->configAuth->lockoutTime, false);
			return $this->my_db->count_all_results($this->tables['login_attempts']);
		}
		return 0;
	}

	/**
	 * Get the last time a login attempt occurred from given identity
	 *
	 * @param string      $identity  User's identity
	 * @param string|null $ipAddress IP address
	 *                               Only used if trackLoginIpAddress is set to true.
	 *                               If null (default value), the current IP address is used.
	 *                               Use getLastAttemptIp($identity) to retrieve a user's last IP
	 *
	 * @return integer The time of the last login attempt for a given IP-address or identity
	 */
	public function get_last_attempt_time(string $identity, $ipAddress = null): int
	{
		if ($this->configAuth->trackLoginAttempts) {
			$builder = $this->my_db->select('time');
			$this->my_db->where('login', $identity);
			if ($this->configAuth->trackLoginIpAddress) {
				if (! isset($ipAddress)) {
					$ipAddress = $this->input->ip_address();
				}
				$this->my_db->where('ip_address', $ipAddress);
			}
			$this->my_db->order_by('id', 'desc');
			$this->my_db->limit(1);
			$query = $this->my_db->get($this->tables['login_attempts']);

			if ($query->row()) {
				return $query->row()->time;
			}
		}

		return 0;
	}

	/**
	 * Get the IP address of the last time a login attempt occurred from given identity
	 *
	 * @param string $identity User's identity
	 *
	 * @return string
	 */
	public function get_last_attempt_ip(string $identity)
	{
		if ($this->configAuth->trackLoginAttempts && $this->configAuth->trackLoginIpAddress) {
			$builder = $this->my_db->select('ip_address');
			$this->my_db->where('login', $identity);
			$this->my_db->order_by('id', 'desc');
			$this->my_db->limit(1);
			$qres = $this->my_db->get($this->tables['login_attempts']);

			if ($qres->num_rows() > 0) {
				return $qres->row()->ip_address;
			}
		}

		return '';
	}

	/**
	 * Based on code from Tank Auth, by Ilya Konyukhov (https://github.com/ilkon/Tank-Auth)
	 *
	 * Note: the current IP address will be used if trackLoginIpAddress config value is true
	 *
	 * @param string $identity User's identity
	 *
	 * @return boolean
	 */
	public function increase_login_attempts(string $identity): bool
	{
		if ($this->configAuth->trackLoginAttempts) {
			$data = ['ip_address' => '', 'login' => $identity, 'time' => time()];
			if ($this->configAuth->trackLoginIpAddress) {
				$data['ip_address'] = $this->input->ip_address();
			}

			$this->my_db->insert($this->tables['login_attempts'], $data);
			return true;
		}
		return false;
	}

	/**
	 * Clear login attempts
	 * Based on code from Tank Auth, by Ilya Konyukhov (https://github.com/ilkon/Tank-Auth)
	 *
	 * @param string      $identity                User's identity
	 * @param integer     $oldAttemptsAxpirePeriod In seconds, any attempts older than this value will be removed.
	 *                                                It is used for regularly purging the attempts table.
	 *                                                (for security reason, minimum value is lockoutTime config value)
	 * @param string|null $ipAddress               IP address
	 *                                                Only used if track_login_ipAddress is set to true.
	 *                                                If null (default value), the current IP address is used.
	 *                                                Use getLastAttemptIp($identity) to retrieve a user's last IP
	 *
	 * @return boolean
	 */
	public function clear_login_attempts(string $identity, int $oldAttemptsAxpirePeriod = 86400, $ipAddress = null): bool
	{
		if ($this->configAuth->trackLoginAttempts) {
			// Make sure $oldAttemptsAxpirePeriod is at least equals to lockoutTime
			$oldAttemptsAxpirePeriod = max($oldAttemptsAxpirePeriod, $this->configAuth->lockoutTime);

			$builder = $this->my_db->where('login', $identity);
			if ($this->configAuth->trackLoginIpAddress) {
				if (! isset($ipAddress)) {
					$ipAddress = $this->input->ip_address();
				}
				$this->my_db->where('ip_address', $ipAddress);
			}
			// Purge obsolete login attempts
			$this->my_db->or_where('time <', time() - $oldAttemptsAxpirePeriod, false);

			return $this->my_db->delete($this->tables['login_attempts']) === false ? false : true;
		}
		return false;
	}

	/**
	 * Limit
	 *
	 * @param integer $limit Limit
	 *
	 * @return self
	 */
	public function limit(int $limit): self
	{
		$this->trigger_events('limit');
		$this->ionLimit = $limit;

		return $this;
	}

	/**
	 * Offset
	 *
	 * @param integer $offset Offset
	 *
	 * @return self
	 */
	public function offset(int $offset): self
	{
		$this->trigger_events('offset');
		$this->ionOffset = $offset;

		return $this;
	}

	/**
	 * @param array|string $where
	 * @param null|string|int  $value
	 *
	 * @return self
	 */
	public function where($where, $value = null): self
	{
		$this->trigger_events('where');

		if (! is_array($where)) {
			$where = [$where => $value];
		}

		array_push($this->ionWhere, $where);

		return $this;
	}

	/**
	 * Like
	 *
	 * @param string      $like
	 * @param string|null $value
	 * @param string      $position
	 *
	 * @return self
	 */
	public function like(string $like, $value = null, $position = 'both'): self
	{
		$this->trigger_events('like');

		array_push($this->ionLike, [
			'like'     => $like,
			'value'    => $value,
			'position' => $position,
		]);

		return $this;
	}

	/**
	 * Select
	 *
	 * @param array|string $select Select
	 *
	 * @return self
	 */
	public function select($select): self
	{
		$this->trigger_events('select');

		$this->ionSelect[] = $select;

		return $this;
	}

	/**
	 * Order by
	 *
	 * @param string $by    By
	 * @param string $order Order
	 *
	 * @return self
	 */
	public function order_by(string $by, string $order = 'desc'): self
	{
		$this->trigger_events('order_by');

		$this->ionOrderBy = $by;
		$this->ionOrder   = $order;

		return $this;
	}

	/**
	 * Wrapper object to return a row as either an array, an object, or
	 * a custom class.
	 *
	 * If row doesn't exist, returns null.
	 *
	 * @return mixed
	 */
	public function row()
	{
		$this->trigger_events('row');

		if (empty($this->response)) {
			return null;
		}

		$row = $this->response->row();

		return $row;
	}

	/**
	 * Returns a single row from the results as an array.
	 *
	 * If row doesn't exist, returns null.
	 *
	 * @return mixed
	 */
	public function row_array()
	{
		$this->trigger_events(['row', 'row_array']);

		$row = $this->response->row_array();

		return $row;
	}

	/**
	 * Get result
	 *
	 * @return array
	 */
	public function result(): array
	{
		$this->trigger_events('result');

		return $this->response->result();
	}

	/**
	 * Get result array
	 *
	 * @return array
	 */
	public function result_array(): array
	{
		$this->trigger_events(['result', 'result_array']);

		$result = $this->response->result_array();

		return $result;
	}

	/**
	 * Num rows
	 *
	 * @return integer
	 */
	public function num_rows(): int
	{
		$this->trigger_events(['num_rows']);

		$result = $this->response->num_rows();

		return $result;
	}

	/**
	 * Get the users
	 *
	 * @param array|string|integer $groups Group IDs, group names, or group IDs and names
	 *
	 * @return self
	 * @author Ben Edmunds
	 */
	public function users($groups = null): self
	{
		$this->trigger_events('users');

		$this->my_db->reset_query();

		if (! empty($this->ionSelect)) {
			foreach ($this->ionSelect as $select) {
				$this->my_db->select($select);
			}

			$this->ionSelect = [];
		} else {
			// default selects
			$this->my_db->select([
				$this->tables['users'] . '.*',
				$this->tables['users'] . '.id as id',
				$this->tables['users'] . '.id as user_id',
			]);
		}

		// filter by group id(s) if passed
		if (isset($groups)) {
			// build an array if only one group was passed
			if (! is_array($groups)) {
				$groups = [$groups];
			}

			// join and then run a where_in against the group ids
			if (! empty($groups)) {
				$this->my_db->distinct();
				$this->my_db->join(
					$this->tables['users_groups'],
					$this->tables['users_groups'] . '.' . $this->join['users'] . '=' . $this->tables['users'] . '.id',
					'inner'
				);
			}

			// verify if group name or group id was used and create and put elements in different arrays
			$groupIds   = [];
			$groupNames = [];
			foreach ($groups as $group) {
				if (is_numeric($group)) {
					$groupIds[] = $group;
				} else {
					$groupNames[] = $group;
				}
			}
			$orWhereIn = (! empty($groupIds) && ! empty($groupNames)) ? 'or_where_in' : 'where_in';
			// if group name was used we do one more join with groups
			if (! empty($groupNames)) {
				$this->my_db->join($this->tables['groups'], $this->tables['users_groups'] . '.' . $this->join['groups'] . ' = ' . $this->tables['groups'] . '.id', 'inner');
				$this->my_db->where_in($this->tables['groups'] . '.name', $groupNames);
			}
			if (! empty($groupIds)) {
				$this->my_db->{$orWhereIn}($this->tables['users_groups'] . '.' . $this->join['groups'], $groupIds);
			}
		}

		$this->trigger_events('extra_where');

		// run each where that was passed
		if (!empty($this->ionWhere)) {
			foreach ($this->ionWhere as $where) {
				$this->my_db->where($where);
			}
			$this->ionWhere = [];
		}

		if (! empty($this->ionLike)) {
			foreach ($this->ionLike as $like) {
				$this->my_db->or_like($like['like'], $like['value'], $like['position']);
			}

			$this->ionLike = [];
		}

		if (isset($this->ionLimit) && isset($this->ionOffset)) {
			$this->my_db->limit($this->ionLimit, $this->ionOffset);

			$this->ionLimit  = null;
			$this->ionOffset = null;
		} elseif (isset($this->ionLimit)) {
			$this->my_db->limit($this->ionLimit);

			$this->ionLimit = null;
		}

		// set the order
		if (isset($this->ionOrderBy) && isset($this->ionOrder)) {
			$this->my_db->order_by($this->ionOrderBy, $this->ionOrder);

			$this->ionOrder   = null;
			$this->ionOrderBy = null;
		}

		$this->response = $this->my_db->get($this->tables['users']);

		return $this;
	}

	/**
	 * Get a user
	 *
	 * @param integer $id If a user id is not passed the id of the currently logged in user will be used
	 *
	 * @return self
	 * @author Ben Edmunds
	 */
	public function user(int $id): self
	{
		$this->trigger_events('user');

		$this->limit(1);
		$this->order_by($this->tables['users'] . '.id', 'desc');
		$this->where($this->tables['users'] . '.id', $id);

		$this->users();

		return $this;
	}

	/**
	 * Get all groups a user is part of
	 *
	 * @param integer $id If a user id is not passed the id of the currently logged in user will be used
	 *
	 * @return object
	 * @author Ben Edmunds
	 */
	public function get_users_groups(int $id)
	{
		$this->trigger_events('get_users_group');

		return $this->my_db->select($this->tables['users_groups'] . '.' . $this->join['groups'] . ' as id, ' . $this->tables['groups'] . '.name, ' . $this->tables['groups'] . '.level, ' . $this->tables['groups'] . '.description')
			->where($this->tables['users_groups'] . '.' . $this->join['users'], $id)
			->join($this->tables['groups'], $this->tables['users_groups'] . '.' . $this->join['groups'] . '=' . $this->tables['groups'] . '.id')
			->get($this->tables['users_groups']);
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
	public function in_group(int|string|array $checkGroup, int $id, bool $checkAll = false): bool
	{
		$this->trigger_events('in_group');

		if (! is_array($checkGroup)) {
			$checkGroup = [$checkGroup];
		}

		if (isset($this->cacheUserInGroup[$id])) {
			$groupsArray = $this->cacheUserInGroup[$id];
		} else {
			$usersGroups = $this->get_users_groups($id)->result();
			$groupsArray = [];
			foreach ($usersGroups as $group) {
				$groupsArray[$group->id] = $group->name;
			}
			$this->cacheUserInGroup[$id] = $groupsArray;
		}
		foreach ($checkGroup as $key => $value) {
			$groups = (is_numeric($value)) ? array_keys($groupsArray) : $groupsArray;

			/**
			 * if !all (default), in_array
			 * if all, !in_array
			 */
			if (in_array($value, $groups) xor $checkAll) {
				/**
				 * if !all (default), true
				 * if all, false
				 */
				return ! $checkAll;
			}
		}

		/**
		 * if !all (default), false
		 * if all, true
		 */
		return $checkAll;
	}

	/**
	 * Add to group
	 *
	 * @param array|integer $groupIds Groups id
	 * @param integer       $userId   User id
	 *
	 * @return integer The number of groups added
	 * @author Ben Edmunds
	 */
	public function add_to_group(array|int $groupIds, int $userId): int
	{
		$this->trigger_events('add_to_group');

		if (! is_array($groupIds)) {
			$groupIds = [$groupIds];
		}

		$return = 0;

		// Then insert each into the database
		foreach ($groupIds as $groupId) {
			// Cast to float to support bigint data type
			if ($this->my_db->insert($this->tables['users_groups'], [
				$this->join['groups'] => (float)$groupId,
				$this->join['users']  => (float)$userId
			])) {
				if (isset($this->cacheGroups[$groupId])) {
					$groupName = $this->cacheGroups[$groupId];
				} else {
					$group                       = $this->group($groupId)->result();
					$groupName                   = $group[0]->name;
					$this->cacheGroups[$groupId] = $groupName;
				}
				$this->cacheUserInGroup[$userId][$groupId] = $groupName;

				// Return the number of groups added
				$return++;
			}
		}

		return $return;
	}

	/**
	 * Remove from group
	 *
	 * @param array|integer|null $groupIds Group id
	 * @param integer       $userId   User id
	 *
	 * @return boolean
	 * @author Ben Edmunds
	 */
	public function remove_from_group($groupIds = 0, int $userId = 0): bool
	{
		$this->trigger_events('remove_from_group');

		// user id is required
		if (! $userId) {
			return false;
		}

		// if group id(s) are passed remove user from the group(s)
		if (! empty($groupIds)) {
			if (! is_array($groupIds)) {
				$groupIds = [$groupIds];
			}

			foreach ($groupIds as $groupId) {
				$this->my_db->where([$this->join['groups'] => (int)$groupId, $this->join['users'] => $userId]);
				$this->my_db->delete($this->tables['users_groups']);
				if (isset($this->cacheUserInGroup[$userId]) && isset($this->cacheUserInGroup[$userId][$groupId])) {
					unset($this->cacheUserInGroup[$userId][$groupId]);
				}
			}

			$return = true;
		}
		// otherwise remove user from all groups
		else {
			if ($return = $this->my_db->delete($this->tables['users_groups'], [$this->join['users'] => $userId])) {
				$this->cacheUserInGroup[$userId] = [];
				$return = true;
			}
		}
		return $return;
	}

	/**
	 * Get the groups
	 *
	 * @return self
	 * @author Ben Edmunds
	 */
	public function groups(): self
	{
		$this->trigger_events('groups');

		// run each where that was passed
		if (!empty($this->ionWhere)) {
			foreach ($this->ionWhere as $where) {
				$this->my_db->where($where);
			}
			$this->ionWhere = [];
		}

		if (isset($this->ionLimit) && isset($this->ionOffset)) {
			$this->my_db->limit($this->ionLimit, $this->ionOffset);

			$this->ionLimit  = null;
			$this->ionOffset = null;
		} elseif (isset($this->ionLimit)) {
			$this->my_db->limit($this->ionLimit);

			$this->ionLimit = null;
		}

		// set the order
		if (isset($this->ionOrderBy) && isset($this->ionOrder)) {
			$this->my_db->order_by($this->ionOrderBy, $this->ionOrder);
		}

		$this->response = $this->my_db->get($this->tables['groups']);

		return $this;
	}

	/**
	 * Get a group
	 *
	 * @param integer $id Group id
	 *
	 * @return self
	 * @author Ben Edmunds
	 */
	public function group(int $id = 0)
	{
		$this->trigger_events('group');

		if ($id) {
			$this->where($this->tables['groups'] . '.id', $id);
		}

		$this->limit(1);
		$this->order_by('id', 'desc');

		return $this->groups();
	}

	/**
	 * Update a user
	 *
	 * @param integer $id   User id
	 * @param array   $data Multidimensional array
	 *
	 * @return boolean
	 * @author Phil Sturgeon
	 */
	public function update(int $id, array $data): bool
	{
		$this->trigger_events('pre_update_user');

		$user = $this->user($id)->row();

		$this->my_db->trans_begin();

		if (array_key_exists($this->identityColumn, $data) && $this->identity_check($data[$this->identityColumn]) && $user->{$this->identityColumn} !== $data[$this->identityColumn]) {
			$this->my_db->trans_rollback();
			$this->set_error('IonAuth.account_creation_duplicate_identity');

			$this->trigger_events(['post_update_user', 'post_update_user_unsuccessful']);
			$this->set_error('IonAuth.update_unsuccessful');

			return false;
		}

		// Filter the data passed
		$data = $this->filter_data($this->tables['users'], $data);

		if (array_key_exists($this->identityColumn, $data) || array_key_exists('password', $data) || array_key_exists('email', $data)) {
			if (array_key_exists('password', $data)) {
				if (! empty($data['password'])) {
					$data['password'] = $this->hash_password($data['password'], $user->{$this->identityColumn});
					if ($data['password'] === false) {
						$this->my_db->trans_rollback();
						$this->trigger_events(['post_update_user', 'post_update_user_unsuccessful']);
						$this->set_error('IonAuth.update_unsuccessful');

						return false;
					}
				} else {
					// unset password so it doesn't effect database entry if no password passed
					unset($data['password']);
				}
			}
		}

		$this->trigger_events('extra_where');
		$this->my_db->update($this->tables['users'], $data, ['id' => $user->id]);

		if ($this->my_db->trans_status() === false) {
			$this->my_db->trans_rollback();

			$this->trigger_events(['post_update_user', 'post_update_user_unsuccessful']);
			$this->set_error('IonAuth.update_unsuccessful');
			return false;
		}

		$this->my_db->trans_commit();

		$this->trigger_events(['post_update_user', 'post_update_user_successful']);
		$this->set_message('IonAuth.update_successful');
		return true;
	}

	/**
	 * Delete a user
	 *
	 * @param integer $id User id
	 *
	 * @return boolean
	 * @author Phil Sturgeon
	 */
	public function delete_user(int $id): bool
	{
		$this->trigger_events('pre_delete_user');

		$this->my_db->trans_begin();

		// remove user from groups
		$this->remove_from_group(null, $id);

		// delete user from users table should be placed after remove from group
		$this->my_db->delete($this->tables['users'], ['id' => $id]);

		if ($this->my_db->trans_status() === false) {
			$this->my_db->trans_rollback();
			$this->trigger_events(['post_delete_user', 'post_delete_user_unsuccessful']);
			$this->set_error('IonAuth.delete_unsuccessful');
			return false;
		}

		$this->my_db->trans_commit();

		$this->trigger_events(['post_delete_user', 'post_delete_user_successful']);
		$this->set_message('IonAuth.delete_successful');
		return true;
	}

	/**
	 * Update last login
	 *
	 * @param integer $id User id
	 *
	 * @return boolean
	 * @author Ben Edmunds
	 */
	public function update_last_login(int $id): bool
	{
		$this->trigger_events('update_last_login');

		$this->trigger_events('extra_where');

		$this->my_db->where('id', $id)->update($this->tables['users'], ['last_login' => time()]);

		return $this->my_db->affected_rows() === 1;
	}

	/**
	 * Set lang
	 *
	 * @param string $lang Lang
	 *
	 * @return boolean
	 * @author Ben Edmunds
	 */
	public function set_lang(string $lang = 'en'): bool
	{
		if (!$this->useSessions) {
			return false;
		}

		$this->trigger_events('set_lang');

		// if the userExpire is set to zero we'll set the expiration two years from now.
		if ($this->configAuth->userExpire === 0) {
			$expire = self::MAX_COOKIE_LIFETIME;
		}
		// otherwise use what is set
		else {
			$expire = $this->configAuth->userExpire;
		}

		set_cookie([
			'name'   => 'lang_code',
			'value'  => $lang,
			'expire' => $expire,
		]);

		return true;
	}

	/**
	 * Set session
	 *
	 * @param \stdClass $user User
	 *
	 * @return boolean
	 * @author jrmadsen67
	 */
	public function set_session(\stdClass $user): bool
	{
		if (!$this->useSessions) {
			return false;
		}

		$this->trigger_events('pre_set_session');

		$sessionData = [
			'identity'            => $user->{$this->identityColumn},
			$this->identityColumn => $user->{$this->identityColumn},
			'email'               => $user->email,
			'user_id'             => $user->id, //everyone likes to overwrite id so we'll use user_id
			'old_last_login'      => $user->last_login,
			'last_check'          => time(),
		];

		$this->session->set_userdata($sessionData);

		$this->trigger_events('post_set_session');

		return true;
	}

	/**
	 * Set a user to be remembered
	 *
	 * Implemented as described in
	 * https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence
	 *
	 * @param string $identity Identity
	 *
	 * @return boolean
	 * @author Ben Edmunds
	 */
	public function remember_user(string $identity): bool
	{
		$this->trigger_events('pre_remember_user');

		if (! $identity) {
			return false;
		}

		// Generate random tokens
		$token = $this->generate_selector_validator_couple();

		if ($token->validatorHashed) {
			$this->my_db->update(
				$this->tables['users'],
				[
					'remember_selector' => $token->selector,
					'remember_code' => $token->validatorHashed
				],
				[$this->identityColumn => $identity]
			);

			if ($this->my_db->affected_rows() > -1) {
				if ($this->useSessions) {
					// if the userExpire is set to zero we'll set the expiration two years from now.
					if ($this->configAuth->userExpire === 0) {
						$expire = self::MAX_COOKIE_LIFETIME;
					}
					// otherwise use what is set
					else {
						$expire = $this->configAuth->userExpire;
					}

					set_cookie([
						'name'   => $this->configAuth->rememberCookieName,
						'value'  => $token->userCode,
						'expire' => $expire
					]);
				}

				$this->trigger_events(['post_remember_user', 'remember_user_successful']);
				return true;
			}
		}

		$this->trigger_events(['post_remember_user', 'remember_user_unsuccessful']);
		return false;
	}

	/**
	 * Login automatically a user with the "Remember me" feature
	 * Implemented as described in
	 * https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence
	 *
	 * @return boolean
	 * @author Ben Edmunds
	 */
	public function login_remembered_user(): bool
	{
		if (!$this->useSessions) {
			return false;
		}

		$this->trigger_events('pre_login_remembered_user');

		// Retrieve token from cookie
		$rememberCookie = get_cookie($this->configAuth->rememberCookieName);
		$token          = $this->retrieve_selector_validator_couple($rememberCookie);

		if ($token === false) {
			$this->trigger_events(['post_login_remembered_user', 'post_login_remembered_user_unsuccessful']);
			return false;
		}

		// get the user with the selector
		$this->trigger_events('extra_where');

		$this->my_db->select($this->identityColumn . ', id, email, remember_code, last_login')
			->where('remember_selector', $token->selector)
			->where('active', 1)
			->limit(1);

		$query = $this->my_db->get($this->tables['users']);

		// Check that we got the user
		if ($query->num_rows() === 1) {
			// Retrieve the information
			$user = $query->row();

			// Check the code against the validator
			$identity = $user->{$this->identityColumn};
			if ($this->verify_password($token->validator, $user->remember_code, $identity)) {
				$this->update_last_login($user->id);

				$this->set_session($user);

				$this->clear_forgotten_password_code($identity);

				// extend the users cookies if the option is enabled
				if ($this->configAuth->userExtendonLogin) {
					$this->remember_user($identity);
				}

				// Regenerate the session (for security purpose: to avoid session fixation)
				$this->session->regenerate(false);

				$this->trigger_events(['post_login_remembered_user', 'post_login_remembered_user_successful']);
				return true;
			}
		}
		delete_cookie($this->configAuth->rememberCookieName);

		$this->trigger_events(['post_login_remembered_user', 'post_login_remembered_user_unsuccessful']);
		return false;
	}

	/**
	 * Create a group
	 *
	 * @param string $groupName        Group name
	 * @param string $groupDescription Group description
	 * @param array  $additionalData   Additional data
	 *
	 * @return integer|boolean The ID of the inserted group, or false on failure
	 * @author aditya menon
	 */
	public function create_group(string $groupName = '', string $groupDescription = '', array $additionalData = [])
	{
		// bail if the group name was not passed
		if (! $groupName) {
			$this->set_error('IonAuth.groupName_required');
			return false;
		}

		// bail if the group name already exists
		$existingGroup = $this->my_db->where(['name' => $groupName])->count_all_results($this->tables['groups']);
		if ($existingGroup !== 0) {
			$this->set_error('IonAuth.group_already_exists');
			return false;
		}

		$data = [
			'name'        => $groupName,
			'description' => $groupDescription,
		];

		// filter out any data passed that doesnt have a matching column in the groups table
		// and merge the set group data and the additional data
		if (! empty($additionalData)) {
			$data = array_merge($this->filter_data($this->tables['groups'], $additionalData), $data);
		}

		$this->trigger_events('extra_group_set');

		// insert the new group
		$this->my_db->insert($this->tables['groups'], $data);
		$groupId = $this->my_db->insert_id();

		// report success
		$this->set_message('IonAuth.group_creation_successful');
		// return the brand new group id
		return $groupId;
	}

	/**
	 * Update group
	 *
	 * @param integer $groupId        Group id
	 * @param string  $groupName      Group name
	 * @param array   $additionalData Additional datas
	 *
	 * @return boolean
	 * @author aditya menon
	 */
	public function update_group(int $groupId, string $groupName = '', array $additionalData = []): bool
	{
		if (! $groupId) {
			return false;
		}

		$data = [];

		if (! empty($groupName)) {
			// we are changing the name, so do some checks

			// bail if the group name already exists
			$existingGroup = $this->my_db->get_where($this->tables['groups'], ['name' => $groupName])->row();
			if (isset($existingGroup->id) && (int)$existingGroup->id !== $groupId) {
				$this->set_error('IonAuth.group_already_exists');
				return false;
			}

			$data['name'] = $groupName;
		}

		// restrict change of name of the admin group
		$group = $this->my_db->get_where($this->tables['groups'], ['id' => $groupId])->row();
		if ($this->configAuth->adminGroup === $group->name && $groupName !== $group->name) {
			$this->set_error('IonAuth.groupName_admin_not_alter');
			return false;
		}

		// filter out any data passed that doesnt have a matching column in the groups table
		// and merge the set group data and the additional data
		if (! empty($additionalData)) {
			$data = array_merge($this->filter_data($this->tables['groups'], $additionalData), $data);
		}

		$this->my_db->update($this->tables['groups'], $data, ['id' => $groupId]);

		$this->set_message('IonAuth.group_update_successful');

		return true;
	}

	/**
	 * Remove a group.
	 *
	 * @param integer|null $groupId Group id
	 *
	 * @return boolean
	 * @author aditya menon
	 */
	public function delete_group(?int $groupId): bool
	{
		// bail if mandatory param not set
		if (empty($groupId)) {
			return false;
		}
		$group = $this->group($groupId)->row();
		if ($group->name === $this->configAuth->adminGroup) {
			$this->trigger_events(['post_delete_group', 'post_delete_group_notallowed']);
			$this->set_error('IonAuth.group_delete_notallowed');
			return false;
		}

		$this->trigger_events('pre_delete_group');

		$this->my_db->trans_begin();

		// remove all users from this group
		$this->my_db->delete($this->tables['users_groups'], [$this->join['groups'] => $groupId]);
		// remove the group itself
		$this->my_db->delete($this->tables['groups'], ['id' => $groupId]);

		if ($this->my_db->trans_status() === false) {
			$this->my_db->trans_rollback();
			$this->trigger_events(['post_delete_group', 'post_delete_group_unsuccessful']);
			$this->set_error('IonAuth.group_delete_unsuccessful');
			return false;
		}

		$this->my_db->trans_commit();

		$this->trigger_events(['post_delete_group', 'post_delete_group_successful']);
		$this->set_message('group_delete_successful');
		return true;
	}

	/**
	 * Set a single or multiple functions to be called when trigged by trigger_events().
	 *
	 * @param string $event     Event
	 * @param string $name      Name
	 * @param string $class     Class
	 * @param string $method    Method
	 * @param array  $arguments Arguments
	 *
	 * @return self
	 */
	public function set_hook(string $event, string $name, $class, $method, array $arguments = []): self
	{
		$this->ionHooks->{$event}[$name]            = new \stdClass();
		$this->ionHooks->{$event}[$name]->class     = $class;
		$this->ionHooks->{$event}[$name]->method    = $method;
		$this->ionHooks->{$event}[$name]->arguments = $arguments;
		return $this;
	}

	/**
	 * Remove hook
	 *
	 * @param string $event Event
	 * @param string $name  Name
	 *
	 * @return void
	 */
	public function remove_hook(string $event, string $name): void
	{
		if (isset($this->ionHooks->{$event}[$name])) {
			unset($this->ionHooks->{$event}[$name]);
		}
	}

	/**
	 * Remove hooks
	 *
	 * @param string $event Event
	 *
	 * @return void
	 */
	public function remove_hooks(string $event): void
	{
		if (isset($this->ionHooks->$event)) {
			unset($this->ionHooks->$event);
		}
	}

	/**
	 * Call hook
	 *
	 * @param string $event Event
	 * @param string $name  Name
	 *
	 * @return false|mixed
	 */
	protected function call_hook(string $event, string $name)
	{
		if (isset($this->ionHooks->{$event}[$name]) && method_exists($this->ionHooks->{$event}[$name]->class, $this->ionHooks->{$event}[$name]->method)) {
			$hook = $this->ionHooks->{$event}[$name];

			return call_user_func_array([$hook->class, $hook->method], $hook->arguments);
		}

		return false;
	}

	/**
	 * Call Additional functions to run that were registered with set_hook().
	 *
	 * @param string|array $events Event(s)
	 *
	 * @return void
	 */
	public function trigger_events($events): void
	{
		if (is_array($events) && ! empty($events)) {
			foreach ($events as $event) {
				$this->trigger_events($event);
			}
		} else {
			if (isset($this->ionHooks->$events) && ! empty($this->ionHooks->$events)) {
				foreach ($this->ionHooks->$events as $name => $hook) {
					$this->call_hook($events, $name);
				}
			}
		}
	}

	/**
	 * Set the message templates
	 *
	 * @param string $single Template for single message
	 * @param string $list	 Template for list messages
	 *
	 * @return true
	 * @author Ben Edmunds
	 */
	public function set_message_template(string $single = '', string $list = ''): bool
	{
		if (! empty($single)) {
			$this->templates['messages']['single'] = $single;
		}

		if (! empty($list)) {
			$this->templates['messages']['list'] = $list;
		}

		return true;
	}

	/**
	 * Set a message
	 *
	 * @param string $message The message
	 *
	 * @return string The given message
	 * @author Ben Edmunds
	 */
	public function set_message(string $message): string
	{
		$this->messages[] = $message;

		return $message;
	}

	/**
	 * Get the messages
	 *
	 * @return string
	 * @author Ben Edmunds
	 */
	public function messages(): string
	{
		if (empty($this->messages)) {
			return '';
		}

		$this->lang->load('ion_auth');

		$messagesLang = [];
		foreach ($this->messages as $message) {
			$line = $this->lang->line($message);
			$messagesLang[] = $line ? $line : '##' . $message . '##';
		}

		return $this->load->view($this->templates['messages']['list'], ['messages' => $messagesLang], true);
	}

	/**
	 * Get the messages as an array
	 *
	 * @param boolean $langify Translate messages ?
	 *
	 * @return array
	 * @author Raul Baldner Junior
	 */
	public function messages_array(bool $langify = true): array
	{
		if ($langify) {
			$this->lang->load('ion_auth');

			$messagesLang = [];
			foreach ($this->messages as $message) {
				$line = $this->lang->line($message);
				$messagesLang[] = $line ? $line : '##' . $message . '##';
			}

			return $messagesLang;
		} else {
			return $this->messages;
		}
	}

	/**
	 * Clear messages
	 *
	 * @return true
	 * @author Ben Edmunds
	 */
	public function clear_messages()
	{
		$this->messages = [];

		return true;
	}

	/**
	 * Set an error message
	 *
	 * @param string $error The error to set
	 *
	 * @return string The given error
	 * @author Ben Edmunds
	 */
	public function set_error(string $error): string
	{
		$this->errors[] = $error;

		return $error;
	}

	/**
	 * Get the error message
	 *
	 * @return string
	 * @author Ben Edmunds
	 */
	public function errors()
	{
		if (empty($this->errors)) {
			return '';
		}

		$this->lang->load('ion_auth');

		$errorLang = [];
		foreach ($this->errors as $error) {
			$line = $this->lang->line($error);
			$errorLang[] = $line ? $line : '##' . $error . '##';
		}

		return $this->load->view($this->templates['errors']['list'], ['errors' => $errorLang], true);
	}

	/**
	 * Get the error messages as an array
	 *
	 * @param boolean $langify Langify errors ?
	 *
	 * @return array
	 * @author Raul Baldner Junior
	 *
	 */
	public function errors_array(bool $langify = true): array
	{
		if ($langify) {
			$this->lang->load('ion_auth');

			$errorsLang = [];
			foreach ($this->errors as $error) {
				$line = $this->lang->line($error);
				$errorsLang[] = $line ? $line : '##' . $error . '##';
			}

			return $errorsLang;
		} else {
			return $this->errors;
		}
	}

	/**
	 * Clear Errors
	 *
	 * @return true
	 * @author Ben Edmunds
	 */
	public function clear_errors(): bool
	{
		$this->errors = [];

		return true;
	}

	/**
	 * Internal function to set a password in the database
	 *
	 * @param string $identity Identity
	 * @param string $password Password
	 *
	 * @return boolean
	 */
	protected function set_password_db(string $identity, string $password): bool
	{
		$hash = $this->hash_password($password, $identity);

		if ($hash === false) {
			return false;
		}

		// When setting a new password, invalidate any other token
		$data = [
			'password'                => $hash,
			'remember_code'           => null,
			'forgotten_password_code' => null,
			'forgotten_password_time' => null,
		];

		$this->trigger_events('extra_where');

		$this->my_db->where([$this->identityColumn => $identity])->update($this->tables['users'], $data);
		return $this->my_db->affected_rows() === 1;
	}

	/**
	 * Filter data
	 *
	 * @param string $table Table
	 * @param array|null  $data  Data
	 *
	 * @return array
	 */
	protected function filter_data(string $table, ?array $data): array
	{
		$filteredData = [];
		$columns = $this->my_db->list_fields($table);

		if (is_array($data)) {
			foreach ($columns as $column) {
				if (array_key_exists($column, $data)) {
					$filteredData[$column] = $data[$column];
				}
			}
		}

		return $filteredData;
	}

	/**
	 * Generate a random token
	 * Inspired from http://php.net/manual/en/function.random-bytes.php#118932
	 *
	 * @param integer $resultLength Result lenght
	 *
	 * @return string
	 */
	protected function random_token(int $resultLength = 32): string
	{
		if ($resultLength <= 8) {
			$resultLength = 32;
		}

		// Try random_bytes: PHP 7
		if (function_exists('random_bytes')) {
			return bin2hex(random_bytes($resultLength / 2));
		}

		// No luck!
		throw new \Exception('Unable to generate a random token');
	}

	/**
	 * Retrieve hash parameter according to options
	 *
	 * @param string $identity Identity
	 *
	 * @return array|boolean
	 */
	protected function get_hash_parameters(string $identity = '')
	{
		// Check if user is administrator or not
		$isAdmin = false;
		if ($identity) {
			$userId = $this->get_user_id_from_identity($identity);
			if ($userId && $this->in_group($this->configAuth->adminGroup, $userId)) {
				$isAdmin = true;
			}
		}

		$params = false;
		switch ($this->hashMethod) {
			case 'bcrypt':
				$params = [
					'cost' => $isAdmin ? $this->configAuth->bcryptAdminCost
						: $this->configAuth->bcryptDefaultCost
				];
				break;

			case 'argon2':
			case 'argon2id':
				$params = $isAdmin ? $this->configAuth->argon2AdminParams
					: $this->configAuth->argon2DefaultParams;
				break;

			default:
				// Do nothing
		}

		return $params;
	}

	/**
	 * Retrieve hash algorithm according to options
	 *
	 * @return string|boolean
	 */
	protected function get_hash_algo()
	{
		$algo = false;
		switch ($this->hashMethod) {
			case 'bcrypt':
				$algo = PASSWORD_BCRYPT;
				break;

			case 'argon2':
				$algo = PASSWORD_ARGON2I;
				break;

			case 'argon2id':
				$algo = PASSWORD_ARGON2ID;
				break;

			default:
				// Do nothing
		}

		return $algo;
	}

	/**
	 * Generate a random selector/validator couple
	 * This is a user code
	 *
	 * @param integer $selectorSize  Size of the selector token
	 * @param integer $validatorSize Size of the validator token
	 *
	 * @return \stdClass
	 *          ->selector			simple token to retrieve the user (to store in DB)
	 *          ->validatorHashed	token (hashed) to validate the user (to store in DB)
	 *          ->user_code			code to be used user-side (in cookie or URL)
	 */
	protected function generate_selector_validator_couple(int $selectorSize = 40, int $validatorSize = 128): \stdClass
	{
		// The selector is a simple token to retrieve the user
		$selector = $this->random_token($selectorSize);

		// The validator will strictly validate the user and should be more complex
		$validator = $this->random_token($validatorSize);

		// The validator is hashed for storing in DB (avoid session stealing in case of DB leaked)
		$validatorHashed = $this->hash_password($validator);

		// The code to be used user-side
		$userCode = $selector . '.' . $validator;

		return (object) [
			'selector'        => $selector,
			'validatorHashed' => $validatorHashed,
			'userCode'        => $userCode,
		];
	}

	/**
	 * Retrieve remember cookie info
	 *
	 * @param string $userCode A user code of the form "selector.validator"
	 *
	 * @return false|object
	 *          ->selector		simple token to retrieve the user in DB
	 *          ->validator		token to validate the user (check against hashed value in DB)
	 */
	protected function retrieve_selector_validator_couple(string $userCode)
	{
		// Check code
		if ($userCode) {
			$tokens = explode('.', $userCode);

			// Check tokens
			if (count($tokens) === 2) {
				return (object) [
					'selector'  => $tokens[0],
					'validator' => $tokens[1],
				];
			}
		}

		return false;
	}
}
