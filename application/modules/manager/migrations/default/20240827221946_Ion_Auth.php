<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Ion_auth extends MGR_Migration_builder
{
	public function up()
	{
		// Drop table 'group' if it exists
		$this->dbforge->drop_table('group', true);

		// Table structure for table 'group'
		$this->dbforge->add_field([
			...$this->field_id('id'),
			...$this->field(name: 'name', type: MgrFieldType::VarChar, length: 20),
			...$this->field(name: 'name', type: MgrFieldType::VarChar, length: 100),
			...$this->field(name: 'description', type: MgrFieldType::VarChar, length: 100),
			...$this->field(name: 'level', type: MgrFieldType::SmallInt)
		]);
		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('group');

		// Drop table 'user' if it exists
		$this->dbforge->drop_table('user', true);

		$this->dbforge->add_field([
			...$this->field_id('id'),
			...$this->field(name: 'ip_address', type: MgrFieldType::VarChar, length: 16),
			...$this->field(name: 'username', type: MgrFieldType::VarChar, length: 100),
			...$this->field(name: 'password', type: MgrFieldType::VarChar, length: 80),
			...$this->field(name: 'salt', type: MgrFieldType::VarChar, length: 40),
			...$this->field(name: 'email', type: MgrFieldType::VarChar, length: 100),
			...$this->field(name: 'activation_code', type: MgrFieldType::VarChar, length: 40, nullable: true),
			...$this->field(name: 'forgotten_password_code', type: MgrFieldType::VarChar, length: 40, nullable: true),
			...$this->field(name: 'forgotten_password_time', type: MgrFieldType::Int, unsigned: true, nullable: true),
			...$this->field(name: 'remember_code', type: MgrFieldType::VarChar, length: 40, nullable: true),
			...$this->field(name: 'created_on', type: MgrFieldType::Int, unsigned: true),
			...$this->field(name: 'last_login', type: MgrFieldType::Int, unsigned: true, nullable: true),
			...$this->field(name: 'active', type: MgrFieldType::TinyInt, length: 1, nullable: true),
			...$this->field(name: 'first_name', type: MgrFieldType::VarChar, length: 50, nullable: true),
			...$this->field(name: 'last_name', type: MgrFieldType::VarChar, length: 50, nullable: true),
			...$this->field(name: 'company', type: MgrFieldType::VarChar, length: 100, nullable: true),
			...$this->field(name: 'phone', type: MgrFieldType::VarChar, length: 20, nullable: true),
			...$this->field(name: 'image_name', type: MgrFieldType::VarChar, length: 128, nullable: true),
			...$this->field(name: 'image_url', type: MgrFieldType::VarChar, length: 254, nullable: true),
			...$this->field(name: 'last_update', type: MgrFieldType::Timestamp),
			...$this->field(name: 'last_activity_date', type: MgrFieldType::Timestamp, nullable: true),
			...$this->field(name: 'last_activity_os', type: MgrFieldType::TinyInt, unsigned: true, nullable: true),
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('user');

		$this->modify_field_timestamp('user', 'last_update');

		// Drop table 'user_group' if it exists
		$this->dbforge->drop_table('user_group', true);

		// Table structure for table 'user_group'
		$this->dbforge->add_field([
			...$this->field_id('id'),
			...$this->field(name: 'user_id', type: MgrFieldType::Int, unsigned: true),
			...$this->field(name: 'group_id', type: MgrFieldType::Int, unsigned: true),
		]);
		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('user_group');

		// Drop table 'login_attempt' if it exists
		$this->dbforge->drop_table('login_attempt', true);

		// Table structure for table 'login_attempt'
		$this->dbforge->add_field([
			...$this->field_id('id'),
			...$this->field(name: 'ip_address', type: MgrFieldType::VarChar, length: 16),
			...$this->field(name: 'login', type: MgrFieldType::VarChar, length: 100, nullable: true),
			...$this->field(name: 'time', type: MgrFieldType::BigInt, unsigned: true, nullable: true)
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('login_attempt');

		$this->initial_data();
	}

	public function down()
	{
		$this->dbforge->drop_table('user', true);
		$this->dbforge->drop_table('group', true);
		$this->dbforge->drop_table('user_group', true);
		$this->dbforge->drop_table('login_attempt', true);
	}

	private function initial_data()
	{
		// Insert group records
		$this->db->insert('group', [
			'name'        => 'admin',
			'level'       => '10',
			'description' => 'Administrator'
		]);
		$admin_group_id = $this->db->insert_id();

		$this->db->insert('group', [
			'name'        => 'members',
			'level'       => '1',
			'description' => 'General User'
		]);
		$members_group_id = $this->db->insert_id();

		// Insert user
		$this->db->insert('user', [
			'ip_address'             => '127.0.0.1',
			'username'               => 'admin@admin.com',
			'password'               => '$2a$07$SeBknntpZror9uyftVopmu61qg0ms8Qv1yV6FG.kQOSM.9QhmTo36',
			'salt'                   => '',
			'email'                  => 'admin@admin.com',
			'activation_code'        => '',
			'forgotten_password_code' => null,
			'created_on'             => '1268889823',
			'last_login'             => '1268889823',
			'active'                 => '1',
			'first_name'             => 'Admin',
			'last_name'              => 'istrator',
			'company'                => 'ADMIN',
			'phone'                  => '0'
		]);
		$admin_user_id = $this->db->insert_id();

		// Insert relationships using captured IDs
		$this->db->insert_batch('user_group', [
			['user_id' => $admin_user_id, 'group_id' => $admin_group_id],
			['user_id' => $admin_user_id, 'group_id' => $members_group_id],
		]);
	}
}
