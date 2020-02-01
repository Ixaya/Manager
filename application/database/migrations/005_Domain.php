<?php

class Migration_Domain extends CI_Migration {

	public function up() {
		$this->dbforge->add_field(array(
			'id' => array(
				'type' => 'INT',
				'constraint' => 11,
				'auto_increment' => TRUE
			),
			'client_id' => array(
				'type' => 'INT',
				'constraint' => 11
			),
			'theme_id' => array(
				'type' => 'INT',
				'constraint' => 11
			),
			'domain_name' => array(
				'type' => 'VARCHAR',
				'constraint' => 120
			),
			'redirect_url' => array(
				'type' => 'VARCHAR',
				'constraint' => 120
			),
			'last_update' => array(
				'type' => 'TIMESTAMP'
			)
		));

		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('domain');
	}

	public function down() {
		$this->dbforge->drop_table('domain');
	}

}
