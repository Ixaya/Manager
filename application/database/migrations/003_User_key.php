<?php

class Migration_User_key extends CI_Migration {

	public function up() {
		$this->dbforge->add_field(array(
			'id' => array(
				'type' => 'INT',
				'constraint' => 11,
				'auto_increment' => TRUE
			),
			'user_id' => array(
				'type' => 'INT',
				'constraint' => 11
			),
			'key' => array(
				'type' => 'VARCHAR',
				'constraint' => 40
			),
			'level' => array(
				'type' => 'INT',
				'constraint' => 2
			),
			'ignore_limits' => array(
				'type' => 'TINYINT',
				'constraint' => 1
			),
			'is_private_key' => array(
				'type' => 'TINYINT',
				'constraint' => 1
			),
			'activated' => array(
				'type' => 'TINYINT',
				'constraint' => 1
			),
			'ip_addresses' => array(
				'type' => 'TEXT'
			),
			'date_created' => array(
				'type' => 'INT',
				'constraint' => 11
			),
			'magazine_id' => array(
				'type' => 'INT',
				'constraint' => 11
			)
		));

		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('user_key');
	}

	public function down() {
		$this->dbforge->drop_table('user_key');
	}

}
