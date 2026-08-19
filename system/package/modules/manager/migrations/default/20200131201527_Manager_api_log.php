<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Manager_api_log extends MGR_Migration_builder
{
	protected $table_name = 'api_log';

	public function up()
	{
		$this->dbforge->add_field([
			...$this->field_id('id'),
			...$this->field(name: 'uri', type: MgrFieldType::VarChar, constraint: 255),
			...$this->field(name: 'method', type: MgrFieldType::VarChar, constraint: 6),
			...$this->field(name: 'params', type: MgrFieldType::Text, nullable: true),
			...$this->field(name: 'api_key', type: MgrFieldType::VarChar, constraint: 40),
			...$this->field(name: 'ip_address', type: MgrFieldType::VarChar, constraint: 45),
			...$this->field(name: 'time', type: MgrFieldType::BigInt),
			...$this->field(name: 'rtime', type: MgrFieldType::Float, nullable: true),
			...$this->field(name: 'authorized', type: MgrFieldType::TinyInt),
			...$this->field(name: 'response_code', type: MgrFieldType::SmallInt, nullable: true),
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table($this->table_name);
	}

	public function down()
	{
		$this->dbforge->drop_table($this->table_name);
	}
}
