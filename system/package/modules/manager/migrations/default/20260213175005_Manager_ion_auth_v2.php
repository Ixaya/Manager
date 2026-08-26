<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Manager_ion_auth_v2 extends MGR_Migration_builder
{
	public function up()
	{
		$this->dbforge->modify_column('user', [
			...$this->field(name: 'ip_address', type: MgrFieldType::VarChar, constraint: 45),
			...$this->field(name: 'email', type: MgrFieldType::VarChar, constraint: 254),
			...$this->field(name: 'password', type: MgrFieldType::VarChar, constraint: 255, nullable: true),
			...$this->field(name: 'activation_code', type: MgrFieldType::VarChar, constraint: 255, nullable: true),
			...$this->field(name: 'forgotten_password_code', type: MgrFieldType::VarChar, constraint: 255, nullable: true),
			...$this->field(name: 'remember_code', type: MgrFieldType::VarChar, constraint: 255, nullable: true),
			...$this->field(name: 'created_on', type: MgrFieldType::BigInt, unsigned: true),
			...$this->field(name: 'last_login', type: MgrFieldType::BigInt, unsigned: true, nullable: true),
		]);

		$this->dbforge->add_column('user', [
			...$this->field(name: 'activation_selector', type: MgrFieldType::VarChar, constraint: 255, nullable: true, unique: true),
			...$this->field(name: 'forgotten_password_selector', type: MgrFieldType::VarChar, constraint: 255, nullable: true, unique: true),
			...$this->field(name: 'remember_selector', type: MgrFieldType::VarChar, constraint: 255, nullable: true, unique: true),
		]);

		$this->dbforge->modify_column('login_attempt', [
			...$this->field(name: 'ip_address', type: MgrFieldType::VarChar, constraint: 45),
		]);
	}

	public function down()
	{
		$this->dbforge->drop_column('user', 'activation_selector');
		$this->dbforge->drop_column('user', 'forgotten_password_selector');
		$this->dbforge->drop_column('user', 'remember_selector');

		$this->dbforge->modify_column('user', [
			...$this->field(name: 'ip_address', type: MgrFieldType::VarChar, constraint: 16),
			...$this->field(name: 'email', type: MgrFieldType::VarChar, constraint: 100),
			...$this->field(name: 'password', type: MgrFieldType::VarChar, constraint: 80, nullable: true),
			...$this->field(name: 'activation_code', type: MgrFieldType::VarChar, constraint: 40, nullable: true),
			...$this->field(name: 'forgotten_password_code', type: MgrFieldType::VarChar, constraint: 40, nullable: true),
			...$this->field(name: 'remember_code', type: MgrFieldType::VarChar, constraint: 40, nullable: true),
			...$this->field(name: 'created_on', type: MgrFieldType::Int, unsigned: true),
			...$this->field(name: 'last_login', type: MgrFieldType::Int, unsigned: true, nullable: true),
		]);

		$this->dbforge->modify_column('login_attempt', [
			...$this->field(name: 'ip_address', type: MgrFieldType::VarChar, constraint: 16),
		]);
	}
}
