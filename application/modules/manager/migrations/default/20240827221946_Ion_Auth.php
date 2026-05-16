<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Ion_auth extends CI_Migration
{
	public function up()
	{
		// Drop table 'group' if it exists
		$this->dbforge->drop_table('group', true);

		// Table structure for table 'group'
		$this->dbforge->add_field([
			'id' => [
				'type' => 'INT',
				'unsigned' => true,
				'auto_increment' => true,
			],
			'name' => [
				'type' => 'VARCHAR',
				'constraint' => '20',
			],
			'description' => [
				'type' => 'VARCHAR',
				'constraint' => '100',
			],
			'level' => [
				'type' => 'MEDIUMINT',
				'constraint' => '8',
				'unsigned' => true
			]
		]);
		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('group');

		// Dumping data for table 'group'
		$data = [
			[
				'id' => '1',
				'name' => 'admin',
				'level' => '10',
				'description' => 'Administrator'
			],
			[
				'id' => '2',
				'name' => 'members',
				'level' => '1',
				'description' => 'General User'
			]
		];
		$this->db->insert_batch('group', $data);


		// Drop table 'user' if it exists
		$this->dbforge->drop_table('user', true);

		// Table structure for table 'user'
		$this->dbforge->add_field([
			'id' => [
				'type' => 'INT',
				'unsigned' => true,
				'auto_increment' => true,
			],
			'ip_address' => [
				'type' => 'VARCHAR',
				'constraint' => '16'
			],
			'username' => [
				'type' => 'VARCHAR',
				'constraint' => '100',
			],
			'password' => [
				'type' => 'VARCHAR',
				'constraint' => '80',
			],
			'salt' => [
				'type' => 'VARCHAR',
				'constraint' => '40'
			],
			'email' => [
				'type' => 'VARCHAR',
				'constraint' => '100'
			],
			'activation_code' => [
				'type' => 'VARCHAR',
				'constraint' => '40',
				'null' => true
			],
			'forgotten_password_code' => [
				'type' => 'VARCHAR',
				'constraint' => '40',
				'null' => true
			],
			'forgotten_password_time' => [
				'type' => 'INT',
				'unsigned' => true,
				'null' => true
			],
			'remember_code' => [
				'type' => 'VARCHAR',
				'constraint' => '40',
				'null' => true
			],
			'created_on' => [
				'type' => 'INT',
				'unsigned' => true,
			],
			'last_login' => [
				'type' => 'INT',
				'unsigned' => true,
				'null' => true
			],
			'active' => [
				'type' => 'TINYINT',
				'constraint' => '1',
				'unsigned' => true,
				'null' => true
			],
			'first_name' => [
				'type' => 'VARCHAR',
				'constraint' => '50',
				'null' => true
			],
			'last_name' => [
				'type' => 'VARCHAR',
				'constraint' => '50',
				'null' => true
			],
			'company' => [
				'type' => 'VARCHAR',
				'constraint' => '100',
				'null' => true
			],
			'phone' => [
				'type' => 'VARCHAR',
				'constraint' => '20',
				'null' => true
			],
			'image_name' => [
				'type' => 'VARCHAR',
				'constraint' => '128',
				'null' => true
			],
			'image_url' => [
				'type' => 'VARCHAR',
				'constraint' => '254',
				'null' => true
			],
			'last_update timestamp DEFAULT current_timestamp ON UPDATE current_timestamp',
			'last_api_date' => [
				'type' => 'TIMESTAMP',
				'null' => true
			],
			'last_api_os' => [
				'type' => 'TINYINT',
				'constraint' => '1',
				'unsigned' => true
			]
		]);
		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('user');

		// Dumping data for table 'users'
		$data = [
			'id' => '1',
			'ip_address' => '127.0.0.1',
			'username' => 'admin@admin.com',
			'password' => '$2a$07$SeBknntpZror9uyftVopmu61qg0ms8Qv1yV6FG.kQOSM.9QhmTo36',
			'salt' => '',
			'email' => 'admin@admin.com',
			'activation_code' => '',
			'forgotten_password_code' => null,
			'created_on' => '1268889823',
			'last_login' => '1268889823',
			'active' => '1',
			'first_name' => 'Admin',
			'last_name' => 'istrator',
			'company' => 'ADMIN',
			'phone' => '0'
		];
		$this->db->insert('user', $data);


		// Drop table 'user_group' if it exists
		$this->dbforge->drop_table('user_group', true);

		// Table structure for table 'user_group'
		$this->dbforge->add_field([
			'id' => [
				'type' => 'INT',
				'unsigned' => true,
				'auto_increment' => true,
			],
			'user_id' => [
				'type' => 'INT',
				'unsigned' => true
			],
			'group_id' => [
				'type' => 'INT',
				'unsigned' => true
			]
		]);
		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('user_group');

		// Dumping data for table 'user_group'
		$data = [
			[
				'id' => '1',
				'user_id' => '1',
				'group_id' => '1',
			],
			[
				'id' => '2',
				'user_id' => '1',
				'group_id' => '2',
			]
		];
		$this->db->insert_batch('user_group', $data);


		// Drop table 'login_attempts' if it exists
		$this->dbforge->drop_table('login_attempt', true);

		// Table structure for table 'login_attempt'
		$this->dbforge->add_field([
			'id' => [
				'type' => 'INT',
				'unsigned' => true,
				'auto_increment' => true,
			],
			'ip_address' => [
				'type' => 'VARCHAR',
				'constraint' => '16'
			],
			'login' => [
				'type' => 'VARCHAR',
				'constraint' => '100',
				'null',
				true
			],
			'time' => [
				'type' => 'BIGINT',
				'unsigned' => true,
				'null' => true
			]
		]);
		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('login_attempt');
	}

	public function down()
	{
		$this->dbforge->drop_table('user', true);
		$this->dbforge->drop_table('group', true);
		$this->dbforge->drop_table('user_group', true);
		$this->dbforge->drop_table('login_attempt', true);
	}
}
