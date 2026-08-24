<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Manager_mgr_option extends MGR_Migration_builder
{
	protected $table_name = 'manager_option';

	public function up()
	{
		$this->dbforge->add_field([
			...$this->field(name: 'key', type: MgrFieldType::VarChar, constraint: 64),
			...$this->field(name: 'value', type: MgrFieldType::VarChar, constraint: 254),
			...$this->field(name: 'last_update', type: MgrFieldType::Timestamp)
		]);

		$this->dbforge->add_key('key', true);
		$this->dbforge->create_table($this->table_name);

		$this->modify_field_timestamp($this->table_name, 'last_update');
	}

	public function down()
	{
		$this->modify_field_timestamp(table: $this->table_name, column: 'last_update', on_update: false, default: false);
		$this->dbforge->drop_table($this->table_name);
	}
}
