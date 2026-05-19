<?php

class Migration_Notification extends CI_Migration
{
	public function up()
	{
		$this->dbforge->add_field([
			'id' => [
				'type' => 'INT',
				'unsigned' => true,
				'auto_increment' => true,
			],
			'user_id' => [
				'type' => 'INT',
				'null' => true
			],
			'channel' => [
				'type' => 'VARCHAR',
				'constraint' => '100',
			],
			'event' => [
				'type' => 'VARCHAR',
				'constraint' => '100',
			],
			'data' => [
				'type' => 'TEXT',
			],
			'read' => [
				'type' => 'INT',
				'default' => 0
			],
			'deleted' => [
				'type' => 'INT',
				'default' => 0
			],
			'last_update' => [
				'type' => 'TIMESTAMP'
			],
			'create_date' => [
				'type' => 'TIMESTAMP'
			]
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->add_key('user_id');
		$this->dbforge->create_table('notification');
	}

	public function down()
	{
		$this->dbforge->drop_table('notification');
	}
}
