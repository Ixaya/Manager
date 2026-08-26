<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Manager_ion_auth_v3 extends MGR_Migration_builder
{
	public function up()
	{
		$this->dbforge->modify_column('user', [
			...$this->field(name: 'email', type: MgrFieldType::VarChar, constraint: 254, unique: true),
			...$this->field(name: 'last_activity_date', type: MgrFieldType::Timestamp, nullable: true, new_name: 'last_api_date'),
			...$this->field(name: 'last_activity_os', type: MgrFieldType::TinyInt, unsigned: true, nullable: true, new_name: 'last_api_os')
		]);

		$this->dbforge->drop_column('user', 'salt');
	}

	public function down()
	{
		$this->drop_index(table: 'user', columns: 'email');

		$this->dbforge->modify_column('user', [
			...$this->field(name: 'email', type: MgrFieldType::VarChar, constraint: 254),
			...$this->field(name: 'last_api_date', type: MgrFieldType::Timestamp, nullable: true, new_name: 'last_activity_date'),
			...$this->field(name: 'last_api_os', type: MgrFieldType::TinyInt, constraint: 1, unsigned: true, nullable: true, new_name: 'last_activity_os'),
		]);

		$this->dbforge->add_column('user', [
			...$this->field(name: 'salt', type: MgrFieldType::VarChar, constraint: 40),
		]);
	}
}
