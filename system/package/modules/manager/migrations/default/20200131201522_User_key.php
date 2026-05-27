<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_User_key extends MGR_Migration_builder
{
	public function up()
	{
		$this->dbforge->add_field([
			...$this->field_id('id'),
			...$this->field(name: 'user_id', type: MgrFieldType::Int, unsigned: true),
			...$this->field(name: 'key', type: MgrFieldType::VarChar, length: 40),
			...$this->field(name: 'level', type: MgrFieldType::TinyInt),
			...$this->field(name: 'activated', type: MgrFieldType::TinyInt, nullable: true),
			...$this->field(name: 'ip_addresses', type: MgrFieldType::Text, nullable: true),
			...$this->field(name: 'user_agent', type: MgrFieldType::Text, nullable: true),
			...$this->field(name: 'device_uuid', type: MgrFieldType::Uuid, nullable: true),
			...$this->field(name: 'date_created', type: MgrFieldType::BigInt)
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->add_key('key');
		$this->dbforge->create_table('user_key');
	}

	public function down()
	{
		$this->dbforge->drop_table('user_key');
	}
}
