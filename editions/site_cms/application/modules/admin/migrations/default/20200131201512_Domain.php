<?php

class Migration_Domain extends CI_Migration
{
	public function up()
	{
		$this->dbforge->add_field([
			'id' => [
				'type' => 'INT',
				'unsigned' => true,
				'auto_increment' => true,
			],
			'client_id' => [
				'type' => 'INT',
				'unsigned' => true
			],
			'theme_id' => [
				'type' => 'INT',
				'unsigned' => true
			],
			'domain_name' => [
				'type' => 'VARCHAR',
				'constraint' => 120
			],
			'redirect_url' => [
				'type' => 'VARCHAR',
				'constraint' => 120
			],
			'last_update' => [
				'type' => 'TIMESTAMP'
			]
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('domain');
	}

	public function down()
	{
		$this->dbforge->drop_table('domain');
	}
}
