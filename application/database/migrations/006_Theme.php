<?php

class Migration_Theme extends CI_Migration {

	public function up() {
		$this->dbforge->add_field(array(
			'id' => array(
				'type' => 'INT',
				'constraint' => 11,
				'auto_increment' => TRUE
			),
			'kind' => array(
				'type' => 'INT',
				'constraint' => 11
			),
			'shortname' => array(
				'type' => 'VARCHAR',
				'constraint' => 120
			),
			'title' => array(
				'type' => 'VARCHAR',
				'constraint' => 120
			),
			'description' => array(
				'type' => 'VARCHAR',
				'constraint' => 120
			),
			'image_url' => array(
				'type' => 'VARCHAR',
				'constraint' => 120
			),
			'last_update' => array(
				'type' => 'TIMESTAMP'
			)
		));

		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('theme');
	}

	public function down() {
		$this->dbforge->drop_table('theme');
	}

}
