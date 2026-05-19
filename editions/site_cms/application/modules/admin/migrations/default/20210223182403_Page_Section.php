<?php

class Migration_Page_Section extends CI_Migration
{
	public function up()
	{
		$this->dbforge->add_field([
			'id' => [
				'type' => 'INT',
				'unsigned' => true,
				'auto_increment' => true,
			],
			'webpage_id' => [
				'type' => 'INT',
				'unsigned' => true
			],
			'order' => [
				'type' => 'INT',
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

	public function down()
	{
		$this->dbforge->drop_table('page_section');
	}
}
