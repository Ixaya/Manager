<?php
defined('BASEPATH') or exit('No direct script access allowed');

use Ahc\Jwt\JWT;

class Jwt_lib
{
	protected $jwt;

	private $secret;
	private $algorithm;
	private $expiry;

	private $config;
	private $config_key;

	public function __construct()
	{
		// Is the config file in the environment folder?
		if (
			!file_exists($file_path = APPPATH . 'config/' . ENVIRONMENT . '/lib_jwt.php')
			&& !file_exists($file_path = APPPATH . 'config/lib_jwt.php')
		) {
			show_error('The configuration file lib_jwt.php does not exist.');
		}

		include($file_path);

		if (!$this->config_key && isset($active_config)) {
			$this->config_key = $active_config;
		}

		$this->config = isset($jwt_config) ? $jwt_config : [];

		if (!empty($this->config[$this->config_key])) {
			$this->load_config($this->config[$this->config_key]);
		}
	}

	public function set_config_key($key)
	{
		if (!empty($this->config[$key])) {
			$this->config_key = $key;
			$this->load_config($this->config[$key]);
		}
	}

	private function load_config($config)
	{
		$this->secret 		= $config['secret'] ?? '';
		$this->algorithm 		= $config['algorithm'] ?? '';
		$this->expiry = $config['expiry'] ?? '';

		$this->setup_jws();
	}

	private function setup_jws()
	{
		if (empty($this->secret)){
			return;
		}

		$this->jwt = new JWT(
			$this->secret,
			$this->algorithm,
			$this->expiry,
			10 // leeway for clock skew
		);
	}

	/**
	 * Generate JWT token for authenticated user
	 * 
	 * @param int $user_id User database ID
	 * @param string $aud Token audience identifier
	 * @param array $scopes User permissions/roles
	 * @param array $extra Extra data to merge (optional)
	 * @return string JWT token
	 */
	public function generate_token($user_id, $aud, $scopes = ['user'], $extra = [])
	{
		if (!isset($this->jwt)){
			return '';
		}

		$payload = [
			'uid'      => $user_id,
			'scopes'   => $scopes,
			'iss'      => secure_url(),
			'aud'      => $aud
		];

		if (!empty($extra)) {
			$payload = array_merge($extra, $payload);
		}

		return $this->jwt->encode($payload);
	}

	/**
	 * Decode and validate JWT token
	 * 
	 * @param string $token JWT token
	 * @param string $aud Token audience identifier
	 * @return object|false Payload object or false on failure
	 */
	public function decode_token($token, $aud)
	{
		try {
			$payload = $this->jwt->decode($token);

			// Validate audience matches
			if (!isset($payload['aud']) || $payload['aud'] !== $aud) {
				return false;
			}

			return $payload;
		} catch (Exception $e) {
			log_message('error', 'JWT decode error: ' . $e->getMessage());
			return false;
		}
	}
}