<?php

class Migration_Domain extends MGR_Migration_builder
{
	public function up()
	{
		$this->dbforge->add_field([
			...$this->field_id('id'),
			...$this->field(name: 'client_id', type: MgrFieldType::Int, unsigned: true),
			...$this->field(name: 'theme_id', type: MgrFieldType::Int, unsigned: true),
			...$this->field(name: 'domain_name', type: MgrFieldType::VarChar, constraint: 120),
			...$this->field(name: 'redirect_url', type: MgrFieldType::VarChar, constraint: 120),
			...$this->field(name: 'last_update', type: MgrFieldType::Timestamp),
		]);

		$this->dbforge->add_key('id', true);
		$this->dbforge->create_table('domain');
	}

	public function down()
	{
		$this->dbforge->drop_table('domain');
	}
}
