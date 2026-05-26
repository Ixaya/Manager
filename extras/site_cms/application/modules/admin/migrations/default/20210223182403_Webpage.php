<?php

class Migration_Webpage extends CI_Migration
{
	public function up()
	{
		$this->dbforge->add_field([
			'id' => [
				'type' => 'INT',
				'unsigned' => true,
				'auto_increment' => true,
			],
			'title' => [
				'type' => 'VARCHAR',
				'constraint' => 32
			],
			'slug' => [
				'type' => 'VARCHAR',
				'constraint' => 32,
				'null' => true
			],
			'kind' => [
				'type' => 'INT',
				'constraint' => 11
			],
			'content' => [
				'type' => 'LONGTEXT',
				'null' => true
			],
			'last_update' => [
				'type' => 'TIMESTAMP'
			],
			'create_date' => [
				'type' => 'TIMESTAMP'
			]
		]);

		$this->dbforge->add_key('id', true);
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

	public function down()
	{
		$this->dbforge->drop_table('webpage');
	}
}
