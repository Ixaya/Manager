<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Session extends MGR_Migration_builder
{
	public function up()
	{
		$this->dbforge->add_field([
			...$this->field(name: 'id', type: MgrFieldType::VarChar, constraint: 40),
			...$this->field(name: 'ip_address', type: MgrFieldType::VarChar, constraint: 45),
			...$this->field(name: 'timestamp', type: MgrFieldType::BigInt, unsigned: true, default: 0),
			...$this->field(name: 'data', type: MgrFieldType::Blob)
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->add_key('timestamp');
		$this->dbforge->create_table('ci_sessions');
	}

	public function down()
	{
		$this->dbforge->drop_table('ci_sessions', true);
	}
}
