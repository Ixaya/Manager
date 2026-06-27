<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Manager_option extends MGR_Migration_builder
{
	public function up()
	{
		$this->dbforge->add_field([
			...$this->field(name: 'key', type: MgrFieldType::VarChar, constraint: 64),
			...$this->field(name: 'value', type: MgrFieldType::VarChar, constraint: 254),
			...$this->field(name: 'last_update', type: MgrFieldType::Timestamp)
		]);

		$this->dbforge->add_key('key', true);
		$this->dbforge->create_table('manager_option');

		$this->modify_field_timestamp('manager_option', 'last_update');
	}

	public function down()
	{
		$this->dbforge->drop_table('manager_option', true);
	}
}
