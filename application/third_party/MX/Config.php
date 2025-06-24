<?php (defined('BASEPATH')) OR exit('No direct script access allowed');

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
	public function load($file = '', $use_sections = FALSE, $fail_gracefully = FALSE, $_module = '') 
	{
		if ($_module == ''){
			return parent::load($file, $use_sections, $fail_gracefully);
		}

		$_module OR $_module = CI::$APP->router->fetch_module();
		list($path, $file) = Modules::find($file, $_module, 'config/');

		if ($path === FALSE)
		{
			return parent::load($file, $use_sections, $fail_gracefully);					
		}

		$file_path = $path . $file . '.php';
		if (in_array($file_path, $this->is_loaded, TRUE)) {
			return TRUE;
		}

		if (! file_exists($file_path)) {
			return false;
		}

		include($file_path);

		if (! isset($config) or ! is_array($config)) {
			if ($fail_gracefully === TRUE) {
				return FALSE;
			}

			show_error('Your ' . $file_path . ' file does not appear to contain a valid configuration array.');
		}

		if ($use_sections === TRUE) {
			$this->config[$file] = isset($this->config[$file])
				? array_merge($this->config[$file], $config)
				: $config;
		} else {
			$this->config = array_merge($this->config, $config);
		}

		$this->is_loaded[] = $file_path;
		$config = NULL;

		log_message('debug', 'Config file loaded: ' . $file_path);
		
		return TRUE;
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
	public function image_url($uri = '', $protocol = NULL)
	{
		$image_url = $this->slash_item('image_url');
		if (empty($image_url))
			$image_url = $this->slash_item('base_url');

		if (isset($protocol))
		{
			// For protocol-relative links
			if ($protocol === '')
			{
				$image_url = substr($image_url, strpos($image_url, '//'));
			}
			else
			{
				$image_url = $protocol.substr($image_url, strpos($image_url, '://'));
			}
		}

		return $image_url.$this->_uri_string($uri);
	}
}