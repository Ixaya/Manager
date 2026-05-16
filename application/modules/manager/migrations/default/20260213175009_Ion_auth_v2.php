<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Ion_auth_v2 extends CI_Migration
{
	public function up()
	{
		$this->dbforge->modify_column('user', [
			'ip_address' => [
				'type' => 'VARCHAR',
				'constraint' => '45',
			],
			'email' => [
				'type' => 'VARCHAR',
				'constraint' => '254'
			],
			'activation_code' => [
				'type' => 'VARCHAR',
				'constraint' => '255',
				'null' => true,
			],
			'forgotten_password_code' => [
				'type' => 'VARCHAR',
				'constraint' => '255',
				'null' => true,
			],
			'remember_code' => [
				'type' => 'VARCHAR',
				'constraint' => '255',
				'null' => true,
			],
			'created_on' => [
				'type' => 'BIGINT',
				'unsigned' => true,
			],
			'last_login' => [
				'type' => 'BIGINT',
				'unsigned' => true,
				'null' => true,
			]
		]);

		$this->dbforge->add_column('user', [
			'activation_selector' => [
				'type' => 'VARCHAR',
				'constraint' => '255',
				'null' => true,
				'unique' => true,
			],
			'forgotten_password_selector' => [
				'type' => 'VARCHAR',
				'constraint' => '255',
				'null' => true,
				'unique' => true,
			],
			'remember_selector' => [
				'type' => 'VARCHAR',
				'constraint' => '255',
				'null' => true,
				'unique' => true,
			]
		]);

		$this->db->query('ALTER TABLE user ADD UNIQUE KEY `email` (`email`)');

		$this->forge->add_column('login_attempts', [
			'ip_address' => [
				'type' => 'VARCHAR',
				'constraint' => '45',
			]
		]);
	}

	public function down()
	{
		$this->db->query('ALTER TABLE `user` DROP INDEX `email`');

		$this->dbforge->drop_column('user', 'activation_selector');
		$this->dbforge->drop_column('user', 'forgotten_password_selector');
		$this->dbforge->drop_column('user', 'remember_selector');

		$this->dbforge->modify_column('user', [
			'ip_address' => [
				'type' => 'VARCHAR',
				'constraint' => '16',
			],
			'email' => [
				'type' => 'VARCHAR',
				'constraint' => '100'
			],
			'activation_code' => [
				'type' => 'VARCHAR',
				'constraint' => '40',
				'null' => true,
			],
			'forgotten_password_code' => [
				'type' => 'VARCHAR',
				'constraint' => '40',
				'null' => true,
			],
			'remember_code' => [
				'type' => 'VARCHAR',
				'constraint' => '40',
				'null' => true,
			],
			'created_on' => [
				'type' => 'INT',
				'unsigned' => true,
			],
			'last_login' => [
				'type' => 'INT',
				'unsigned' => true,
				'null' => true,
			]
		]);

		$this->dbforge->modify_column('login_attempts', [
			'ip_address' => [
				'type' => 'VARCHAR',
				'constraint' => '16',
			]
		]);
	}
}
