<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Manager_ion_auth_v4 extends MGR_Migration_builder
{
	public function up()
	{
		$this->start_ddl_transaction();

		// built first, so a missing CI key or an unusable collation throws before any column changes
		$username   = $this->field(name: 'username', type: MgrFieldType::VarChar, constraint: 100, nullable: false, case_insensitive: true);
		$email      = $this->field(name: 'email', type: MgrFieldType::VarChar, constraint: 254, nullable: false, case_insensitive: true);
		$login      = $this->field(name: 'login', type: MgrFieldType::VarChar, constraint: 100, nullable: true, case_insensitive: true);
		$ip_address = $this->field(name: 'ip_address', type: MgrFieldType::VarChar, constraint: 45, nullable: false);
		$created_on = $this->field(name: 'created_on', type: MgrFieldType::BigInt, unsigned: true, nullable: false);

		$this->assert_no_duplicates(table: 'user', column: 'username', case_insensitive: true);
		$this->assert_no_duplicates(table: 'user', column: 'email', case_insensitive: true);

		$this->assert_no_nulls(table: 'user', columns: ['email', 'ip_address', 'created_on']);
		$this->assert_no_nulls(table: 'login_attempt', columns: ['ip_address']);

		$this->dbforge->modify_column('user', [...$username, ...$email, ...$ip_address, ...$created_on]);
		$this->dbforge->modify_column('login_attempt', [...$login, ...$ip_address]);

		$this->add_index(table: 'user', columns: 'username', unique: true);
		$this->add_index(table: 'user', columns: 'email', unique: true);

		// after the login collation change, so the index is built once; (time) serves the purge's OR arm
		$this->add_index(table: 'login_attempt', columns: ['login', 'ip_address']);
		$this->add_index(table: 'login_attempt', columns: 'time');

		$this->end_ddl_transaction();
	}

	public function down()
	{
		$this->start_ddl_transaction();

		$this->drop_index(table: 'login_attempt', columns: 'time');
		$this->drop_index(table: 'login_attempt', columns: ['login', 'ip_address']);
		$this->drop_index(table: 'user', columns: 'username');

		$this->dbforge->modify_column('user', [
			...$this->field(name: 'username', type: MgrFieldType::VarChar, constraint: 100, nullable: false),
			...$this->field(name: 'email', type: MgrFieldType::VarChar, constraint: 254, nullable: false),
		]);
		$this->dbforge->modify_column('login_attempt', [
			...$this->field(name: 'login', type: MgrFieldType::VarChar, constraint: 100, nullable: true),
		]);

		$this->end_ddl_transaction();
	}
}
