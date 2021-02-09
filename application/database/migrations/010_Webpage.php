<?php

class Migration_Webpage extends CI_Migration {

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
			'slug' => array(
				'type' => 'VARCHAR',
				'constraint' => 32,
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
		$this->dbforge->create_table('webpage');
		
		$data['title'] = 'About';
		$data['slug'] = 'about';
		$data['kind'] = '1';
		$this->db->insert('webpage', $data);
		
		$data['title'] = 'Links';
		$data['slug'] = 'links';
		$data['kind'] = '1';
		$this->db->insert('webpage', $data);
		
	}

	public function down() {
		$this->dbforge->drop_table('webpage');
	}
}
