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
 | Hash Method ( bcrypt)
 | -------------------------------------------------------------------------
 | Bcrypt is available in PHP 5.3+
 |
 | IMPORTANT: Based on the recommendation by many professionals, it is highly recommended to use
 | bcrypt instead of sha1.
 |
 | NOTE: If you use bcrypt you will need to increase your password column character limit to (80)
 |
 | Below there is "default_rounds" setting.  This defines how strong the encryption will be,
 | but remember the more rounds you set the longer it will take to hash (CPU usage) So adjust
 | this based on your server hardware.
 |
 | If you are using Bcrypt the Admin password field also needs to be changed in order to login as admin:
 | $2y$: $2y$08$200Z6ZZbp3RAEXoaWcMA6uJOFicwNZaqk4oDhqTUiFXFe63MG.Daa
 | $2a$: $2a$08$6TTcWD1CJ8pzDy.2U3mdi.tpl.nYOR1pwYXwblZdyQd9SL16B7Cqa
 |
 | Be careful how high you set max_rounds, I would do your own testing on how long it takes
 | to encrypt with x rounds.
 |
 */
// Run test_argon2.php on server to determine config
$config['hashMethod'] = 'argon2';
$config['hashConfig'] = [
	'memory_cost' => 1 << 17, // 2^17 = 131072 (128 MB)
	'time_cost' => 4,
	'threads' => 1,
];

// Run test_bcrypt.php on server to determine config
// $config['hashMethod'] = 'bcrypt';
// $config['hashConfig'] = [
// 	'cost' => 13
// ];

/*
 | -------------------------------------------------------------------------
 | Authentication options.
 | -------------------------------------------------------------------------
 | maximum_login_attempts: This maximum is not enforced by the library, but is
 | used by $this->ion_auth->is_max_login_attempts_exceeded().
 | The controller should check this function and act
 | appropriately. If this variable set to 0, there is no maximum.
 */
$config['defaultGroup'] = 'members'; // Default group, use name
$config['adminGroup'] = 'admin'; // Default administrators group, use name
$config['identity'] = 'username'; // A database column which is used to login with

$config['connectionName'] = null; //Database connection group name, leave empty for default
$config['identityExtraColumns'] = ['first_name', 'last_name', 'image_url']; // Database identity extra columns to respond

$config['minPasswordLength'] = 8; // Minimum Required Length of Password
$config['maxPasswordLength'] = 20; // Maximum Allowed Length of Password
$config['emailActivation'] = false; // Email Activation for registration
$config['manualActivation'] = true; // Manual Activation for registration
$config['rememberUsers'] = false; // Allow users to be remembered and enable auto-login
$config['sessionsEnabled'] = false; // Use sessions to keep users logged in (Default: TRUE)
$config['userExpire'] = 1; // How long to remember the user (seconds). Set to zero for no expiration
$config['userExtendOnLogin'] = false; // Extend the users cookies every time they auto-login
$config['trackLoginAttempts'] = true; // Track the number of failed login attempts for each user or ip.
$config['trackLoginIpAddress'] = false; // Track login attempts by IP Address, if FALSE will track based on identity. (Default: TRUE)
$config['maximumLoginAttempts'] = 5; // The maximum number of failed login attempts.
$config['lockoutTime'] = 600; // The number of seconds to lockout an account due to exceeded attempts
$config['forgotPasswordExpiration'] = 1800000; // The number of milliseconds after which a forgot password request will expire. If set to 0, forgot password requests will not expire.

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
 | Email options.
 | -------------------------------------------------------------------------
 | email_config:
 | 	'file' = Use the default CI config or use from a config file
 | 	array= Manually set your email config settings
 */
$config['useCiEmail'] = false; // Send Email using the builtin CI email class, if false it will return the code and the identity
$config['emailConfig'] = [
	'mailtype' => 'html',
];

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



$config['trackLoginAttempts']      = true;                // Track the number of failed login attempts for each user or ip.
$config['trackLoginIpAddress']      = true;                // Track login attempts by IP Address, if false will track based on identity. (Default: true)
$config['maximumLoginAttempts']     = 3;                   // The maximum number of failed login attempts.
$config['lockoutTime']              = 600;                 /* The number of seconds to lockout an account due to exceeded attempts
																	You should not use a value below 60 (1 minute) */
$config['forgotPasswordExpiration'] = 1800;                /* The number of seconds after which a forgot password request will expire. If set to 0, forgot password requests will not expire.
																	30 minutes to 1 hour are good values (enough for a user to receive the email and reset its password)
																	You should not set a value too high, as it would be a security issue! */
$config['recheckTimer']             = 0;                   /* The number of seconds after which the session is checked again against database to see if the user still exists and is active.
																	Leave 0 if you don't want session recheck. if you really think you need to recheck the session against database, we would
																	recommend a higher value, as this would affect performance */


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
$config['hashMethod']          = 'argon2id';  // bcrypt, argon2 (argon2i) or argon2id
$config['bcryptDefaultCost']   = 12;        // Set cost according to your server benchmark - but no lower than 12 (default PHP value)
$config['bcryptAdminCost']     = 13;        // Cost for user in admin group
$config['argon2DefaultParams'] = [
	'memory_cost' => 1 << 17, // 2^17 = 131072 (128 MB)
	'time_cost' => 4,
	'threads' => 1,
];
$config['argon2AdminParams']   = [
	'memory_cost' => 1 << 17, // 2^17 = 131072 (128 MB)
	'time_cost' => 5,
	'threads' => 1,
];
