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
			'page_section_id' => array(
				'type' => 'INTEGER',
				'constraint' => 11,
				'null' => true
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
		
		
		$data['title'] = 'Composer Upgradable';
		$data['description'] = 'CodeIgniter upgradeable through Composer (always use latest version)';
		$data['url'] = '';
		$data['kind'] = '1';
		$data['faicon'] = 'fa-square';
		$data['page_section_id'] = '1';
		$data['image_name'] = '';
		$this->db->insert('page_item', $data);

		$data['title'] = 'HMVC';
		$data['description'] = 'Modular, you can use Modules, but also Model-View-Controller Paradigm';
		$data['url'] = '';
		$data['kind'] = '1';
		$data['faicon'] = 'fa-cube';
		$data['page_section_id'] = '1';
		$data['image_name'] = '';
		$this->db->insert('page_item', $data);

		$data['title'] = 'Multi-Database Support';
		$data['description'] = 'Support for MySQL, PostgreSQL, MSSQL, Sqlite, or any database that is supported in CodeIgniter 3.';
		$data['url'] = '';
		$data['kind'] = '1';
		$data['faicon'] = 'fa-database';
		$data['page_section_id'] = '1';
		$data['image_name'] = '';
		$this->db->insert('page_item', $data);
		
		$data['title'] = 'About';
		$data['description'] = 'About';
		$data['url'] = 'about';
		$data['kind'] = '3';
		$data['faicon'] = '';
		$data['page_section_id'] = '3';
		$data['image_name'] = '';
		$this->db->insert('page_item', $data);
		
		$data['title'] = 'Humberto';
		$data['description'] = 'Easy Very easy to maintain.';
		$data['url'] = '';
		$data['kind'] = '3';
		$data['faicon'] = '';
		$data['page_section_id'] = '3';
		$data['image_name'] = '';
		$this->db->insert('page_item', $data);
		
		$data['title'] = 'Gustavo';
		$data['description'] = 'A Joy to use.';
		$data['url'] = '';
		$data['kind'] = '3';
		$data['faicon'] = '';
		$data['page_section_id'] = '3';
		$data['image_name'] = '';
		$this->db->insert('page_item', $data);
		
		$data['title'] = 'Leonardo';
		$data['description'] = 'Easy to do multi-language sites.';
		$data['url'] = '';
		$data['kind'] = '3';
		$data['faicon'] = '';
		$data['page_section_id'] = '3';
		$data['image_name'] = '';
		$this->db->insert('page_item', $data);
	}

	public function down() {
		$this->dbforge->drop_table('page_item');
	}
}
