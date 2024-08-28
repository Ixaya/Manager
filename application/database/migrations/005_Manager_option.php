<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Manager_option extends CI_Migration
{

	public function up()
	{
		$this->dbforge->add_field(array(
			'key' => [
				'type' => 'VARCHAR',
				'constraint' => 64
			],
			'title' => [
				'type' => 'VARCHAR',
				'constraint' => 254
			],
			'last_update' => [
				'type' => 'TIMESTAMP'
			]
		));
		$this->dbforge->add_key('key', true);
		$this->dbforge->create_table('manager_option');
	}

	public function down()
	{
		$this->dbforge->drop_table('manager_option', TRUE);
	}
}