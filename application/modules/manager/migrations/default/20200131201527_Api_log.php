<?php

class Migration_Api_log extends MGR_Migration
{
	public function up()
	{
		$this->dbforge->add_field([
			...$this->field_id('id'),
			...$this->field(name: 'uri', type: MgrFieldType::VarChar, length: 255),
			...$this->field(name: 'method', type: MgrFieldType::VarChar, length: 6),
			...$this->field(name: 'params', type: MgrFieldType::Text, nullable: true),
			...$this->field(name: 'api_key', type: MgrFieldType::VarChar, length: 40),
			...$this->field(name: 'ip_address', type: MgrFieldType::VarChar, length: 45),
			...$this->field(name: 'time', type: MgrFieldType::VarChar, length: 45),
			...$this->field(name: 'rtime', type: MgrFieldType::Float, nullable: true),
			...$this->field(name: 'authorized', type: MgrFieldType::TinyInt),
			...$this->field(name: 'response_code', type: MgrFieldType::SmallInt, nullable: true),
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('api_log');
	}

	public function down()
	{
		$this->dbforge->drop_table('api_log');
	}
}
