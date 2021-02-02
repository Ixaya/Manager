<?php

class Migration_Page_Section extends CI_Migration {

	public function up() {
		$this->dbforge->add_field(array(
			'id' => array(
				'type' => 'INT',
				'constraint' => 11,
				'auto_increment' => TRUE
			),
			'webpage_id' => array(
				'type' => 'INT',
				'constraint' => 11
			),
			'order' => array(
				'type' => 'INT',
				'constraint' => 11,
				'null' => TRUE
			),
			'kind' => array(
				'type' => 'INT',
				'constraint' => 11
			),
			'content' => array(
				'type' => 'LONGTEXT',
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
		$this->dbforge->create_table('page_section');
	}

	public function down() {
		$this->dbforge->drop_table('page_section');
	}
}
