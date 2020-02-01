<?php

class Migration_Api_log extends CI_Migration {

	public function up() {
		$this->dbforge->add_field(array(
			'id' => array(
				'type' => 'INT',
				'constraint' => 11,
				'auto_increment' => TRUE
			),
			'uri' => array(
				'type' => 'VARCHAR',
				'constraint' => 255
			),
			'method' => array(
				'type' => 'VARCHAR',
				'constraint' => 6
			),
			'params' => array(
				'type' => 'TEXT'
			),
			'api_key' => array(
				'type' => 'VARCHAR',
				'constraint' => 40
			),
			'ip_address' => array(
				'type' => 'VARCHAR',
				'constraint' => 45
			),
			'time' => array(
				'type' => 'INT',
				'constraint' => 11
			),
			'rtime' => array(
				'type' => 'FLOAT'
			),
			'authorized' => array(
				'type' => 'VARCHAR',
				'constraint' => 1
			),
			'response_code' => array(
				'type' => 'SMALLINT',
				'constraint' => 3
			)
		));

		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('api_log');
	}

	public function down() {
		$this->dbforge->drop_table('api_log');
	}

}
