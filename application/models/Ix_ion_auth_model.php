<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
* Name:  Ion Auth Model
*
* Author:  Ben Edmunds
* 		   ben.edmunds@gmail.com
*	  	   @benedmunds
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
* Requirements: PHP5 or above
*
*/
require APPPATH . '/models/Ion_auth_model.php';

class Ix_ion_auth_model extends Ion_auth_model
{

	/**
	 * register
	 *
	 * @return bool
	 * @author Mathew
	 * @modified ho@ixaya.com
	 **/
	public function register_facebook($identity, $additional_data = array(), $groups = array())
	{
		$this->trigger_events('pre_register');

		if ($this->identity_check($identity))
		{
			$this->set_error('account_creation_duplicate_identity');
			return FALSE;
		}
		elseif ( !$this->config->item('default_group', 'ion_auth') && empty($groups) )
		{
			$this->set_error('account_creation_missing_default_group');
			return FALSE;
		}

		// check if the default set in config exists in database
		$query = $this->db->get_where($this->tables['groups'],array('name' => $this->config->item('default_group', 'ion_auth')),1)->row();
		if( !isset($query->id) && empty($groups) )
		{
			$this->set_error('account_creation_invalid_default_group');
			return FALSE;
		}

		// capture default group details
		$default_group = $query;

		// IP Address
		$ip_address = $this->_prepare_ip($this->input->ip_address());

		// Users table.
		$data = array(
			$this->identity_column   => $identity,
			'facebook_id' => $identity,
			'ip_address'  => $ip_address,
			'created_on'  => time(),
			'active'	  => 1
		);

		if ($this->store_salt)
		{
			$data['salt'] = $salt;
		}

		// filter out any data passed that doesnt have a matching column in the users table
		// and merge the set user data and the additional data
		$user_data = array_merge($this->_filter_data($this->tables['users'], $additional_data), $data);

		$this->trigger_events('extra_set');

		$this->db->insert($this->tables['users'], $user_data);

		$id = $this->db->insert_id();
				
		// add in groups array if it doesn't exists and stop adding into default group if default group ids are set
		if( isset($default_group->id) && empty($groups) )
		{
			$groups[] = $default_group->id;
		}

		if (!empty($groups))
		{
			// add to groups
			foreach ($groups as $group)
			{
				$this->add_to_group($group, $id);
			}
		}

		$this->trigger_events('post_register');

		return (isset($id)) ? $id : FALSE;
	}

	/**
	 * login
	 *
	 * @return bool
	 * @author Mathew
	 * @modified ho@ixaya.com
	 **/
	public function login_facebook($identity, $facebook_token, $remember=FALSE, $returnUser=FALSE)
	{
		$this->trigger_events('pre_login');

		if (empty($identity))
		{
			$this->set_error('login_unsuccessful');
			return FALSE;
		}
		
		$this->trigger_events('extra_where');

		$query = $this->db->select($this->identity_column . ', id, facebook_id, active, last_login')
						  ->where($this->identity_column, $identity)
						  ->limit(1)
						  ->order_by('id', 'desc')
						  ->get($this->tables['users']);

		if($this->is_time_locked_out($identity))
		{
			// Hash something anyway, just to take up time
			$this->hash_password($identity);

			$this->trigger_events('post_login_unsuccessful');
			$this->set_error('login_timeout');
			//remove
			echo('login_timeout');
			
			return FALSE;
		}

		if ($query->num_rows() === 1)
		{
			$user = $query->row();

			if ($user->active == 0)
			{
				$this->trigger_events('post_login_unsuccessful');
				$this->set_error('login_unsuccessful_not_active');
				//remove
				echo('login_unsuccessful_not_active');
				
				return FALSE;
			}
			
			
			$this->update_token($identity, $facebook_token);
			
			if ($returnUser)
				return $user;
				
			$this->set_session($user);

			$this->update_last_login($user->id);

			$this->clear_login_attempts($identity);

			if ($remember && $this->config->item('remember_users', 'ion_auth'))
			{
				$this->remember_user($user->id);
			}

			$this->trigger_events(array('post_login', 'post_login_successful'));
			$this->set_message('login_successful');

			return TRUE;
		}

		// Hash something anyway, just to take up time
		$this->hash_password($identity);

		$this->increase_login_attempts($identity);

		$this->trigger_events('post_login_unsuccessful');
		$this->set_error('login_unsuccessful');

		return FALSE;
	}
	public function update_token($identity, $facebook_token)
	{
		$data = array( 'fb_token' => $facebook_token);
		
		$query = $this->db->where($this->identity_column, $identity)
						  ->update($this->tables['users'], $data);
	}
}
