<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * Keys Model
 * This is a basic Key Management REST controller to make and delete keys
 *
 * @package		 CodeIgniter
 * @subpackage	  Rest Server
 * @category		Controller
 * @author		  Phil Sturgeon, Chris Kacerguis
 * @license		 MIT
 * @link			https://github.com/chriskacerguis/codeigniter-restserver
 * @modified		ho@ixaya.com -> converted controller into model.
 */
class Rest_key_model extends CI_Model {
	
	function __construct()
	{
		// Construct the parent class
		parent::__construct();
		
		$this->load->config('rest');
	}
	
	/**
	 * Insert a key into the database
	 *
	 * @access public
	 * @return void
	 */
	public function add_key($level = false, $user_id = false, $returnKey = false)
	{
		// Build a new key
		$key = $this->_generate_key();
		$data = array();
		// If no key level provided, provide a generic key
		if ($level)
			$data['level'] = $level;
		
		if ($user_id)
			$data['user_id'] = $user_id;
		
		//$ignore_limits = ctype_digit($this->put('ignore_limits')) ? (int) $this->put('ignore_limits') : 1;
		$result = $this->_insert_key($key, $data);
		// Insert the new key
		if ($returnKey)
			return $key;
		else
			return $result;
	}
	
	/**
	 * Remove a key from the database to stop it working
	 *
	 * @access public
	 * @return void
	 */
	public function delete_key($key)
	{
		
		// Does this key exist?
		if (!$this->_key_exists($key))
			return false;
		
		// Destroy it
		$this->_delete_key($key);
		
		// Respond that the key was destroyed
		return true;
	}
	
	/**
	 * Change the level
	 *
	 * @access public
	 * @return void
	 */
	public function set_key_level($key, $new_level)
	{
		// Does this key exist?
		if (!$this->_key_exists($key))
			return false;
		
		// Update the key level
		if ($this->_update_key($key, ['level' => $new_level]))
			return true;
		else
			return false;
	}
	
	/**
	 * Suspend a key
	 *
	 * @access public
	 * @return void
	 */
	public function suspend_key($key)
	{
		// Does this key exist?
		if (!$this->_key_exists($key))
			return false;
		
		// Update the key level
		if ($this->_update_key($key, ['level' => 0]))
			return true;
		else
			return false;
	}
	
	/**
	 * Regenerate a key
	 *
	 * @access public
	 * @return void
	 */
	public function regenerate_post($old_key)
	{
		$key_details = $this->_get_key($old_key);
		
		// Does this key exist?
		if (!$key_details)
			return false;
		
		// Build a new key
		$new_key = $this->_generate_key();
		
		// Insert the new key
		if ($this->_insert_key($new_key, ['level' => $key_details->level, 'ignore_limits' => $key_details->ignore_limits]))
		{
			// Suspend old key
			$this->_update_key($old_key, ['level' => 0]);
			
			return true;
		}
		else
		{
			return false;
		}
	}
	
	public function get_user_key($user_id)
	{
		$keyRow = $this->db
					->where('user_id', $user_id)
					->get(config_item('rest_keys_table'))
					->row();
		if ($keyRow)
			return $keyRow->key;
		else
			return $this->add_key(1, $user_id, true);
	}
	public function delete_user_key($user_id)
	{
		return $this->db
		->where('user_id', $user_id)
		->delete(config_item('rest_keys_table'));
	}
	/* Helper Methods */
	
	private function _generate_key()
	{
		do
		{
			// Generate a random salt
			$salt = base_convert(bin2hex($this->security->get_random_bytes(64)), 16, 36);
			
			// If an error occurred, then fall back to the previous method
			if ($salt === FALSE)
			{
				$salt = hash('sha256', time() . mt_rand());
			}
			
			$new_key = substr($salt, 0, config_item('rest_key_length'));
		}
		while ($this->_key_exists($new_key));
		
		return $new_key;
	}
	
	/* Private Data Methods */
	
	private function _get_key($key)
	{
		return $this->db
		->where(config_item('rest_key_column'), $key)
		->get(config_item('rest_keys_table'))
		->row();
	}
	
	private function _key_exists($key)
	{
		return $this->db
		->where(config_item('rest_key_column'), $key)
		->count_all_results(config_item('rest_keys_table')) > 0;
	}
	
	private function _insert_key($key, $data)
	{
		$data[config_item('rest_key_column')] = $key;
		$data['date_created'] = function_exists('now') ? now() : time();
		
		return $this->db
		->set($data)
		->insert(config_item('rest_keys_table'));
	}
	
	private function _update_key($key, $data)
	{
		return $this->db
		->where(config_item('rest_key_column'), $key)
		->update(config_item('rest_keys_table'), $data);
	}
	
	private function _delete_key($key)
	{
		return $this->db
		->where(config_item('rest_key_column'), $key)
		->delete(config_item('rest_keys_table'));
	}
	
}
