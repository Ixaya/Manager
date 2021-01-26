<?php

class Migration_Page_Item extends CI_Migration {

	public function up() {
		$this->dbforge->add_field(array(
			'id' => array(
				'type' => 'INT',
				'constraint' => 11,
				'auto_increment' => TRUE
			),
			'title' => array(
				'type' => 'VARCHAR',
				'constraint' => 32
			),
			'description' => array(
				'type' => 'VARCHAR',
				'constraint' => 128,
				'null' => TRUE
			),
			'url' => array(
				'type' => 'VARCHAR',
				'constraint' => 128,
				'null' => TRUE
			),
			'kind' => array(
				'type' => 'INTEGER',
				'constraint' => 11
			),
			'faicon' => array(
				'type' => 'VARCHAR',
				'constraint' => 32,
				'null' => TRUE
			),
			'image_name' => array(
				'type' => 'VARCHAR',
				'constraint' => 32,
				'null' => TRUE
			),
			'last_update' => array(
				'type' => 'TIMESTAMP'
			),
			'create_date' => array(
				'type' => 'TIMESTAMP'
			)
		));

		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('page_item');
	}

	public function down() {
		$this->dbforge->drop_table('page_item');
	}
}
