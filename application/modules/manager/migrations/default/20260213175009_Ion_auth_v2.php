<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Ion_auth_v2 extends MGR_Migration_builder
{
	public function up()
	{
		$this->dbforge->modify_column('user', [
			...$this->field(name: 'ip_address', type: MgrFieldType::VarChar, length: 45),
			...$this->field(name: 'email', type: MgrFieldType::VarChar, length: 254, unique: true),
			...$this->field(name: 'password', type: MgrFieldType::VarChar, length: 255, nullable: true),
			...$this->field(name: 'activation_code', type: MgrFieldType::VarChar, length: 255, nullable: true),
			...$this->field(name: 'forgotten_password_code', type: MgrFieldType::VarChar, length: 255, nullable: true),
			...$this->field(name: 'remember_code', type: MgrFieldType::VarChar, length: 255, nullable: true),
			...$this->field(name: 'created_on', type: MgrFieldType::BigInt, unsigned: true),
			...$this->field(name: 'last_login', type: MgrFieldType::BigInt, unsigned: true, nullable: true),
			...$this->field(name: 'last_activity_date', type: MgrFieldType::Timestamp, nullable: true, new_name: 'last_api_date'),
			...$this->field(name: 'last_activity_os', type: MgrFieldType::TinyInt, unsigned: true, nullable: true, new_name: 'last_api_date')
		]);

		$this->dbforge->add_column('user', [
			...$this->field(name: 'activation_selector', type: MgrFieldType::VarChar, length: 255, nullable: true, unique: true),
			...$this->field(name: 'forgotten_password_selector', type: MgrFieldType::VarChar, length: 255, nullable: true, unique: true),
			...$this->field(name: 'remember_selector', type: MgrFieldType::VarChar, length: 255, nullable: true, unique: true),
		]);

		$this->dbforge->drop_column('user', 'salt');

		$this->add_index(table: 'user', columns: 'email', unique: true);

		$this->dbforge->modify_column('login_attempt', [
			...$this->field(name: 'ip_address', type: MgrFieldType::VarChar, length: 45),
		]);
	}

	public function down()
	{
		$this->drop_index(table: 'user', columns: 'email');

		$this->dbforge->drop_column('user', 'activation_selector');
		$this->dbforge->drop_column('user', 'forgotten_password_selector');
		$this->dbforge->drop_column('user', 'remember_selector');

		$this->dbforge->modify_column('user', [
			...$this->field(name: 'ip_address', type: MgrFieldType::VarChar, length: 16),
			...$this->field(name: 'email', type: MgrFieldType::VarChar, length: 100),
			...$this->field(name: 'password', type: MgrFieldType::VarChar, length: 80, nullable: true),
			...$this->field(name: 'activation_code', type: MgrFieldType::VarChar, length: 40, nullable: true),
			...$this->field(name: 'forgotten_password_code', type: MgrFieldType::VarChar, length: 40, nullable: true),
			...$this->field(name: 'remember_code', type: MgrFieldType::VarChar, length: 40, nullable: true),
			...$this->field(name: 'created_on', type: MgrFieldType::Int, unsigned: true),
			...$this->field(name: 'last_login', type: MgrFieldType::Int, unsigned: true, nullable: true),
			...$this->field(name: 'last_activity_date', type: MgrFieldType::Timestamp, nullable: true, new_name: 'last_api_date'),
			...$this->field(name: 'last_activity_os', type: MgrFieldType::TinyInt, length: 1, unsigned: true, nullable: true, new_name: 'last_api_os'),
		]);

		$this->dbforge->add_column('user', [
			...$this->field(name: 'salt', type: MgrFieldType::VarChar, length: 40),
		]);

		$this->dbforge->modify_column('login_attempt', [
			...$this->field(name: 'ip_address', type: MgrFieldType::VarChar, length: 16),
		]);
	}
}
