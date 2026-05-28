<?php

if (! defined('BASEPATH')) {
	exit('No direct script access allowed');
}
/**
 * Name:  Ion Auth
 *
 * Version: 4.0.3
 *
 * Author: Ben Edmunds
 *		ben.edmunds@gmail.com
 *		@benedmunds
 *
 * Added Awesomeness: Phil Sturgeon
 *
 * Location: http://github.com/benedmunds/CodeIgniter-Ion-Auth
 *
 * Created:  10.01.2009
 *
 * Description:  Modified auth system based on redux_auth with extensive customization.  This is basically what Redux Auth 2 should be.
 * Original Author name has been kept but that does not mean that the method has not been modified.
 *
 *
 */

/*
| -------------------------------------------------------------------------
| Tables.
| -------------------------------------------------------------------------
| Database table names.
*/
$config['tables']['users'] = 'user';
$config['tables']['groups'] = 'group';
$config['tables']['users_groups'] = 'user_group';
$config['tables']['login_attempts'] = 'login_attempt';

/*
 | Users table column and Group table column you want to join WITH.
 |
 | Joins from users.id
 | Joins from groups.id
 */
$config['join']['users'] = 'user_id';
$config['join']['groups'] = 'group_id';


/*
 | -------------------------------------------------------------------------
 | Authentication options.
 | -------------------------------------------------------------------------
 */
// --- Identity & Database ---
$config['defaultGroup'] = 'members'; // Default group, use name
$config['adminGroup'] = 'admin'; // Default administrators group, use name
$config['identity'] = 'username'; // Database column used to login with
$config['connectionName'] = mgr_env('AUTH_DB_CONNECTION', null); // Database connection group name, leave empty for default
$config['identityExtraColumns'] = mgr_env_array('AUTH_IDENTITY_EXTRA_COLUMNS', ['first_name', 'last_name', 'image_url']); // Extra columns returned on identity

// --- Registration ---
$config['emailActivation'] = mgr_env_bool('AUTH_EMAIL_ACTIVATION', false); // Require email activation on registration
$config['manualActivation'] = mgr_env_bool('AUTH_MANUAL_ACTIVATION', true); // Require manual activation on registration
$config['rememberUsers'] = false; // Allow remember-me / auto-login
$config['userExpire'] = 1; // Remember-me duration in seconds, 0 for no expiration
$config['userExtendOnLogin'] = false; // Extend remember-me cookie on each auto-login

// --- Login Protection ---
$config['trackLoginAttempts'] = mgr_env_bool('AUTH_TRACK_LOGIN_ATTEMPTS', true); // Track failed login attempts per user or IP
$config['trackLoginIpAddress'] = mgr_env_bool('AUTH_TRACK_LOGIN_IP', false); // Track by IP if true, by identity if false
$config['maximumLoginAttempts'] = mgr_env_int('AUTH_MAX_LOGIN_ATTEMPTS', 3); // Max failed attempts before lockout
$config['lockoutTime'] = mgr_env_int('AUTH_LOCKOUT_TIME', 600); // Lockout duration in seconds, minimum 60

// Forgot password link expiration in seconds, 0 to disable expiration
// 1800 (30 min) is recommended — long enough to receive email, short enough to be safe
$config['forgotPasswordExpiration'] = mgr_env_int('AUTH_FORGOT_PASSWORD_EXPIRATION', 1800);

// Session recheck interval in seconds against database (user exists and is active)
// 0 disables recheck — only enable if needed, has a performance cost
$config['recheckTimer'] = mgr_env_int('AUTH_RECHECK_TIMER', 0);
/*
 | -------------------------------------------------------------------------
 | Cookie options.
 | -------------------------------------------------------------------------
 | remember_cookie_name Default: remember_code
 | identity_cookie_name Default: identity
 */
$config['rememberCookieName'] = 'remember_code';
$config['identityCookieName'] = 'identity';

/*
 | -------------------------------------------------------------------------
 | Templates.
 | -------------------------------------------------------------------------
 */
$config['templates'] = [
	'errors'   => [
		'list' => 'auth/messages/list_errors',
	],

	// templates for messages
	'messages' => [
		'list'   => 'auth/messages/list',
		'single' => 'auth/messages/single',
	],
];

/*
| -------------------------------------------------------------------------
| Hash Method (bcrypt or argon2)
| -------------------------------------------------------------------------
| Bcrypt is available in PHP 5.3+
| Argon2 is available in PHP 7.2
|
| Argon2 is recommended by expert (it is actually the winner of the Password Hashing Competition
| for more information see https://password-hashing.net). So if you can (PHP 7.2), go for it.
|
| Bcrypt specific:
| 		bcryptDefaultCost settings:  This defines how strong the encryption will be.
| 		However, higher the cost, longer it will take to hash (CPU usage) So adjust
| 		this based on your server hardware.
|
| 		You can (and should!) benchmark your server. This can be done easily with this little script:
| 		https://gist.github.com/Indigo744/24062e07477e937a279bc97b378c3402
|
| 		With bcrypt, an example hash of "password" is:
| 		$2y$08$200Z6ZZbp3RAEXoaWcMA6uJOFicwNZaqk4oDhqTUiFXFe63MG.Daa
|
|		A specific parameter bcryptAdminCost is available for user in admin group.
|		It is recommended to have a stronger hashing for administrators.
|
| Argon2 specific:
| 		argon2DefaultParams settings:  This is an array containing the options for the Argon2 algorithm.
| 		You can define 3 differents keys:
| 			memory_cost (default 4096 kB)
|				Maximum memory (in kBytes) that may be used to compute the Argon2 hash
|				The spec recommends setting the memory cost to a power of 2.
| 			time_cost (default 2)
|				Number of iterations (used to tune the running time independently of the memory size).
|			This defines how strong the encryption will be.
| 			threads (default 2)
|				Number of threads to use for computing the Argon2 hash
|				The spec recommends setting the number of threads to a power of 2.
|
| 		You can (and should!) benchmark your server. This can be done easily with this little script:
| 		https://gist.github.com/Indigo744/e92356282eb808b94d08d9cc6e37884c
|
| 		With argon2, an example hash of "password" is:
| 		$argon2i$v=19$m=1024,t=2,p=2$VEFSSU4wSzh3cllVdE1JZQ$PDeks/7JoKekQrJa9HlfkXIk8dAeZXOzUxLBwNFbZ44
|
|		A specific parameter argon2AdminParams is available for user in admin group.
|		It is recommended to have a stronger hashing for administrators.
|
| For more information, check the password_hash function help: http://php.net/manual/en/function.password-hash.php
|
*/
$config['hashMethod'] = mgr_env('AUTH_HASH_METHOD', 'argon2id'); // bcrypt, argon2 (argon2i) or argon2id

// Run test_bcrypt.php on server to determine config
$config['bcryptDefaultCost'] = mgr_env('AUTH_BCRYPT_COST', 12); // Set cost according to your server benchmark - but no lower than 12 (default PHP value)

// Run test_argon2.php on server to determine config
$config['argon2DefaultParams'] = [
	'memory_cost' => mgr_env('AUTH_ARGON2_MEMORY_COST', 65536), //64 MB
	'time_cost' => mgr_env('AUTH_ARGON2_TIME_COST', 2),
	'threads' => mgr_env('AUTH_ARGON2_THREADS', 1),
];
