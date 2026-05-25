<?php

(defined('BASEPATH')) or exit('No direct script access allowed');

/**
 * Modular Extensions - HMVC
 *
 * Adapted from the CodeIgniter Core Classes
 * @link	http://codeigniter.com
 *
 * Description:
 * This library extends the CodeIgniter CI_Config class
 * and adds features allowing use of modules and the HMVC design pattern.
 *
 * Install this file as application/third_party/MX/Config.php
 *
 * @copyright	Copyright (c) 2015 Wiredesignz
 * @version 	5.5
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 **/
class MX_Config extends CI_Config
{
	/**
	 * Get the full file path of a config file (supports modules)
	 * Returns the path without loading the config
	 *
	 * @param string $file Config filename (without .php)
	 * @param string $_module Module name (optional, auto-detected if empty)
	 * @return string|null Full file path or FALSE if not found
	 */
	public function path(string $file = '', string $_module = ''): ?string
	{
		if (empty($file)) {
			return null;
		}

		$file =  str_replace('.php', '', $file);

		if ($_module == '') {
			return $this->path_env($file);
		}

		return $this->path_module($file, $_module);
	}

	private function path_env(string $file = ''): ?string
	{
		foreach ($this->_config_paths as $path) {
			foreach ([$file, ENVIRONMENT . DIRECTORY_SEPARATOR . $file] as $location) {
				$file_path = $path . 'config/' . $location . '.php';
				if (in_array($file_path, $this->is_loaded, true)) {
					return $file_path;
				}

				if (! file_exists($file_path)) {
					continue;
				}

				return $file_path;
			}
		}
		return null;
	}

	private function path_module(string $file = '', string $_module = '', bool $fallback = true): ?string
	{
		$_module or $_module = CI::$APP->router->fetch_module();
		list($path, $file) = Modules::find($file, $_module, 'config/');

		if ($path === false) {
			return ($fallback) ? $this->path_env($file) : null;
		}

		$file_path = $path . $file . '.php';
		if (in_array($file_path, $this->is_loaded, true) || file_exists($file_path)) {
			return $file_path;
		}

		return null;
	}

	/**
	 * Load and return config array without storing it in $this->config
	 * Useful for reading sensitive configs that you don't want to keep in memory
	 *
	 * @param string $file Config filename
	 * @param string $_module Module name (optional)
	 * @return array|null Config array or null on failure
	 */
	public function read(string $file = '', string $_module = '', $fail_gracefully = true): ?array
	{
		$file_path = $this->path($file, $_module);

		if ($file_path === null) {
			log_message('error', "Config file not found: {$file}");
			return null;
		}

		return $this->read_path($file_path, $fail_gracefully);
	}

	private function read_path(string $file_path, bool $fail_gracefully = false): ?array
	{
		// Isolate the included file's variables
		$config = [];
		// Load config file
		/** @var array $config */
		include($file_path);

		if (empty($config)) {
			log_message('error', "Invalid config file: {$file_path}");
			if ($fail_gracefully) {
				return null;
			}

			show_error('Your ' . $file_path . ' file does not appear to contain a valid configuration array.');
		}

		return $config;
	}

	public function load($file = '', $use_sections = false, $fail_gracefully = false, $_module = '')
	{
		if ($_module == '') {
			return parent::load($file, $use_sections, $fail_gracefully);
		}

		$file =  str_replace('.php', '', $file);
		$file_path = $this->path_module($file, $_module, false);

		if ($file_path === null) {
			return parent::load($file, $use_sections, $fail_gracefully);
		}

		if (in_array($file_path, $this->is_loaded, true)) {
			return true;
		}

		$config = $this->read_path($file_path, $fail_gracefully);

		if ($config === null) {
			return false;
		}

		if ($use_sections === true) {
			$this->config[$file] = isset($this->config[$file])
				? array_merge($this->config[$file], $config)
				: $config;
		} else {
			$this->config = array_merge($this->config, $config);
		}

		$this->is_loaded[] = $file_path;
		$config = null;

		log_message('debug', 'Config file loaded: ' . $file_path);

		return true;
	}
	/**
	 * Image URL
	 *
	 * Returns image_url [. uri_string]
	 *
	 * @uses	CI_Config::_uri_string()
	 *
	 * @param	string|string[]	$uri	URI string or an array of segments
	 * @param	string	$protocol
	 * @return	string
	 */
	public function image_url($uri = '', $protocol = null)
	{
		$image_url = $this->slash_item('image_url');
		if (empty($image_url)) {
			$image_url = $this->slash_item('base_url');
		}

		if (isset($protocol)) {
			// For protocol-relative links
			if ($protocol === '') {
				$image_url = substr($image_url, strpos($image_url, '//'));
			} else {
				$image_url = $protocol . substr($image_url, strpos($image_url, '://'));
			}
		}

		return $image_url . $this->_uri_string($uri);
	}
}
