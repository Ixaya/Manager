<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Manager_user_key_v2 extends MGR_Migration_builder
{
	public function up()
	{
		$this->dbforge->add_column('user_key', [
			...$this->field(name: 'ignore_limits', type: MgrFieldType::TinyInt, nullable: false, default: 0),
			...$this->field(name: 'is_private_key', type: MgrFieldType::TinyInt, nullable: false, default: 0),
		]);
	}

	public function down()
	{
		$this->dbforge->drop_column('user_key', 'ignore_limits');
		$this->dbforge->drop_column('user_key', 'is_private_key');
	}
}
