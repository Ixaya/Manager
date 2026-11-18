<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Manager_user_key_v3 extends MGR_Migration_builder
{
	public function up()
	{
		$this->start_ddl_transaction();

		$this->db->where('activated IS NULL', null, false)->update('user_key', ['activated' => 1]);

		// MySQL/MariaDB without strict mode would turn a remaining NULL into 0, a revoked key.
		$this->assert_no_nulls(table: 'user_key', columns: ['activated']);

		$this->dbforge->modify_column('user_key', [
			...$this->field(name: 'activated', type: MgrFieldType::TinyInt, nullable: false, default: 1),
		]);

		$this->end_ddl_transaction();
	}

	public function down()
	{
		// The backfilled NULLs are not restored: NULL and 1 both mean active. default: null because on
		// PostgreSQL a modify without a default keeps the existing one.
		$this->dbforge->modify_column('user_key', [
			...$this->field(name: 'activated', type: MgrFieldType::TinyInt, nullable: true, default: null),
		]);
	}
}
