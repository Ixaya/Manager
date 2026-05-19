<?php

class Migration_Theme extends CI_Migration
{
	public function up()
	{
		$this->dbforge->add_field([
			'id' => [
				'type' => 'INT',
				'unsigned' => true,
				'auto_increment' => true,
			],
			'kind' => [
				'type' => 'INT',
				'constraint' => 11
			],
			'shortname' => [
				'type' => 'VARCHAR',
				'constraint' => 120
			],
			'title' => [
				'type' => 'VARCHAR',
				'constraint' => 120
			],
			'description' => [
				'type' => 'VARCHAR',
				'constraint' => 120
			],
			'image_url' => [
				'type' => 'VARCHAR',
				'constraint' => 120
			],
			'last_update' => [
				'type' => 'TIMESTAMP'
			]
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('theme');
	}

	public function down()
	{
		$this->dbforge->drop_table('theme');
	}
}
