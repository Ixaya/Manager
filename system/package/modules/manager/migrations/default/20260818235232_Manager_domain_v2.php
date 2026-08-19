<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Manager_domain_v2 extends MGR_Migration_builder
{
	protected $table_name = 'domain';

	public function up()
	{
		$this->dbforge->add_column($this->table_name, [
			...$this->field(name: 'create_date', type: MgrFieldType::Timestamp, nullable: true),
		]);

		$this->modify_field_timestamp(table: $this->table_name, column: 'last_update', on_update: true, default: true);
		$this->modify_field_timestamp(table: $this->table_name, column: 'create_date', on_update: false, default: true);
	}

	public function down()
	{
		$this->modify_field_timestamp(table: $this->table_name, column: 'last_update', on_update: false, default: false);
		$this->dbforge->drop_column($this->table_name, 'create_date');
	}
}
