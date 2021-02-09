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
		
		$data['webpage_id'] = '1';
		$data['order'] = '1';
		$data['kind'] = '1';
		$data['content'] = '<p>About Ixaya/Manager</p>';
		$this->db->insert('page_section', $data);
		
		$data['webpage_id'] = '2';
		$data['order'] = '1';
		$data['kind'] = '6';
		$data['content'] = '';
		$this->db->insert('page_section', $data);
		
		$data['webpage_id'] = '1';
		$data['order'] = '1';
		$data['kind'] = '3';
		$data['content'] = '<p>What people say about the framework</p>';
		$this->db->insert('page_section', $data);
	}

	public function down() {
		$this->dbforge->drop_table('page_section');
	}
}
