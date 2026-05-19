<?php

class Migration_Example extends CI_Migration
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
				'constraint' => 100
			],
			'example' => [
				'type' => 'VARCHAR',
				'constraint' => 100
			],
			'last_update' => [
				'type' => 'TIMESTAMP'
			],
			'create_date' => [
				'type' => 'TIMESTAMP'
			]
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('example');
	}

	public function down()
	{
		$this->dbforge->drop_table('example');
	}
}
